<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Contracts\PaymentGateway;
use App\Modules\Revenue\Gateways\PaynowGateway;
use App\Modules\Revenue\Gateways\SandboxGateway;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CashierSession;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentAllocation;
use App\Modules\Revenue\Models\PaymentAttempt;
use App\Modules\Revenue\Models\PaymentIntent;
use App\Modules\Revenue\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payment intent -> attempt -> server-verified settlement -> receipt ->
 * allocation -> journal. A browser return can never create a receipt; only a
 * verified server-side settlement can.
 */
class PaymentService
{
    public function __construct(
        private readonly AllocationService $allocations,
        private readonly PostingService $posting,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function gateway(string $name = 'sandbox'): PaymentGateway
    {
        return match ($name) {
            'sandbox' => new SandboxGateway,
            'paynow' => new PaynowGateway,
            default => throw new \DomainException("Payment gateway [{$name}] has no verified integration. Use the sandbox adapter until API access is documented."),
        };
    }

    public function createIntent(
        CustomerAccount $account,
        int $amountMinor,
        string $channel = 'web',
        string $gateway = 'sandbox',
        ?string $idempotencyKey = null,
        ?User $user = null,
        array $metadata = [],
    ): PaymentIntent {
        if ($amountMinor <= 0) {
            throw new \DomainException('Payment amount must be greater than zero.');
        }

        if ($account->status !== 'active') {
            throw new \DomainException('This account is not active.');
        }

        $this->gateway($gateway);

        $key = $idempotencyKey ?? (string) Str::uuid();

        $existing = PaymentIntent::where('idempotency_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        return DB::connection('tenant')->transaction(function () use ($account, $amountMinor, $channel, $gateway, $key, $metadata) {
            $intent = PaymentIntent::create([
                'reference' => 'PAY-'.strtoupper((string) Str::ulid()),
                'customer_account_id' => $account->id,
                'amount_minor' => $amountMinor,
                'currency_code' => $account->currency_code,
                'channel' => $channel,
                'gateway' => $gateway,
                'status' => 'pending',
                'idempotency_key' => $key,
                'metadata' => $metadata ?: null,
                'expires_at' => now()->addMinutes(30),
            ]);

            PaymentAttempt::create([
                'payment_intent_id' => $intent->id,
                'provider_reference' => 'SBX-'.strtoupper((string) Str::ulid()),
                'status' => 'pending',
                'request_meta' => ['channel' => $channel, 'gateway' => $gateway],
                'attempted_at' => now(),
            ]);

            $this->audit->record(
                action: 'revenue.payment_intent.created',
                subject: $intent,
                after: ['reference' => $intent->reference, 'amount_minor' => $amountMinor, 'channel' => $channel, 'gateway' => $gateway],
                module: 'revenue',
            );

            return $intent;
        });
    }

    /**
     * Apply a server-verified settlement. Safe to call repeatedly: the intent
     * row is locked and an existing verified payment is returned unchanged.
     *
     * @param  array<string, mixed>  $payload
     */
    public function settle(PaymentIntent $intent, string $outcome, string $providerReference, array $payload = []): ?Payment
    {
        if (! in_array($outcome, ['succeeded', 'failed', 'cancelled'], true)) {
            throw new \DomainException('Unknown settlement outcome.');
        }

        return DB::connection('tenant')->transaction(function () use ($intent, $outcome, $providerReference, $payload) {
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);

            if ($locked->status === 'verified') {
                return Payment::where('payment_intent_id', $locked->id)->first();
            }

            if (($payload['amount_minor'] ?? null) !== null && (int) $payload['amount_minor'] !== (int) $locked->amount_minor) {
                throw new \DomainException('Settled amount does not match the payment intent.');
            }

            $attempt = PaymentAttempt::where('payment_intent_id', $locked->id)
                ->orderByDesc('attempted_at')
                ->first();

            if ($outcome !== 'succeeded') {
                $locked->status = $outcome;
                $locked->save();

                if ($attempt) {
                    $attempt->status = $outcome === 'failed' ? 'failed' : 'cancelled';
                    $attempt->response_meta = ['provider_reference' => $providerReference, 'payload' => $payload];
                    $attempt->save();
                }

                $this->audit->record(
                    action: 'revenue.payment_intent.'.$outcome,
                    subject: $locked,
                    after: ['reference' => $locked->reference, 'provider_reference' => $providerReference],
                    module: 'revenue',
                );

                return null;
            }

            $duplicate = Payment::where('external_reference', $providerReference)->first();

            if ($duplicate) {
                return $duplicate;
            }

            $year = now()->format('Y');
            $payment = Payment::create([
                'payment_number' => $this->sequences->next("payment:{$year}", "PMT-{$year}", 6, $year),
                'payment_intent_id' => $locked->id,
                'customer_account_id' => $locked->customer_account_id,
                'amount_minor' => $locked->amount_minor,
                'currency_code' => $locked->currency_code,
                'method' => $locked->channel === 'mobile' ? 'mobile_money' : 'card',
                'status' => 'verified',
                'received_at' => now(),
                'verified_at' => now(),
                'verified_by' => null,
                'verification_source' => 'gateway',
                'external_reference' => $providerReference,
            ]);

            $locked->status = 'verified';
            $locked->save();

            if ($attempt) {
                $attempt->status = 'succeeded';
                $attempt->provider_reference = $providerReference;
                $attempt->response_meta = ['payload' => $payload];
                $attempt->save();
            }

            $receipt = Receipt::create([
                'receipt_number' => $this->sequences->next("receipt:{$year}", "RCPT-{$year}", 6, $year),
                'payment_id' => $payment->id,
                'customer_account_id' => $payment->customer_account_id,
                'amount_minor' => $payment->amount_minor,
                'issued_at' => now(),
                'issued_by' => null,
                'status' => 'issued',
            ]);

            $targetBillId = $locked->metadata['bill_id'] ?? null;
            $this->allocations->allocate($payment, null, is_string($targetBillId) ? $targetBillId : null);

            // If this payment was for a structured payment plan installment under Urban Councils Act s279
            if (! empty($locked->metadata['installment_id'])) {
                $inst = \App\Modules\Revenue\Models\PaymentPlanInstallment::find($locked->metadata['installment_id']);
                if ($inst) {
                    $inst->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'paid_amount_minor' => $payment->amount_minor,
                        'payment_id' => $payment->id,
                    ]);
                    $plan = $inst->plan;
                    if ($plan && $plan->installments()->where('status', '!=', 'paid')->count() === 0) {
                        $plan->update(['status' => 'completed']);
                    }
                }
            }

            $this->postReceiptJournal($payment);

            $this->audit->record(
                action: 'revenue.payment.verified',
                subject: $payment,
                after: [
                    'payment_number' => $payment->payment_number,
                    'receipt_number' => $receipt->receipt_number,
                    'amount_minor' => $payment->amount_minor,
                    'verification_source' => 'gateway',
                ],
                module: 'revenue',
            );

            return $payment->load('receipt', 'allocations');
        });
    }

    public function captureCash(
        CustomerAccount $account,
        int $amountMinor,
        string $method,
        ?string $reference,
        CashierSession $session,
        ?User $user = null,
    ): Payment {
        if ($amountMinor <= 0) {
            throw new \DomainException('Payment amount must be greater than zero.');
        }

        if ($session->status !== 'open') {
            throw new \DomainException('The cashier session is not open.');
        }

        if ($user && $session->cashier_id !== $user->getKey()) {
            throw new \DomainException('This cashier session belongs to another cashier.');
        }

        if (! in_array($method, ['cash', 'card', 'mobile_money', 'bank_transfer', 'cheque'], true)) {
            throw new \DomainException('Unsupported tender type.');
        }

        return DB::connection('tenant')->transaction(function () use ($account, $amountMinor, $method, $reference, $session, $user) {
            $year = now()->format('Y');

            $payment = Payment::create([
                'payment_number' => $this->sequences->next("payment:{$year}", "PMT-{$year}", 6, $year),
                'customer_account_id' => $account->id,
                'amount_minor' => $amountMinor,
                'currency_code' => $account->currency_code,
                'method' => $method,
                'status' => 'verified',
                'received_at' => now(),
                'verified_at' => now(),
                'verified_by' => $user?->getKey(),
                'verification_source' => 'cashier',
                'external_reference' => $reference,
                'cashier_session_id' => $session->id,
                'received_by' => $user?->getKey(),
            ]);

            Receipt::create([
                'receipt_number' => $this->sequences->next("receipt:{$year}", "RCPT-{$year}", 6, $year),
                'payment_id' => $payment->id,
                'customer_account_id' => $account->id,
                'amount_minor' => $amountMinor,
                'issued_at' => now(),
                'issued_by' => $user?->getKey(),
                'status' => 'issued',
            ]);

            $this->allocations->allocate($payment, $user);
            $this->postReceiptJournal($payment, $user);

            $this->audit->record(
                action: 'revenue.payment.captured',
                subject: $payment,
                after: ['payment_number' => $payment->payment_number, 'amount_minor' => $amountMinor, 'method' => $method],
                module: 'revenue',
            );

            return $payment->load('receipt', 'allocations');
        });
    }

    private function postReceiptJournal(Payment $payment, ?User $user = null): void
    {
        $receipts = config('accounting.receipts');
        $cashAccount = in_array($payment->method, ['cash', 'cheque'], true) ? $receipts['cash'] : $receipts['bank'];

        $credits = [];
        $allocated = 0;

        $allocations = PaymentAllocation::where('payment_id', $payment->id)
            ->whereNull('reversed_at')
            ->get();

        foreach ($allocations as $allocation) {
            $bill = Bill::find($allocation->bill_id);
            $chargeType = $bill?->service_type ?? 'rates';
            $receivable = $bill?->calculation_snapshot['revenue_policies'][0]['debit'] ?? config("accounting.receivables.{$chargeType}", config('accounting.receivables.rates'));
            $credits[$receivable] = ($credits[$receivable] ?? 0) + (int) $allocation->amount_minor;
            $allocated += (int) $allocation->amount_minor;
        }

        $unallocated = (int) $payment->amount_minor - $allocated;

        if ($unallocated > 0) {
            $credits[$receipts['unapplied']] = ($credits[$receipts['unapplied']] ?? 0) + $unallocated;
        }

        $lines = [['account_code' => $cashAccount, 'debit_minor' => (int) $payment->amount_minor, 'memo' => "Payment {$payment->payment_number}"]];

        foreach ($credits as $code => $amount) {
            $lines[] = ['account_code' => $code, 'credit_minor' => $amount, 'memo' => "Payment {$payment->payment_number}"];
        }

        $this->posting->post([
            'journal_type' => 'receipt',
            'entry_date' => $payment->received_at->toDateString(),
            'description' => "Receipt for payment {$payment->payment_number}",
            'currency_code' => $payment->currency_code,
            'lines' => $lines,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'idempotency_key' => 'payment:'.$payment->id,
            'created_by' => $user?->getKey(),
        ]);
    }
}
