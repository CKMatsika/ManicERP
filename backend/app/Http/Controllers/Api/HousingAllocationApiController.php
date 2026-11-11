<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Land\Models\HousingAllocation;
use App\Modules\Land\Services\HousingAllocationService;
use App\Modules\Revenue\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;

class HousingAllocationApiController extends Controller
{
    public function __construct(
        private readonly HousingAllocationService $allocations,
        private readonly AccountLinkService $links,
        private readonly PaymentService $payments,
    ) {}

    public function currentOffer(Request $request)
    {
        $offer = $this->allocations->getOfferForCitizen($request->user());

        if (! $offer) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->allocations->transformAllocation($offer),
        ]);
    }

    public function accept(Request $request, string $id)
    {
        $offer = HousingAllocation::findOrFail($id);
        $accepted = $this->allocations->acceptOffer($offer, $request->user());

        return response()->json([
            'message' => 'Municipal stand allocation offer accepted successfully.',
            'data' => $this->allocations->transformAllocation($accepted),
        ]);
    }

    public function reject(Request $request, string $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:250'],
        ]);

        $offer = HousingAllocation::findOrFail($id);
        $rejected = $this->allocations->rejectOffer($offer, $data['reason'], $request->user());

        return response()->json([
            'message' => 'Stand allocation offer declined. Your position on the waiting list has been restored.',
            'data' => $this->allocations->transformAllocation($rejected),
        ]);
    }

    public function payDeposit(Request $request, string $id)
    {
        $data = $request->validate([
            'gateway' => ['nullable', 'string', 'in:paynow,sandbox'],
            'payment_method' => ['nullable', 'string', 'in:ecocash,onemoney,card'],
            'phone' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        $offer = HousingAllocation::findOrFail($id);

        if ($offer->status !== 'accepted' && $offer->status !== 'offer_issued') {
            return response()->json(['message' => 'Offer is not in an acceptable state for deposit payment.'], 422);
        }

        $depositDue = $offer->deposit_required_minor - $offer->deposit_paid_minor;
        if ($depositDue <= 0) {
            return response()->json(['message' => 'Stand deposit is already fully paid.'], 422);
        }

        $link = $this->links->linksFor($request->user())->first();
        if (! $link || ! $link->account) {
            return response()->json(['message' => 'No linked customer account found for billing.'], 422);
        }

        $account = $link->account;
        $gateway = $data['gateway'] ?? 'paynow';
        $metadata = [
            'type' => 'stand_deposit',
            'allocation_id' => $offer->id,
            'stand_number' => $offer->stand_number,
            'payment_method' => $data['payment_method'] ?? 'ecocash',
            'phone' => $data['phone'] ?? '',
        ];

        $intent = $this->payments->createIntent(
            $account,
            $depositDue,
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

