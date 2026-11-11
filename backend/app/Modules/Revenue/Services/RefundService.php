<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PeriodService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Contracts\RefundGateway;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\Receipt;
use App\Modules\Revenue\Models\Refund;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Refund workflow separate from payment reversal and allocation reversal.
 *
 * A refund never deletes the original receipt. Refunding previously allocated
 * money requires an explicit allocation reversal first, so an invoice balance
 * is never silently restored. Settlement uses an adapter; only the labelled
 * sandbox adapter exists.
 */
class RefundService
{
    public function __construct(
        private readonly RefundGateway $gateway,
        private readonly PostingService $posting,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
        private readonly TenantManager $tenants,
        private readonly PermissionService $permissions,
    ) {}

    public function availableForAccount(string $accountId, ?string $paymentId = null, ?string $excludeRefundId = null): int
    {
        $credits = (int) AccountCredit::where('customer_account_id', $accountId)
            ->where('type', 'unapplied')
            ->where('status', 'open')
            ->sum('remaining_minor');

        if ($paymentId === null) {
            $reserved = (int) Refund::where('customer_account_id', $accountId)
                ->whereNull('payment_id')
                ->whereIn('status', ['requested', 'approved', 'settling', 'failed'])
                ->when($excludeRefundId, fn ($query) => $query->where('id', '!=', $excludeRefundId))
                ->sum('amount_minor');

            return max(0, $credits - $reserved);
        }

        $payment = Payment::where('customer_account_id', $accountId)->findOrFail($paymentId);
        if (! $payment->verified()) {
            return 0;
        }
        $alreadyRefunded = (int) Refund::where('payment_id', $paymentId)
            ->whereIn('status', ['requested', 'approved', 'settling', 'failed'])
            ->when($excludeRefundId, fn ($query) => $query->where('id', '!=', $excludeRefundId))
            ->sum('amount_minor');

        return max(0, $payment->unallocatedMinor() - $alreadyRefunded);
    }

    public function request(
        string $accountId,
        int $amountMinor,
        string $type = 'unapplied',
        ?string $paymentId = null,
        ?string $reason = null,
        ?User $requester = null,
        ?string $idempotencyKey = null,
        ?string $customerNote = null,
        array $metadata = [],
    ): Refund {
        if ($amountMinor <= 0) {
            throw new \DomainException('Refund amount must be greater than zero.');
        }

        if ($type !== 'unapplied') {
            throw new \DomainException('Only unapplied funds are eligible. Deposits require a separately approved release policy.');
        }
        $this->authorize($requester, 'revenue.refund.request');

        return DB::connection('tenant')->transaction(function () use ($accountId, $amountMinor, $type, $paymentId, $reason, $requester, $idempotencyKey, $customerNote, $metadata) {
            CustomerAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();
            if ($paymentId) {
                Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            }

            if ($idempotencyKey) {
                $existing = Refund::where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    if ($existing->customer_account_id !== $accountId || $existing->payment_id !== $paymentId || $existing->amount_minor !== $amountMinor) {
                        throw new \DomainException('Idempotency key already belongs to a different refund request.');
                    }

                    return $existing;
                }
            }

            $account = CustomerAccount::findOrFail($accountId);

            if ($account->status !== 'active') {
                throw new \DomainException('Refunds are not available for an inactive account.');
            }

            $available = $this->availableForAccount($accountId, $paymentId);

            if ($amountMinor > $available) {
                throw new \DomainException("Refund exceeds the available unapplied balance of {$available} minor units. Reverse allocations separately if allocated money is to be refunded.");
            }

            $year = now()->format('Y');
            $refundNumber = $this->sequences->next("refund:{$year}", "REF-{$year}", 5, $year);

            $refund = Refund::create([
                'refund_number' => $refundNumber,
                'customer_account_id' => $account->id,
                'payment_id' => $paymentId,
                'type' => $type,
                'amount_minor' => $amountMinor,
                'status' => Refund::STATUS_REQUESTED,
                'reason' => $reason ?? 'Customer refund request',
                'requested_by' => $requester?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'customer_note' => $customerNote ?? 'Refund requested and awaiting council review.',
                'metadata' => ['gateway' => $this->gateway->name(), 'sandbox' => $this->gateway->isSandbox(), ...$metadata],
            ]);

            $this->audit->record(
                action: 'revenue.refund.requested',
                subject: $refund,
                after: ['refund' => $refundNumber, 'amount_minor' => $amountMinor, 'type' => $type],
                module: 'revenue',
            );

            return $refund;
        });
    }

    public function approve(Refund $refund, User $approver, ?string $note = null): Refund
    {
        $this->authorize($approver, 'revenue.refund.approve');

        return DB::connection('tenant')->transaction(function () use ($refund, $approver, $note) {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status !== Refund::STATUS_REQUESTED) {
                throw new \DomainException("Only a requested refund can be approved; current status is [{$locked->status}].");
            }

            if ($locked->requested_by === $approver->getKey()) {
                throw new \DomainException('Separation of duties: the requester cannot approve the same refund.');
            }

            $threshold = (int) config('manicerp.refunds.approval_threshold_minor', 100_000);

            if ($locked->amount_minor > $threshold && ! $this->permissions->allows($approver, 'revenue.refund.approve_high')) {
                throw new \DomainException('This refund exceeds the approver threshold and needs a higher approval permission.');
            }

            $locked->forceFill([
                'status' => Refund::STATUS_APPROVED,
                'approved_amount_minor' => $locked->amount_minor,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
                'customer_note' => $note ?? 'Refund approved; settlement is pending.',
            ])->save();

            $this->audit->record(
                action: 'revenue.refund.approved',
                subject: $locked,
                before: ['status' => Refund::STATUS_REQUESTED],
                after: ['status' => Refund::STATUS_APPROVED, 'amount_minor' => $locked->approved_amount_minor],
                module: 'revenue',
            );

            return $locked;
        });
    }

    public function reject(Refund $refund, User $reviewer, string $reason): Refund
    {
        $this->authorize($reviewer, 'revenue.refund.approve');
        if (trim($reason) === '') {
            throw new \DomainException('A rejection reason is required.');
        }

        return DB::connection('tenant')->transaction(function () use ($refund, $reviewer, $reason) {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if (! in_array($locked->status, [Refund::STATUS_REQUESTED, Refund::STATUS_APPROVED], true)) {
                throw new \DomainException('This refund can no longer be rejected.');
            }

            if ($locked->requested_by === $reviewer->getKey()) {
                throw new \DomainException('Separation of duties: the requester cannot reject the same refund.');
            }

            $locked->forceFill([
                'status' => Refund::STATUS_REJECTED,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'customer_note' => $reason,
            ])->save();

            $this->audit->record(
                action: 'revenue.refund.rejected',
                subject: $locked,
                after: ['reason' => $reason],
                module: 'revenue',
            );

            return $locked;
        });
    }

    public function settle(Refund $refund, User $settler): Refund
    {
        $this->authorize($settler, 'revenue.refund.settle');
        if (! $this->gateway->isSandbox()) {
            throw new \DomainException('Real gateway refunds remain unavailable pending verified provider integration.');
        }

        return DB::connection('tenant')->transaction(function () use ($refund, $settler) {
            CustomerAccount::whereKey($refund->customer_account_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->payment_id) {
                Payment::whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            }
            if ($refund->status === Refund::STATUS_SETTLED) {
                return $refund;
            }

            if (! in_array($refund->status, [Refund::STATUS_APPROVED, Refund::STATUS_FAILED], true)) {
                throw new \DomainException('Only an approved or retryable failed refund can be settled.');
            }
            if ($refund->amount_minor > $this->availableForAccount($refund->customer_account_id, $refund->payment_id, $refund->id)) {
                throw new \DomainException('Refund exceeds the remaining available unapplied funds.');
            }
            app(PeriodService::class)->forPosting(now()->toDateString());

            try {
                $result = $this->gateway->settle($refund);
            } catch (Throwable $exception) {
                $refund->forceFill([
                    'status' => Refund::STATUS_FAILED,
                    'customer_note' => 'Refund settlement failed and will be retried by the council.',
                ])->save();

                $this->audit->record(
                    action: 'revenue.refund.settlement_failed',
                    subject: $refund,
                    metadata: ['error' => $exception->getMessage()],
                    module: 'revenue',
                );

                return $refund;
            }

            if (($result['status'] ?? 'failed') !== 'settled') {
                $refund->forceFill(['status' => Refund::STATUS_FAILED, 'customer_note' => 'Sandbox settlement not confirmed; eligible for retry.'])->save();

                return $refund;
            }

            return DB::connection('tenant')->transaction(function () use ($refund, $settler, $result) {
                $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

                if ($locked->status === Refund::STATUS_SETTLED) {
                    return $locked;
                }

                if (! in_array($locked->status, [Refund::STATUS_APPROVED, Refund::STATUS_FAILED], true)) {
                    throw new \DomainException('This refund is no longer eligible for settlement.');
                }

                $available = $this->availableForAccount($locked->customer_account_id, $locked->payment_id, $locked->id);

                if ($locked->amount_minor > $available && $locked->payment_id !== null) {
                    throw new \DomainException('The payment no longer has enough unapplied balance for this refund.');
                }

                if ($locked->payment_id === null) {
                    $this->consumeCredits($locked);
                }
                $journal = $this->postRefundJournal($locked, $settler);

                $locked->forceFill([
                    'status' => Refund::STATUS_SETTLED,
                    'settled_amount_minor' => $locked->amount_minor,
                    'settled_at' => now(),
                    'paid_at' => now(),
                    'sandbox_reference' => $result['reference'] ?? null,
                    'journal_entry_id' => $journal->id,
                    'customer_note' => 'Refund settled. The original receipt remains valid and unchanged.',
                ])->save();

                $this->audit->record(
                    action: 'revenue.refund.settled',
                    subject: $locked,
                    after: [
                        'refund' => $locked->refund_number,
                        'amount_minor' => $locked->amount_minor,
                        'reference' => $locked->sandbox_reference,
                        'journal_entry_id' => $journal->id,
                    ],
                    module: 'revenue',
                );

                return $locked;
            });
        });
    }

    private function consumeCredits(Refund $refund): void
    {
        $remaining = $refund->amount_minor;

        $credits = AccountCredit::where('customer_account_id', $refund->customer_account_id)
            ->where('type', 'unapplied')
            ->where('status', 'open')
            ->where('remaining_minor', '>', 0)
            ->orderBy('effective_date')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $use = min($remaining, (int) $credit->remaining_minor);
            $credit->remaining_minor -= $use;
            $credit->status = $credit->remaining_minor === 0 ? 'refunded' : 'open';
            $credit->save();
            $remaining -= $use;
        }
    }

    private function authorize(?User $user, string $permission): void
    {
        if (! $this->permissions->allows($user, $permission)) {
            throw new AuthorizationException('Refund permission required.');
        }
    }

    private function postRefundJournal(Refund $refund, User $settler): JournalEntry
    {
        $unapplied = config('accounting.receipts.unapplied');
        $bank = config('accounting.receipts.bank');

        return $this->posting->post([
            'journal_type' => 'refund',
            'entry_date' => now()->toDateString(),
            'description' => "Refund {$refund->refund_number}",
            'currency_code' => $refund->customer_account_id ? CustomerAccount::find($refund->customer_account_id)?->currency_code : 'USD',
            'lines' => [
                ['account_code' => $unapplied, 'debit_minor' => $refund->amount_minor, 'memo' => 'Refund of unapplied funds'],
                ['account_code' => $bank, 'credit_minor' => $refund->amount_minor, 'memo' => 'Sandbox refund settlement'],
            ],
            'source_type' => 'refund',
            'source_id' => $refund->id,
            'idempotency_key' => 'refund:'.$refund->id,
            'created_by' => $settler->getKey(),
        ]);
    }

    /**
     * Read-only customer-visible status, with receipts retained.
     *
     * @return array<string, mixed>
     */
    public function status(Refund $refund): array
    {
        $receipt = $refund->payment_id
            ? Receipt::where('payment_id', $refund->payment_id)->first()
            : null;

        return [
            'refund_number' => $refund->refund_number,
            'amount_minor' => $refund->amount_minor,
            'status' => $refund->status,
            'customer_note' => $refund->customer_note,
            'original_receipt' => $receipt?->receipt_number,
            'original_receipt_status' => $receipt?->status,
            'sandbox_reference' => $refund->sandbox_reference,
            'sandbox' => (bool) ($refund->metadata['sandbox'] ?? true),
        ];
    }
}
