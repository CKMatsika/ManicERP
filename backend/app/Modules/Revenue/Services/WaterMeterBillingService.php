<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\WaterMeter;
use App\Modules\Revenue\Models\WaterMeterReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WaterMeterBillingService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Stepped Inverted Block Tariff (IBT) per m³
     * Band 1: 0 - 10 m³ = $0.45 (Domestic Lifeline)
     * Band 2: 11 - 25 m³ = $0.85
     * Band 3: 26 - 50 m³ = $1.40
     * Band 4: 51+ m³ = $2.10
     * Sewerage surcharge: 25% of water consumption
     */
    public static function calculateWaterTariff(float $consumptionM3, string $category = 'domestic'): array
    {
        $consumption = max(0, $consumptionM3);
        $waterChargeUsd = 0.0;

        if ($category === 'commercial' || $category === 'industrial') {
            $waterChargeUsd = $consumption * 1.75;
        } else {
            // Domestic Stepped Tier
            $remaining = $consumption;

            // Tier 1 (0 - 10)
            $tier1 = min($remaining, 10.0);
            $waterChargeUsd += $tier1 * 0.45;
            $remaining -= $tier1;

            // Tier 2 (11 - 25)
            if ($remaining > 0) {
                $tier2 = min($remaining, 15.0);
                $waterChargeUsd += $tier2 * 0.85;
                $remaining -= $tier2;
            }

            // Tier 3 (26 - 50)
            if ($remaining > 0) {
                $tier3 = min($remaining, 25.0);
                $waterChargeUsd += $tier3 * 1.40;
                $remaining -= $tier3;
            }

            // Tier 4 (51+)
            if ($remaining > 0) {
                $waterChargeUsd += $remaining * 2.10;
            }
        }

        $sewerageSurchargeUsd = $waterChargeUsd * 0.25; // 25% municipal sanitation surcharge
        $totalUsd = $waterChargeUsd + $sewerageSurchargeUsd;

        return [
            'consumption_m3' => round($consumption, 2),
            'water_charge_usd' => number_format($waterChargeUsd, 2),
            'water_charge_minor' => (int) round($waterChargeUsd * 100),
            'sewerage_surcharge_usd' => number_format($sewerageSurchargeUsd, 2),
            'sewerage_surcharge_minor' => (int) round($sewerageSurchargeUsd * 100),
            'total_usd' => number_format($totalUsd, 2),
            'total_minor' => (int) round($totalUsd * 100),
        ];
    }

    public function recordReading(
        WaterMeter $meter,
        float $currentReading,
        string $period,
        ?float $gpsLat = null,
        ?float $gpsLng = null,
        ?User $reader = null,
        string $source = 'mobile_app',
        ?string $notes = null
    ): WaterMeterReading {
        return DB::connection('tenant')->transaction(function () use ($meter, $currentReading, $period, $gpsLat, $gpsLng, $reader, $source, $notes) {
            $prev = (float) $meter->last_reading;
            $consumption = round($currentReading - $prev, 3);

            $anomaly = 'normal';
            if ($consumption < 0) {
                $anomaly = 'negative_consumption';
            } elseif ($consumption === 0.0) {
                $anomaly = 'stuck_meter';
            } elseif ((float) $meter->average_monthly_consumption > 0 && $consumption > ((float) $meter->average_monthly_consumption * 2.2) && $consumption > 15) {
                $anomaly = 'burst_alert'; // Potential pipe burst or underground leak!
            }

            // Distance from meter geofence
            $distanceMeters = null;
            if ($gpsLat !== null && $gpsLng !== null && $meter->gps_lat !== null && $meter->gps_lng !== null) {
                $distanceMeters = $this->calculateHaversineDistance(
                    $gpsLat,
                    $gpsLng,
                    (float) $meter->gps_lat,
                    (float) $meter->gps_lng
                );
            }

            $reading = WaterMeterReading::create([
                'water_meter_id' => $meter->id,
                'reading_period' => $period,
                'previous_reading' => $prev,
                'current_reading' => $currentReading,
                'consumption' => max(0, $consumption),
                'reader_user_id' => $reader?->id,
                'reading_source' => $source,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'gps_distance_meters' => $distanceMeters,
                'anomaly_flag' => $anomaly,
                'billed_status' => 'pending',
                'notes' => $notes,
            ]);

            // Update meter state
            $meter->update([
                'last_reading' => $currentReading,
                'last_reading_date' => now()->toDateString(),
            ]);

            $this->audit->record(
                action: 'engineering.water_reading.captured',
                subject: $reading,
                after: [
                    'meter_number' => $meter->meter_number,
                    'consumption' => $consumption,
                    'anomaly' => $anomaly,
                ],
                module: 'revenue',
            );

            return $reading;
        });
    }

    public function generateMonthlyBillingRun(string $period, ?User $user = null): array
    {
        return DB::connection('tenant')->transaction(function () use ($period) {
            $readings = WaterMeterReading::with('meter.account')
                ->where('reading_period', $period)
                ->where('billed_status', 'pending')
                ->get();

            $processedCount = 0;
            $totalBilledMinor = 0;

            foreach ($readings as $reading) {
                $meter = $reading->meter;
                if (! $meter || ! $meter->customer_account_id || ! $meter->account) {
                    continue;
                }

                $calc = self::calculateWaterTariff((float) $reading->consumption, $meter->tariff_category);
                $year = date('Y');

                $bill = Bill::create([
                    'bill_number' => 'BIL-WTR-' . $year . '-' . strtoupper(Str::random(6)),
                    'customer_account_id' => $meter->customer_account_id,
                    'service_type' => 'water',
                    'total_minor' => $calc['total_minor'],
                    'balance_minor' => $calc['total_minor'],
                    'status' => 'issued',
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(21)->toDateString(),
                ]);

                $reading->update([
                    'billed_status' => 'billed',
                    'bill_id' => $bill->id,
                ]);

                $processedCount++;
                $totalBilledMinor += $calc['total_minor'];
            }

            return [
                'period' => $period,
                'bills_generated' => $processedCount,
                'total_billed_usd' => number_format($totalBilledMinor / 100, 2),
                'total_billed_minor' => $totalBilledMinor,
            ];
        });
    }

    public function getWaterMetersSummary(): array
    {
        $meters = WaterMeter::with(['account', 'readings' => fn ($q) => $q->latest()->limit(3)])
            ->orderBy('meter_number')
            ->get();

        $recentReadings = WaterMeterReading::with('meter.account')
            ->orderByDesc('created_at')
            ->take(15)
            ->get();

        $burstAlerts = $recentReadings->where('anomaly_flag', 'burst_alert')->count();

        return [
            'total_meters_count' => $meters->count(),
            'active_meters_count' => $meters->where('status', 'active')->count(),
            'burst_alerts_count' => $burstAlerts,
            'meters' => $meters->map(function ($m) {
                return [
                    'id' => $m->id,
                    'meter_number' => $m->meter_number,
                    'serial_number' => $m->serial_number,
                    'size' => $m->meter_size,
                    'account_number' => $m->account?->account_number ?? 'UNLINKED',
                    'property' => $m->property_stand,
                    'suburb' => $m->suburb,
                    'address' => $m->location_address,
                    'last_reading' => (float) $m->last_reading,
                    'last_reading_date' => $m->last_reading_date?->toDateString(),
                    'avg_consumption' => (float) $m->average_monthly_consumption,
                    'category' => $m->tariff_category,
                    'status' => $m->status,
                ];
            })->all(),
            'recent_readings' => $recentReadings->map(function ($r) {
                return [
                    'id' => $r->id,
                    'meter_number' => $r->meter?->meter_number,
                    'account_number' => $r->meter?->account?->account_number,
                    'property' => $r->meter?->property_stand,
                    'period' => $r->reading_period,
                    'previous' => (float) $r->previous_reading,
                    'current' => (float) $r->current_reading,
                    'consumption' => (float) $r->consumption,
                    'anomaly' => $r->anomaly_flag,
                    'billed' => $r->billed_status,
                    'date' => $r->created_at?->toDateTimeString(),
                ];
            })->all(),
        ];
    }

    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}

