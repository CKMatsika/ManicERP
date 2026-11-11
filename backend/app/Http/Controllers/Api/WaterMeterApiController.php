<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Revenue\Models\WaterMeter;
use App\Modules\Revenue\Services\WaterMeterBillingService;
use Illuminate\Http\Request;

class WaterMeterApiController extends Controller
{
    public function __construct(
        private readonly AccountLinkService $links,
        private readonly WaterMeterBillingService $waterService,
    ) {}

    public function currentMeter(Request $request)
    {
        $link = $this->links->linksFor($request->user())->first();
        if (! $link || ! $link->account) {
            return response()->json(['data' => null]);
        }

        $meter = WaterMeter::with(['readings' => fn ($q) => $q->latest()->limit(6)])
            ->where('customer_account_id', $link->account->id)
            ->first();

        if (! $meter) {
            return response()->json(['data' => null]);
        }

        $latestReading = $meter->readings->first();
        $consumption = $latestReading ? (float) $latestReading->consumption : (float) $meter->average_monthly_consumption;
        $tariff = WaterMeterBillingService::calculateWaterTariff($consumption, $meter->tariff_category);

        return response()->json([
            'data' => [
                'id' => $meter->id,
                'meter_number' => $meter->meter_number,
                'serial_number' => $meter->serial_number,
                'meter_size' => $meter->meter_size,
                'property_stand' => $meter->property_stand,
                'location_address' => $meter->location_address,
                'last_reading' => (float) $meter->last_reading,
                'last_reading_date' => $meter->last_reading_date?->toDateString(),
                'latest_consumption_m3' => $consumption,
                'average_monthly_consumption' => (float) $meter->average_monthly_consumption,
                'tariff_category' => $meter->tariff_category,
                'estimated_bill' => $tariff,
                'readings_history' => $meter->readings->map(fn ($r) => [
                    'period' => $r->reading_period,
                    'reading' => (float) $r->current_reading,
                    'consumption' => (float) $r->consumption,
                    'anomaly' => $r->anomaly_flag,
                    'date' => $r->created_at?->toDateString(),
                ])->all(),
            ],
        ]);
    }
}

