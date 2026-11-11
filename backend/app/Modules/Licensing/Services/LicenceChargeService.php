<?php

namespace App\Modules\Licensing\Services;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\RevenuePolicy;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\RevenuePolicyService;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceCharge;
use App\Modules\Licensing\Models\LicenceFeeRelease;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\AllocationService;
use App\Modules\Revenue\Services\BillingService;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shared billing and controlled revenue release for licence-specific charges. */
class LicenceChargeService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly RevenuePolicyService $revenuePolicies,
        private readonly PostingService $posting,
        private readonly PaymentService $payments,
        private readonly PermissionService $permissions,
        private readonly AuditLogger $audit,
        private readonly TenantManager $tenants,
        private readonly AccountLinkService $links,
    ) {}

    public function assess(LicenceApplication $application, string $stage, int $amountMinor, User $actor): ?LicenceCharge
    {
        if ($amountMinor === 0) {
            return null;
        }
        if ($amountMinor < 0 || ! in_array($stage, ['application', 'renewal', 'inspection', 'issuance'], true)) {
            throw new \DomainException('Licence charge stage or amount is invalid.');
        }

        return DB::connection('tenant')->transaction(function () use ($application, $stage, $amountMinor, $actor) {
            $lockedApplication = LicenceApplication::query()->lockForUpdate()->findOrFail($application->id);
            $existing = LicenceCharge::where('licence_application_id', $lockedApplication->id)->where('stage', $stage)->first();
            if ($existing) {
                return $existing;
            }
            $configuration = $lockedApplication->configuration_snapshot;
            $chargeSettings = $configuration['charge_rules'][$stage] ?? [];
            $recognitionConfiguration = $configuration['revenue_recognition'] ?? [];
            $chargeType = (string) ($chargeSettings['charge_type'] ?? $recognitionConfiguration['charge_type'] ?? 'licence_application');
            $date = now($this->tenants->requireCouncil()->timezone ?: 'Africa/Harare')->toDateString();
            $policy = $this->revenuePolicies->resolve($chargeType, $date);
            $recognition = $policy?->recognition_method ?? 'demonstration_immediate';
            $configuredRecognition = $chargeSettings['recognition_method'] ?? $recognitionConfiguration['method'] ?? 'immediate';
            if ($policy && $policy->recognition_method !== $configuredRecognition) {
                throw new \DomainException('The approved revenue policy and licence configuration recognition methods do not match.');
            }
            if (! $policy && $configuredRecognition !== 'immediate') {
                throw new \DomainException('An independently approved revenue policy is required for non-immediate licence fees.');
            }
            if (in_array($recognition, ['advance_receipt', 'refundable_deposit'], true)) {
                throw new \DomainException('Advance-receipt and refundable-deposit licence charges require their own approved liability release and refund workflows.');
            }
            if ($recognition === 'deferred' && ! $policy?->recognition_rule) {
                throw new \DomainException('The approved deferred revenue policy has no recognition trigger and evidence rule.');
            }
            $account = CustomerAccount::findOrFail($lockedApplication->customer_account_id);
            $eventKey = 'licence-charge:'.$lockedApplication->id.':'.$stage;
            $bill = $this->billing->issueLicenceFee(
                $account,
                $amountMinor,
                $chargeType,
                ucfirst($stage).' fee for '.($lockedApplication->configuration_snapshot['label'] ?? 'licence application'),
                $eventKey,
                $actor,
            );
            $treatment = $bill->calculation_snapshot['revenue_policies'][0] ?? [];
            $paymentDefaults = [
                'application' => 'decision', 'renewal' => 'decision',
                'inspection' => 'inspection_completion', 'issuance' => 'certificate_issue',
            ];
            $refundPolicy = $chargeSettings['refund_policy'] ?? $configuration['refund_policy'] ?? [];
            $refundPolicy['refundable'] = (bool) ($chargeSettings['refundable'] ?? $refundPolicy['refundable'] ?? $configuration['charges']['refundable'] ?? false);
            $refundPolicy['events'] ??= $refundPolicy['refundable'] ? ['application_rejected'] : [];
            $charge = LicenceCharge::create([
                'licence_application_id' => $lockedApplication->id,
                'licence_type_version_id' => $lockedApplication->licence_type_version_id,
                'bill_id' => $bill->id,
                'revenue_policy_id' => $policy?->id,
                'stage' => $stage,
                'trigger_event' => $chargeSettings['trigger_event'] ?? $this->defaultTrigger($stage),
                'event_key' => $eventKey,
                'amount_minor' => $amountMinor,
                'currency_code' => $bill->currency_code,
                'payment_prerequisite' => (bool) ($chargeSettings['payment_prerequisite'] ?? true),
                'payment_required_before' => $chargeSettings['payment_required_before'] ?? $paymentDefaults[$stage],
                'refund_policy' => $refundPolicy,
                'accounting_treatment' => $treatment,
                'recognition_method' => $recognition,
                'status' => 'outstanding',
            ]);
            $this->audit->record(action: 'licensing.charge.assessed', subject: $charge, after: [
                'stage' => $stage, 'amount_minor' => $amountMinor, 'bill_id' => $bill->id,
                'configuration_version_id' => $lockedApplication->licence_type_version_id, 'revenue_policy_id' => $policy?->id,
            ], module: 'licensing');

            return $charge;
        });
    }

    /** Reconstruct a ledger row around an existing bill without charging it again. */
    public function adoptLegacyApplicationCharge(LicenceApplication $application): ?LicenceCharge
    {
        if ($application->charges()->exists() || $application->fee_amount_minor <= 0 || ! $application->fee_bill_id) {
            return $application->charges()->first();
        }
        $bill = Bill::findOrFail($application->fee_bill_id);
        $stage = $application->attempt_type === 'renewal' ? 'renewal' : 'application';
        $snapshot = $bill->calculation_snapshot['revenue_policies'][0] ?? [];
        $refundPolicy = $application->configuration_snapshot['refund_policy'] ?? [];
        $refundPolicy['refundable'] = (bool) ($application->configuration_snapshot['charges']['refundable'] ?? false);
        $refundPolicy['events'] ??= $refundPolicy['refundable'] ? ['application_rejected'] : [];

        return LicenceCharge::firstOrCreate(['event_key' => 'licence-charge-legacy:'.$application->id.':'.$stage], [
            'licence_application_id' => $application->id, 'licence_type_version_id' => $application->licence_type_version_id,
            'bill_id' => $bill->id, 'revenue_policy_id' => $bill->revenue_policy_id, 'stage' => $stage,
            'trigger_event' => $stage === 'renewal' ? 'renewal_submitted' : 'application_submitted',
            'amount_minor' => (int) $application->fee_amount_minor, 'currency_code' => $bill->currency_code,
            'payment_prerequisite' => true, 'payment_required_before' => 'decision', 'refund_policy' => $refundPolicy,
            'accounting_treatment' => $snapshot, 'recognition_method' => $bill->revenue_recognition_method ?: 'immediate',
            'status' => $bill->status === 'void' ? 'cancelled' : 'outstanding',
            'cancelled_at' => $application->fee_cancelled_at, 'cancelled_by' => $application->fee_cancelled_by,
            'cancellation_reason' => $application->fee_cancellation_reason,
        ]);
    }

    public function paymentIntent(User $user, LicenceApplication $application, LicenceCharge $charge, string $idempotencyKey): array
    {
        $account = $this->links->requireAccount($user, $application->customer_account_id);
        if ($charge->licence_application_id !== $application->id || $application->submitted_by !== $user->getKey()) {
            abort(404);
        }
        $bill = Bill::findOrFail($charge->bill_id);
        if (! $bill->isPayable()) {
            throw new \DomainException('This licence charge is already settled.');
        }
        $intent = $this->payments->createIntent($account, (int) $bill->balance_minor, 'web', 'sandbox', $idempotencyKey, $user, [
            'licence_application_id' => $application->id,
            'licence_charge_id' => $charge->id,
            'licence_charge_stage' => $charge->stage,
            'bill_id' => $bill->id,
        ]);

        return ['intent' => $intent, 'checkout' => $this->payments->gateway($intent->gateway)->createCheckout($intent)];
    }

    public function assertStageSettled(LicenceApplication $application, string $beforeEvent): void
    {
        $charge = LicenceCharge::where('licence_application_id', $application->id)
            ->where('payment_prerequisite', true)->where('payment_required_before', $beforeEvent)->get()
            ->first(fn (LicenceCharge $charge): bool => ! in_array($charge->status, ['waived', 'cancelled'], true)
                && (! $charge->bill_id || Bill::find($charge->bill_id)?->balance_minor !== 0));
        if ($charge) {
            throw new \DomainException('The '.$charge->stage.' charge must be fully paid, allocated or formally waived before '.$beforeEvent.'.');
        }
    }

    public function waive(LicenceCharge $charge, string $reason, User $user): LicenceCharge
    {
        $this->permissions->authorize('licensing.charges.waive');
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required to waive a licence charge.');
        }

        return DB::connection('tenant')->transaction(function () use ($charge, $reason, $user) {
            $locked = LicenceCharge::query()->lockForUpdate()->findOrFail($charge->id);
            if ($locked->status === 'waived') {
                return $locked;
            }
            if ($locked->status !== 'outstanding' || ! $locked->bill_id) {
                throw new \DomainException('Only an outstanding, billed licence charge can be waived.');
            }
            $bill = Bill::query()->lockForUpdate()->findOrFail($locked->bill_id);
            if ($bill->paid_minor > 0) {
                throw new \DomainException('A charge with any payment allocated cannot be waived; use the approved cancellation/refund correction path.');
            }
            $this->postChargeCreditNote($locked, $bill, $user, $reason, 'waiver');
            $bill->forceFill(['status' => 'void', 'paid_minor' => 0, 'balance_minor' => 0])->save();
            $locked->forceFill(['status' => 'waived', 'waived_by' => $user->getKey(), 'waived_at' => now(), 'waiver_reason' => $reason])->save();
            $this->audit->record(action: 'licensing.charge.waived', subject: $locked, after: ['reason' => $reason, 'bill_id' => $bill->id], module: 'licensing');

            return $locked;
        });
    }

    public function releaseDeferred(LicenceCharge $charge, int $amountMinor, string $triggerEvent, string $eventKey, string $evidence, User $user): LicenceFeeRelease
    {
        $this->permissions->authorize('licensing.charges.release');
        if ($amountMinor <= 0 || trim($eventKey) === '' || trim($evidence) === '') {
            throw new \DomainException('A positive release amount, unique event key and evidence are required.');
        }

        return DB::connection('tenant')->transaction(function () use ($charge, $amountMinor, $triggerEvent, $eventKey, $evidence, $user) {
            $prior = LicenceFeeRelease::where('event_key', $eventKey)->first();
            if ($prior) {
                if ($prior->licence_charge_id !== $charge->id || $prior->amount_minor !== $amountMinor || $prior->trigger_event !== $triggerEvent) {
                    throw new \DomainException('Release event key already belongs to a different release.');
                }

                return $prior;
            }
            $locked = LicenceCharge::query()->lockForUpdate()->findOrFail($charge->id);
            if ($locked->recognition_method !== 'deferred' || ! $locked->bill_id) {
                throw new \DomainException('Only charges posted to a deferred liability can be released.');
            }
            if (in_array($locked->status, ['cancelled', 'waived'], true)) {
                throw new \DomainException('A cancelled or waived charge cannot be released into revenue.');
            }
            $bill = Bill::findOrFail($locked->bill_id);
            $snapshot = $locked->accounting_treatment;
            $policy = RevenuePolicy::findOrFail($locked->revenue_policy_id);
            if ($policy->status !== 'approved' || $policy->recognition_method !== 'deferred' || (int) $policy->version !== (int) ($snapshot['version'] ?? -1)) {
                throw new \DomainException('The original independently approved revenue policy version is missing or does not match the billed snapshot.');
            }
            $rule = $snapshot['recognition_rule'] ?? $policy->recognition_rule ?? [];
            if (($rule['trigger_event'] ?? null) !== $triggerEvent) {
                throw new \DomainException('The release event does not match the event authorised by the billed policy snapshot.');
            }
            $this->assertReleaseEvent($locked, $triggerEvent);
            $released = (int) $locked->releases()->sum('amount_minor');
            $available = (int) $locked->amount_minor - $released;
            if ($amountMinor > $available) {
                throw new \DomainException('Release exceeds the unrecognised balance remaining on this charge.');
            }
            $liabilityCode = $snapshot['credit'] ?? null;
            $revenueCode = $snapshot['revenue_account_code'] ?? null;
            if (! $liabilityCode || ! $revenueCode || ! $bill->journal_entry_id) {
                throw new \DomainException('The original bill does not contain sufficient account and journal provenance for a safe release.');
            }
            $releaseId = (string) Str::ulid();
            $journal = $this->posting->post([
                'journal_type' => 'revenue_recognition', 'entry_date' => now($this->tenants->requireCouncil()->timezone ?: 'Africa/Harare')->toDateString(),
                'description' => 'Licence deferred fee release '.$locked->stage.' '.$locked->event_key,
                'currency_code' => $bill->currency_code,
                'lines' => [
                    ['account_code' => $liabilityCode, 'debit_minor' => $amountMinor, 'memo' => 'Release of licence fee liability'],
                    ['account_code' => $revenueCode, 'credit_minor' => $amountMinor, 'memo' => 'Recognised licence fee revenue'],
                ],
                'source_type' => 'licence_fee_release', 'source_id' => $releaseId,
                'idempotency_key' => 'licence-fee-release:'.$eventKey, 'created_by' => $user->getKey(),
            ]);
            $release = LicenceFeeRelease::create([
                'id' => $releaseId, 'licence_charge_id' => $locked->id, 'revenue_policy_id' => $policy->id,
                'original_journal_entry_id' => $bill->journal_entry_id, 'journal_entry_id' => $journal->id,
                'amount_minor' => $amountMinor, 'trigger_event' => $triggerEvent, 'event_key' => $eventKey,
                'evidence' => $evidence, 'released_by' => $user->getKey(), 'released_at' => now(),
            ]);
            $locked->forceFill(['released_minor' => $released + $amountMinor])->save();
            $this->audit->record(action: 'licensing.charge.released', subject: $release, after: [
                'charge_id' => $locked->id, 'amount_minor' => $amountMinor, 'trigger_event' => $triggerEvent,
                'policy_id' => $policy->id, 'policy_version' => $policy->version, 'journal_id' => $journal->id,
            ], module: 'licensing');

            return $release;
        });
    }

    /** Release all deferred licence charges whose approved term has elapsed. */
    public function releaseEligibleDeferred(User $user): array
    {
        $this->permissions->authorize('licensing.charges.release');
        $released = [];
        $timezone = $this->tenants->requireCouncil()->timezone ?: 'Africa/Harare';
        $today = now($timezone)->toDateString();
        $charges = LicenceCharge::where('recognition_method', 'deferred')
            ->whereNotIn('status', ['cancelled', 'waived'])
            ->with('bill')
            ->get();
        foreach ($charges as $charge) {
            $snapshot = $charge->accounting_treatment ?? [];
            $rule = $snapshot['recognition_rule'] ?? [];
            if (($rule['trigger_event'] ?? null) !== 'term_elapsed') {
                continue;
            }
            $termDays = (int) ($rule['term_days'] ?? 0);
            $billDate = $charge->bill?->issue_date;
            if ($termDays < 1 || ! $billDate) {
                continue;
            }
            if ($today < $billDate->copy()->addDays($termDays)->toDateString()) {
                continue;
            }
            $available = (int) $charge->amount_minor - (int) $charge->releases()->sum('amount_minor');
            if ($available <= 0) {
                continue;
            }
            try {
                $this->releaseDeferred($charge, $available, 'term_elapsed', 'term-elapsed:'.$charge->id, 'Scheduled term elapsed', $user);
                $released[] = $charge->id;
            } catch (\Throwable $exception) {
                // Leave the charge for manual review; do not abort the whole run.
            }
        }

        return $released;
    }

    public function cancelForRefund(LicenceCharge $charge, string $reason, User $user): LicenceCharge
    {
        $this->permissions->authorize('licensing.applications.approve');
        $this->permissions->authorize('revenue.payment.reverse');
        if (trim($reason) === '') {
            throw new \DomainException('A cancellation reason is required.');
        }

        return DB::connection('tenant')->transaction(function () use ($charge, $reason, $user) {
            $locked = LicenceCharge::query()->lockForUpdate()->findOrFail($charge->id);
            $application = LicenceApplication::query()->lockForUpdate()->findOrFail($locked->licence_application_id);
            $isRejectedRefund = $application->status === 'rejected' && in_array('application_rejected', $locked->refund_policy['events'] ?? [], true);
            $isExpiredRefund = $application->status === 'issued'
                && $application->licence_id
                && Licence::whereKey($application->licence_id)->value('validity_status') === 'expired'
                && in_array('licence_expired', $locked->refund_policy['events'] ?? [], true);
            if (! ($locked->refund_policy['refundable'] ?? false) || (! $isRejectedRefund && ! $isExpiredRefund)) {
                throw new \DomainException('The current application or licence event is not authorised by the snapshotted refund policy.');
            }
            if ($application->submitted_by === $user->getKey() || $application->decided_by === $user->getKey()) {
                throw new \DomainException('Separation of duties: applicant and decision maker cannot cancel this charge.');
            }
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            $bill = Bill::query()->lockForUpdate()->findOrFail($locked->bill_id);
            if ($bill->status === 'void') {
                throw new \DomainException('The charge bill has already been cancelled.');
            }
            foreach (PaymentAllocation::where('bill_id', $bill->id)->whereNull('reversed_at')->lockForUpdate()->get() as $allocation) {
                app(AllocationService::class)->reverseAllocation($allocation, 'Licence charge refund cancellation: '.$reason, $user);
            }
            $this->postChargeCreditNote($locked, $bill, $user, $reason, 'refund-cancellation');
            $bill->forceFill(['status' => 'void', 'paid_minor' => 0, 'balance_minor' => 0])->save();
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->getKey(), 'cancellation_reason' => $reason])->save();
            if ($locked->stage === 'application' || $locked->stage === 'renewal') {
                $application->forceFill(['fee_cancelled_at' => now(), 'fee_cancelled_by' => $user->getKey(), 'fee_cancellation_reason' => $reason,
                    'fee_credit_note_journal_id' => JournalEntry::where('source_type', 'licence_charge_credit_note')->where('source_id', $locked->id)->value('id')])->save();
            }
            $this->audit->record(action: 'licensing.charge.cancelled_for_refund', subject: $locked, after: ['reason' => $reason, 'bill_id' => $bill->id], module: 'licensing');

            return $locked;
        });
    }

    public function view(LicenceCharge $charge): array
    {
        $bill = $charge->bill_id ? Bill::find($charge->bill_id) : null;
        $status = $charge->status;
        $refunded = (int) Refund::where('metadata->licence_charge_id', $charge->id)->where('status', 'settled')->sum('amount_minor');
        if ($refunded >= $charge->amount_minor && $refunded > 0) {
            $status = 'refunded';
        } elseif ($refunded > 0) {
            $status = 'partially_refunded';
        } elseif ($status === 'outstanding' && $bill && $bill->balance_minor === 0) {
            $status = 'settled';
        }

        return [
            'id' => $charge->id, 'stage' => $charge->stage, 'trigger_event' => $charge->trigger_event,
            'configuration_version_id' => $charge->licence_type_version_id, 'revenue_policy_id' => $charge->revenue_policy_id,
            'amount_minor' => $charge->amount_minor, 'paid_minor' => (int) ($bill?->paid_minor ?? 0),
            'balance_minor' => (int) ($bill?->balance_minor ?? 0), 'currency' => $charge->currency_code,
            'status' => $status, 'payment_prerequisite' => $charge->payment_prerequisite,
            'payment_required_before' => $charge->payment_required_before, 'refundable' => (bool) ($charge->refund_policy['refundable'] ?? false),
            'refund_events' => $charge->refund_policy['events'] ?? [],
            'bill_id' => $charge->bill_id, 'recognition_method' => $charge->recognition_method,
            'recognition_trigger' => $charge->accounting_treatment['recognition_rule']['trigger_event'] ?? null,
            'released_minor' => (int) $charge->releases()->sum('amount_minor'),
        ];
    }

    private function postChargeCreditNote(LicenceCharge $charge, Bill $bill, User $user, string $reason, string $kind): JournalEntry
    {
        $snapshot = $charge->accounting_treatment;
        $receivableCode = $snapshot['debit'] ?? null;
        $method = $charge->recognition_method;
        if (! $receivableCode || ! $bill->journal_entry_id) {
            throw new \DomainException('The original charge bill lacks posting provenance required for its linked credit note.');
        }
        $released = (int) $charge->releases()->sum('amount_minor');
        $debitLines = [];
        if ($method === 'deferred') {
            $liability = $snapshot['credit'] ?? null;
            $revenue = $snapshot['revenue_account_code'] ?? null;
            if (! $liability || ! $revenue || $released > $bill->total_minor) {
                throw new \DomainException('Deferred charge account or release provenance is incomplete; credit note blocked.');
            }
            $unrecognised = (int) $bill->total_minor - $released;
            if ($unrecognised > 0) {
                $debitLines[] = ['account_code' => $liability, 'debit_minor' => $unrecognised, 'memo' => 'Release cancelled unearned liability'];
            }
            if ($released > 0) {
                $debitLines[] = ['account_code' => $revenue, 'debit_minor' => $released, 'memo' => 'Reverse previously recognised licence fee'];
            }
        } else {
            $revenue = $snapshot['credit'] ?? null;
            if (! $revenue) {
                throw new \DomainException('Original revenue account snapshot is missing; credit note blocked.');
            }
            $debitLines[] = ['account_code' => $revenue, 'debit_minor' => (int) $bill->total_minor, 'memo' => 'Reverse licence fee'];
        }
        $debitLines[] = ['account_code' => $receivableCode, 'credit_minor' => (int) $bill->total_minor, 'memo' => 'Clear licence fee receivable'];

        return $this->posting->post([
            'journal_type' => 'credit_note', 'entry_date' => now($this->tenants->requireCouncil()->timezone ?: 'Africa/Harare')->toDateString(),
            'description' => 'Licence fee '.$kind.' '.$charge->event_key.' — '.$reason, 'currency_code' => $bill->currency_code,
            'lines' => $debitLines, 'source_type' => 'licence_charge_credit_note', 'source_id' => $charge->id,
            'idempotency_key' => 'licence-charge-credit-note:'.$charge->id, 'created_by' => $user->getKey(),
        ]);
    }

    private function assertReleaseEvent(LicenceCharge $charge, string $triggerEvent): void
    {
        $application = $charge->application;
        if ($triggerEvent === 'application_approved' && ! in_array($application->status, ['approved', 'issued'], true)) {
            throw new \DomainException('The approved application event has not occurred.');
        }
        if ($triggerEvent === 'certificate_issued' && $application->status !== 'issued') {
            throw new \DomainException('The certificate-issued recognition event has not occurred.');
        }
        if ($triggerEvent === 'inspection_passed' && ! $application->inspections()->where('status', 'completed')->where('result', 'like', 'pass%')->exists()) {
            throw new \DomainException('A passing inspection event has not occurred.');
        }
        if ($triggerEvent === 'term_elapsed') {
            $termDays = (int) ($charge->accounting_treatment['recognition_rule']['term_days'] ?? 0);
            $billDate = $charge->bill?->issue_date;
            if ($termDays < 1 || ! $billDate || now($this->tenants->requireCouncil()->timezone ?: 'Africa/Harare')->toDateString() < $billDate->copy()->addDays($termDays)->toDateString()) {
                throw new \DomainException('The approved recognition term has not elapsed; a valid schedule and bill date are required.');
            }
        }
    }

    private function defaultTrigger(string $stage): string
    {
        return match ($stage) {
            'application' => 'application_submitted', 'renewal' => 'renewal_submitted',
            'inspection' => 'inspection_assigned', 'issuance' => 'application_approved',
            default => throw new \DomainException('Unsupported charge stage.'),
        };
    }
}
