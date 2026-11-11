<?php

namespace App\Http\Controllers\Engineering;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\WaterMeter;
use App\Modules\Revenue\Services\WaterMeterBillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterMeterController extends Controller
{
    public function __construct(
        private readonly WaterMeterBillingService $waterService,
    ) {}

    public function index(): Response
    {
        $summary = $this->waterService->getWaterMetersSummary();

        return Inertia::render('Engineering/WaterMeters', [
            'summary' => $summary,
        ]);
    }

    public function capture(Request $request)
    {
        $data = $request->validate([
            'water_meter_id' => ['required', 'string'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'reading_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $meter = WaterMeter::findOrFail($data['water_meter_id']);

        $reading = $this->waterService->recordReading(
            $meter,
            (float) $data['current_reading'],
            $data['reading_period'],
            $data['gps_lat'] ?? null,
            $data['gps_lng'] ?? null,
            $request->user(),
            'manual_walk',
            $data['notes'] ?? null
        );

        $msg = "Meter reading recorded for {$meter->meter_number}. Consumption: {$reading->consumption} m³.";
        if ($reading->anomaly_flag === 'burst_alert') {
            $msg .= " ⚠️ WARNING: High consumption anomaly detected (+220% of average)! Leak inspection recommended.";
        }

        return redirect()->back()->with('success', $msg);
    }

    public function billingRun(Request $request)
    {
        $data = $request->validate([
            'reading_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $result = $this->waterService->generateMonthlyBillingRun($data['reading_period'], $request->user());

        return redirect()->back()->with(
            'success',
            "Water billing run completed for {$result['period']}: {$result['bills_generated']} bills generated (Total: USD {$result['total_billed_usd']})."
        );
    }
}

