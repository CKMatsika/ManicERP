<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Revenue\Models\PaymentPlanInstallment;
use App\Modules\Revenue\Services\CreditControlService;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Http\Request;

class CreditControlApiController extends Controller
{
    public function __construct(
        private readonly CreditControlService $creditControl,
        private readonly AccountLinkService $links,
        private readonly PaymentService $payments,
    ) {}

    public function options(Request $request)
    {
        $link = $this->links->linksFor($request->user())->first();
        if (! $link || ! $link->account) {
            return response()->json(['message' => 'No linked customer account found.'], 422);
        }

        $account = $link->account;
        $arrearsMinor = $account->outstandingBalanceMinor();

        $calc = $this->creditControl->calculatePlanOptions($arrearsMinor, $account->currency_code);

        return response()->json(['data' => $calc]);
    }

    public function activePlan(Request $request)
    {
        $link = $this->links->linksFor($request->user())->first();
        if (! $link || ! $link->account) {
            return response()->json(['data' => null]);
        }

        $plan = $this->creditControl->activePlanForAccount($link->account);
        if (! $plan) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->creditControl->transformPlan($plan)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'in:3,6,12'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $link = $this->links->linksFor($request->user())->first();
        if (! $link || ! $link->account) {
            return response()->json(['message' => 'No linked customer account found.'], 422);
        }

        $plan = $this->creditControl->createPlan($link->account, $data, $request->user());

        return response()->json([
            'message' => 'Structured payment plan created. Disconnection immunity is now active on your account.',
            'data' => $this->creditControl->transformPlan($plan),
        ], 201);
    }

    public function payInstallment(Request $request, string $id)
    {
        $data = $request->validate([
            'gateway' => ['nullable', 'string', 'in:paynow,sandbox'],
            'payment_method' => ['nullable', 'string', 'in:ecocash,onemoney,card'],
            'phone' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        $installment = PaymentPlanInstallment::with('plan.account')->findOrFail($id);
        $plan = $installment->plan;
        $account = $plan->account;

        if ($installment->status === 'paid') {
            return response()->json(['message' => 'This installment is already paid.'], 422);
        }

        $gateway = $data['gateway'] ?? 'paynow';
        $metadata = [
            'type' => 'payment_plan_installment',
            'plan_id' => $plan->id,
            'installment_id' => $installment->id,
            'installment_number' => $installment->installment_number,
            'payment_method' => $data['payment_method'] ?? 'ecocash',
            'phone' => $data['phone'] ?? '',
        ];

        $intent = $this->payments->createIntent(
            $account,
            $installment->amount_minor,
            'mobile',
            $gateway,
            $data['idempotency_key'],
            $request->user(),
            $metadata
        );

        return response()->json([
            'data' => [
                'id' => $intent->id,
                'reference' => $intent->reference,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency_code,
                'status' => $intent->status,
                'checkout' => $this->payments->gateway($intent->gateway)->createCheckout($intent),
            ],
        ], 201);
    }
}

