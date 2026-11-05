<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\CashierSession;
use App\Modules\Revenue\Models\Payment;
use Illuminate\Support\Facades\DB;

class CashierService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function open(User $cashier, int $openingFloatMinor = 0): CashierSession
    {
        $existing = CashierSession::where('cashier_id', $cashier->getKey())
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $existing;
        }

        $date = now()->format('Ymd');
        $session = CashierSession::create([
            'session_number' => $this->sequences->next("cashier:{$date}", 'CS', 4),
            'cashier_id' => $cashier->getKey(),
            'opened_at' => now(),
            'status' => 'open',
            'opening_float_minor' => $openingFloatMinor,
        ]);

        $this->audit->record(
            action: 'revenue.cashier.session_opened',
            subject: $session,
            after: ['session_number' => $session->session_number, 'float_minor' => $openingFloatMinor],
            module: 'revenue',
        );

        return $session;
    }

    /** @return array<string, mixed> */
    public function close(CashierSession $session, int $countedCashMinor, ?User $user = null): array
    {
        if ($session->status !== 'open') {
            throw new \DomainException('This cashier session is already closed.');
        }

        return DB::connection('tenant')->transaction(function () use ($session, $countedCashMinor, $user) {
            $payments = Payment::where('cashier_session_id', $session->id)
                ->where('status', 'verified')
                ->get();

            $tenderTotals = [];

            foreach ($payments as $payment) {
                $tenderTotals[$payment->method] = ($tenderTotals[$payment->method] ?? 0) + (int) $payment->amount_minor;
            }

            $expectedCash = (int) ($tenderTotals['cash'] ?? 0) + (int) $session->opening_float_minor;
            $variance = $countedCashMinor - $expectedCash;

            $session->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $user?->getKey(),
                'expected_cash_minor' => $expectedCash,
                'counted_cash_minor' => $countedCashMinor,
                'variance_minor' => $variance,
                'tender_totals' => $tenderTotals,
            ])->save();

            $this->audit->record(
                action: 'revenue.cashier.session_closed',
                subject: $session,
                after: [
                    'session_number' => $session->session_number,
                    'expected_cash_minor' => $expectedCash,
                    'counted_cash_minor' => $countedCashMinor,
                    'variance_minor' => $variance,
                    'tender_totals' => $tenderTotals,
                ],
                module: 'revenue',
            );

            return [
                'session' => $session->fresh(),
                'expected_cash_minor' => $expectedCash,
                'counted_cash_minor' => $countedCashMinor,
                'variance_minor' => $variance,
                'tender_totals' => $tenderTotals,
                'payment_count' => $payments->count(),
            ];
        });
    }
}
