<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\CashierSession;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Services\CashierService;
use App\Modules\Revenue\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashierController extends Controller
{
    public function index(Request $request)
    {
        $session = CashierSession::where('cashier_id', $request->user()->getKey())
            ->where('status', 'open')
            ->first();

        $payments = $session
            ? Payment::with('receipt', 'account')
                ->where('cashier_session_id', $session->id)
                ->orderByDesc('received_at')
                ->get()
                ->map(fn (Payment $payment) => [
                    'number' => $payment->payment_number,
                    'receipt' => $payment->receipt?->receipt_number,
                    'account' => $payment->account?->account_number,
                    'amount_minor' => $payment->amount_minor,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at?->toIso8601String(),
                ])
            : collect();

        return Inertia::render('Revenue/Cashier', [
            'session' => $session ? [
                'id' => $session->id,
                'number' => $session->session_number,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'opening_float_minor' => $session->opening_float_minor,
            ] : null,
            'payments' => $payments,
            'cashTotalMinor' => $payments->where('method', 'cash')->sum('amount_minor'),
        ]);
    }

    public function open(Request $request, CashierService $cashier)
    {
        $data = $request->validate(['opening_float' => ['nullable', 'string', 'max:20']]);

        $float = $data['opening_float'] ?? '0';
        $session = $cashier->open($request->user(), Money::fromDecimal($float === '' ? '0' : $float)->minor);

        return back()->with('success', "Cashier session {$session->session_number} opened.");
    }

    public function capture(Request $request, PaymentService $payments, CashierService $cashier)
    {
        $data = $request->validate([
            'account_number' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['required', 'in:cash,card,mobile_money,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:96'],
        ]);

        $session = CashierSession::where('cashier_id', $request->user()->getKey())
            ->where('status', 'open')
            ->first();

        if (! $session) {
            return back()->with('error', 'Open a cashier session before capturing payments.');
        }

        $account = CustomerAccount::where('account_number', $data['account_number'])->first();

        if (! $account) {
            return back()->with('error', 'No account matches that account number.');
        }

        $amount = Money::fromDecimal($data['amount'], $account->currency_code);

        if (! $amount->isPositive()) {
            return back()->with('error', 'The payment amount must be greater than zero.');
        }

        try {
            $payment = $payments->captureCash($account, $amount->minor, $data['method'], $data['reference'] ?? null, $session, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Receipt {$payment->receipt?->receipt_number} issued for {$amount->format()}.");
    }

    public function close(Request $request, CashierService $cashier)
    {
        $data = $request->validate(['counted_cash' => ['required', 'string', 'max:20']]);

        $session = CashierSession::where('cashier_id', $request->user()->getKey())
            ->where('status', 'open')
            ->first();

        if (! $session) {
            return back()->with('error', 'There is no open cashier session.');
        }

        $counted = Money::fromDecimal($data['counted_cash'] === '' ? '0' : $data['counted_cash']);

        try {
            $result = $cashier->close($session, $counted->minor, $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $variance = Money::fromMinor($result['variance_minor']);

        return back()->with(
            'success',
            'Session closed. Expected '.Money::fromMinor($result['expected_cash_minor'])->format()
            .', counted '.$counted->format().', variance '.$variance->format().'.',
        );
    }
}
