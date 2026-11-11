<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class AllocationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NumberSequenceService $sequences,
    ) {}

    /**
     * Allocate a verified payment oldest-first across open bills. Returns the
     * total allocated in minor units.
     */
    public function allocate(Payment $payment, ?User $user = null, ?string $billId = null): int
    {
        if ($payment->status !== 'verified') {
            throw new \DomainException('Only verified payments may be allocated.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $user, $billId) {
            CustomerAccount::whereKey($payment->customer_account_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $remaining = app(RefundService::class)->availableForAccount($payment->customer_account_id, $payment->id);

            if ($remaining <= 0) {
                return 0;
            }

            $bills = Bill::query()
                ->where('customer_account_id', $payment->customer_account_id)
                ->when($billId !== null, fn ($query) => $query->whereKey($billId))
                ->whereIn('status', ['issued', 'partially_paid'])
                ->where('balance_minor', '>', 0)
                ->orderBy('due_date')
                ->orderBy('issue_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($billId !== null && $bills->isEmpty() && Bill::whereKey($billId)->exists()) {
                throw new \DomainException('The requested payment bill does not belong to this customer account or is not payable.');
            }

            $allocated = 0;

            foreach ($bills as $bill) {
                if ($remaining <= 0) {
                    break;
                }

                $amount = min($remaining, (int) $bill->balance_minor);

                if ($amount <= 0) {
                    continue;
                }

                $allocation = PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'amount_minor' => $amount,
                    'allocated_at' => now(),
                    'allocated_by' => $user?->getKey(),
                ]);

                if (JournalEntry::where('source_type', 'payment')->where('source_id', $payment->id)->exists()) {
                    $this->postAllocationTransfer($allocation, $bill, false, $user);
                }

                $bill->paid_minor += $amount;
                $bill->balance_minor -= $amount;
                $bill->status = $bill->balance_minor === 0 ? 'paid' : 'partially_paid';
                $bill->save();

                $allocated += $amount;
                $remaining -= $amount;
            }

            $this->audit->record(
                action: 'revenue.payment.allocated',
                subject: $payment,
                after: ['allocated_minor' => $allocated, 'unallocated_minor' => $remaining],
                module: 'revenue',
            );

            return $allocated;
        });
    }

    public function reverseAllocation(PaymentAllocation $allocation, string $reason, ?User $user = null): void
    {
        if ($allocation->reversed_at) {
            throw new \DomainException('This allocation has already been reversed.');
        }

        DB::connection('tenant')->transaction(function () use ($allocation, $reason, $user) {
            $payment = Payment::findOrFail($allocation->payment_id);
            CustomerAccount::whereKey($payment->customer_account_id)->lockForUpdate()->firstOrFail();
            Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $allocation = PaymentAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($allocation->reversed_at) {
                throw new \DomainException('This allocation has already been reversed.');
            }
            $bill = Bill::query()->lockForUpdate()->findOrFail($allocation->bill_id);
            $this->postAllocationTransfer($allocation, $bill, true, $user);

            $bill->paid_minor = max(0, $bill->paid_minor - $allocation->amount_minor);
            $bill->balance_minor += $allocation->amount_minor;
            $bill->status = $bill->paid_minor > 0 ? 'partially_paid' : 'issued';
            $bill->save();

            $allocation->reversed_at = now();
            $allocation->reversal_reason = $reason;
            $allocation->save();

            $this->audit->record(
                action: 'revenue.payment.allocation_reversed',
                subject: $allocation,
                after: ['reason' => $reason],
                module: 'revenue',
            );
        });
    }

    private function postAllocationTransfer(PaymentAllocation $allocation, Bill $bill, bool $reverse, ?User $user): void
    {
        $receivable = $bill->calculation_snapshot['revenue_policies'][0]['debit'] ?? config('accounting.receivables.'.$bill->service_type);
        $unapplied = config('accounting.receipts.unapplied');
        $event = $reverse ? 'allocation_reversal' : 'allocation';
        app(PostingService::class)->post([
            'journal_type' => $event, 'entry_date' => now()->toDateString(),
            'description' => ($reverse ? 'Reverse' : 'Apply').' allocation '.$allocation->id,
            'currency_code' => $bill->currency_code,
            'source_type' => $event, 'source_id' => $allocation->id,
            'idempotency_key' => $event.':'.$allocation->id, 'created_by' => $user?->id,
            'lines' => [
                ['account_code' => $reverse ? $receivable : $unapplied, 'debit_minor' => $allocation->amount_minor],
                ['account_code' => $reverse ? $unapplied : $receivable, 'credit_minor' => $allocation->amount_minor],
            ],
        ]);
    }
}
