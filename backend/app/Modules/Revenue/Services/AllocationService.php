<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\Bill;
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
    public function allocate(Payment $payment, ?User $user = null): int
    {
        if ($payment->status !== 'verified') {
            throw new \DomainException('Only verified payments may be allocated.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $user) {
            $remaining = $payment->unallocatedMinor();

            if ($remaining <= 0) {
                return 0;
            }

            $bills = Bill::query()
                ->where('customer_account_id', $payment->customer_account_id)
                ->whereIn('status', ['issued', 'partially_paid'])
                ->where('balance_minor', '>', 0)
                ->orderBy('due_date')
                ->orderBy('issue_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allocated = 0;

            foreach ($bills as $bill) {
                if ($remaining <= 0) {
                    break;
                }

                $amount = min($remaining, (int) $bill->balance_minor);

                if ($amount <= 0) {
                    continue;
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'amount_minor' => $amount,
                    'allocated_at' => now(),
                    'allocated_by' => $user?->getKey(),
                ]);

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

        DB::connection('tenant')->transaction(function () use ($allocation, $reason) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($allocation->bill_id);

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
}
