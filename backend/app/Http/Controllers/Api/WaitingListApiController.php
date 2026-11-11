<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Land\Services\HousingWaitingListService;
use App\Modules\Revenue\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;

class WaitingListApiController extends Controller
{
    public function __construct(
        private readonly HousingWaitingListService $waitingList,
        private readonly AccountLinkService $links,
        private readonly PaymentService $payments,
    ) {}

    public function categories()
    {
        return response()->json([
            'data' => [
                [
                    'code' => 'high_density',
                    'name' => 'High Density Residential Stand',
                    'stand_size' => '200 - 300 m²',
                    'application_fee_usd' => 20.00,
                    'annual_renewal_usd' => 15.00,
                    'suburbs' => ['Dangamvura', 'Chikanga', 'Hobhouse', 'Sakubva'],
                    'description' => 'Standard high-density housing stand suitable for single-family detached dwellings.',
                ],
                [
                    'code' => 'medium_density',
                    'name' => 'Medium Density Residential Stand',
                    'stand_size' => '600 - 1000 m²',
                    'application_fee_usd' => 35.00,
                    'annual_renewal_usd' => 20.00,
                    'suburbs' => ['Yeovil', 'Florida', 'Fairbridge Park', 'Darlington'],
                    'description' => 'Suburban medium-density stand in established or newly serviced areas.',
                ],
                [
                    'code' => 'low_density',
                    'name' => 'Low Density Residential Stand',
                    'stand_size' => '1500 - 4000 m²',
                    'application_fee_usd' => 50.00,
                    'annual_renewal_usd' => 30.00,
                    'suburbs' => ['Morningside', 'Murambi', 'Palmerstone', 'Bordervale'],
                    'description' => 'Low-density prime residential plot with municipal services.',
                ],
                [
                    'code' => 'commercial',
                    'name' => 'Commercial / Suburban Retail Stand',
                    'stand_size' => '300 - 1200 m²',
                    'application_fee_usd' => 75.00,
                    'annual_renewal_usd' => 45.00,
                    'suburbs' => ['Central Business District', 'Commercial Service Centres'],
                    'description' => 'Commercial shop, service station, or office development parcel.',
                ],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $entries = $this->waitingList->listForUser($request->user());

        return response()->json([
            'data' => $entries,
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'national_id' => ['required', 'string', 'max:30'],
            'full_name' => ['required', 'string', 'max:160'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'category' => ['required', 'in:high_density,medium_density,low_density,commercial,industrial'],
            'preferred_suburbs' => ['nullable', 'array'],
            'marital_status' => ['required', 'in:single,married,widowed,divorced'],
            'number_of_dependents' => ['required', 'integer', 'min:0', 'max:20'],
            'employment_status' => ['required', 'in:employed,self_employed,informal_trader,unemployed,retired'],
            'monthly_income_minor' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = $this->waitingList->register($request->user(), $data);

        return response()->json([
            'message' => 'Housing Waiting List application submitted successfully.',
            'data' => $this->waitingList->transformEntry($entry),
        ], 201);
    }

    public function renew(Request $request, string $id)
    {
        $entry = $this->waitingList->getEntry($id, $request->user());
        $renewed = $this->waitingList->renew($entry);

        return response()->json([
            'message' => 'Waiting list registration renewed successfully for 12 months.',
            'data' => $this->waitingList->transformEntry($renewed),
        ]);
    }
}

