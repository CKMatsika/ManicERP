<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\PaymentPlan;
use App\Modules\Revenue\Services\CreditControlService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditControlController extends Controller
{
    public function __construct(
        private readonly CreditControlService $creditControl,
    ) {}

    public function index(Request $request)
    {
        $aging = $this->creditControl->getAgingSummary();

        $plans = PaymentPlan::with(['account.property', 'installments'])
            ->latest()
            ->get()
            ->map(fn ($p) => $this->creditControl->transformPlan($p));

        $debtorAccounts = CustomerAccount::with('property')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($acc) => $acc->outstandingBalanceMinor() > 0)
            ->map(fn ($acc) => [
                'id' => $acc->id,
                'account_number' => $acc->account_number,
                'property' => $acc->property?->parcel_number,
                'address' => $acc->property?->address_line,
                'outstanding_usd' => number_format($acc->outstandingBalanceMinor() / 100, 2),
                'currency' => $acc->currency_code,
                'has_active_plan' => PaymentPlan::where('customer_account_id', $acc->id)->where('status', 'active')->exists(),
            ])
            ->values();

        return Inertia::render('Revenue/CreditControl', [
            'aging' => $aging,
            'plans' => $plans,
            'debtors' => $debtorAccounts,
        ]);
    }
}

