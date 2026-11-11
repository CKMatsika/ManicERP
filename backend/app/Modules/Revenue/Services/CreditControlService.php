<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\PaymentPlan;
use App\Modules\Revenue\Models\PaymentPlanInstallment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditControlService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function getAgingSummary(?string $ward = null): array
    {
        $bills = Bill::where('balance_minor', '>', 0)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->get();

        $now = now();
        $current = 0;
        $thirtyDays = 0;
        $sixtyDays = 0;
        $ninetyPlus = 0;

        foreach ($bills as $b) {
            $days = $b->due_date ? $b->due_date->diffInDays($now, false) : 0;
            if ($days <= 0) {
                $current += $b->balance_minor;
            } elseif ($days <= 30) {
                $thirtyDays += $b->balance_minor;
            } elseif ($days <= 60) {
                $sixtyDays += $b->balance_minor;
            } else {
                $ninetyPlus += $b->balance_minor;
            }
        }

        $total = $current + $thirtyDays + $sixtyDays + $ninetyPlus;

        return [
            'currency' => 'USD',
            'current_minor' => $current,
            'current_usd' => number_format($current / 100, 2),
            'thirty_days_minor' => $thirtyDays,
            'thirty_days_usd' => number_format($thirtyDays / 100, 2),
            'sixty_days_minor' => $sixtyDays,
            'sixty_days_usd' => number_format($sixtyDays / 100, 2),
            'ninety_plus_minor' => $ninetyPlus,
            'ninety_plus_usd' => number_format($ninetyPlus / 100, 2),
            'total_arrears_minor' => $total,
            'total_arrears_usd' => number_format($total / 100, 2),
            'total_debtor_bills' => $bills->count(),
            'active_plans_count' => PaymentPlan::where('status', 'active')->count(),
            'protected_accounts_count' => PaymentPlan::where('status', 'active')->where('disconnection_immunity', true)->count(),
        ];
    }

    public function calculatePlanOptions(int $arrearsMinor, string $currency = 'USD'): array
    {
        // Under Resolution FC/89/2026, estimated 20% of aged arrears represents interest,
        // of which 50% is waived upon entering a structured payment plan.
        $estimatedInterestMinor = (int) ($arrearsMinor * 0.20);
        $waiverDiscountMinor = (int) ($estimatedInterestMinor * 0.50);
        $netArrearsMinor = max($arrearsMinor - $waiverDiscountMinor, 0);

        $durations = [3, 6, 12];
        $options = [];

        foreach ($durations as $months) {
            // Down payment is 10%
            $downPayment = (int) ($netArrearsMinor * 0.10);
            $remaining = $netArrearsMinor - $downPayment;
            $monthly = (int) ceil($remaining / $months);

            $options[] = [
                'months' => $months,
                'total_arrears_usd' => number_format($arrearsMinor / 100, 2),
                'interest_waiver_usd' => number_format($waiverDiscountMinor / 100, 2),
                'net_payable_usd' => number_format($netArrearsMinor / 100, 2),
                'down_payment_usd' => number_format($downPayment / 100, 2),
                'monthly_installment_usd' => number_format($monthly / 100, 2),
                'monthly_installment_minor' => $monthly,
                'down_payment_minor' => $downPayment,
                'resolution_code' => 'FC/89/2026',
                'resolution_title' => 'Council 50% Interest Amnesty Resolution',
            ];
        }

        return [
            'arrears_minor' => $arrearsMinor,
            'currency' => $currency,
            'options' => $options,
        ];
    }

    public function createPlan(CustomerAccount $account, array $data, ?User $user = null): PaymentPlan
    {
        $arrearsMinor = $account->outstandingBalanceMinor();
        if ($arrearsMinor <= 0) {
            throw new \DomainException('Account has no outstanding arrears to place on a payment plan.');
        }

        $months = (int) ($data['months'] ?? 6);
        if (! in_array($months, [3, 6, 12], true)) {
            $months = 6;
        }

        $options = $this->calculatePlanOptions($arrearsMinor, $account->currency_code);
        $chosenOption = collect($options['options'])->firstWhere('months', $months);

        return DB::connection('tenant')->transaction(function () use ($account, $data, $user, $months, $arrearsMinor, $chosenOption) {
            // Cancel existing active plans
            PaymentPlan::where('customer_account_id', $account->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $now = now();
            $planRef = 'PP-' . date('Y') . '-' . strtoupper(Str::random(6));

            $waiverMinor = (int) (floatval($chosenOption['interest_waiver_usd']) * 100);
            $netPayableMinor = (int) (floatval($chosenOption['net_payable_usd']) * 100);
            $downPaymentMinor = $chosenOption['down_payment_minor'];
            $monthlyMinor = $chosenOption['monthly_installment_minor'];

            $plan = PaymentPlan::create([
                'plan_number' => $planRef,
                'customer_account_id' => $account->id,
                'user_id' => $user?->id,
                'total_arrears_minor' => $arrearsMinor,
                'interest_waived_minor' => $waiverMinor,
                'net_payable_minor' => $netPayableMinor,
                'down_payment_minor' => $downPaymentMinor,
                'installment_count' => $months,
                'monthly_installment_minor' => $monthlyMinor,
                'currency_code' => $account->currency_code,
                'start_date' => $now->toDateString(),
                'end_date' => $now->copy()->addMonths($months)->toDateString(),
                'paid_installments' => 0,
                'total_paid_minor' => 0,
                'disconnection_immunity' => true,
                'resolution_ref' => 'FC/89/2026',
                'status' => 'active',
                'notes' => $data['notes'] ?? 'Section 279 Debt Acknowledgment & Payment Agreement.',
            ]);

            // Generate monthly installments
            for ($i = 1; $i <= $months; $i++) {
                PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'installment_number' => $i,
                    'due_date' => $now->copy()->addMonths($i)->toDateString(),
                    'amount_minor' => $monthlyMinor,
                    'paid_amount_minor' => 0,
                    'currency_code' => $account->currency_code,
                    'status' => 'pending',
                ]);
            }

            $this->audit->record(
                action: 'credit_control.plan_created',
                after: [
                    'plan_number' => $planRef,
                    'account' => $account->account_number,
                    'months' => $months,
                    'monthly_usd' => $chosenOption['monthly_installment_usd'],
                    'waiver_usd' => $chosenOption['interest_waiver_usd'],
                ],
                module: 'revenue'
            );

            return $plan;
        });
    }

    public function recordInstallmentPayment(
        PaymentPlanInstallment $installment,
        int $amountMinor,
        string $reference
    ): PaymentPlanInstallment {
        return DB::connection('tenant')->transaction(function () use ($installment, $amountMinor, $reference) {
            $installment->update([
                'paid_amount_minor' => $installment->paid_amount_minor + $amountMinor,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $reference,
            ]);

            $plan = $installment->plan;
            $plan->increment('paid_installments');
            $plan->increment('total_paid_minor', $amountMinor);

            if ($plan->paid_installments >= $plan->installment_count) {
                $plan->update(['status' => 'completed']);
            }

            return $installment->fresh();
        });
    }

    public function activePlanForAccount(CustomerAccount $account): ?PaymentPlan
    {
        return PaymentPlan::with('installments')
            ->where('customer_account_id', $account->id)
            ->where('status', 'active')
            ->first();
    }

    public function transformPlan(PaymentPlan $plan): array
    {
        $next = $plan->nextPendingInstallment();

        return [
            'id' => $plan->id,
            'plan_number' => $plan->plan_number,
            'account_number' => $plan->account?->account_number,
            'total_arrears_usd' => number_format($plan->total_arrears_minor / 100, 2),
            'interest_waived_usd' => number_format($plan->interest_waived_minor / 100, 2),
            'net_payable_usd' => number_format($plan->net_payable_minor / 100, 2),
            'monthly_installment_usd' => number_format($plan->monthly_installment_minor / 100, 2),
            'installment_count' => $plan->installment_count,
            'paid_installments' => $plan->paid_installments,
            'total_paid_usd' => number_format($plan->total_paid_minor / 100, 2),
            'disconnection_immunity' => $plan->disconnection_immunity,
            'resolution_ref' => $plan->resolution_ref,
            'start_date' => $plan->start_date?->toDateString(),
            'end_date' => $plan->end_date?->toDateString(),
            'status' => $plan->status,
            'next_installment' => $next ? [
                'id' => $next->id,
                'installment_number' => $next->installment_number,
                'due_date' => $next->due_date?->toDateString(),
                'amount_usd' => number_format($next->amount_minor / 100, 2),
                'status' => $next->status,
            ] : null,
            'installments' => $plan->installments->map(fn ($ins) => [
                'id' => $ins->id,
                'installment_number' => $ins->installment_number,
                'due_date' => $ins->due_date?->toDateString(),
                'amount_usd' => number_format($ins->amount_minor / 100, 2),
                'paid_usd' => number_format($ins->paid_amount_minor / 100, 2),
                'status' => $ins->status,
                'paid_at' => $ins->paid_at?->toIso8601String(),
            ])->all(),
        ];
    }
}

