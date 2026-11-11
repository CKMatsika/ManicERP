<?php

namespace Database\Seeders;

use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\WaterMeter;
use App\Modules\Revenue\Services\WaterMeterBillingService;
use Illuminate\Database\Seeder;

class ZimbabweWaterMetersSeeder extends Seeder
{
    public function run(WaterMeterBillingService $waterService): void
    {
        $citizenAccount = CustomerAccount::where('account_number', 'ACC-0000001')->first();

        $sampleMeters = [
            [
                'meter_number' => 'WTR-MTR-00101',
                'serial_number' => 'ELSTER-24-998210',
                'brand_model' => 'Elster V100 15mm Volumetric',
                'meter_size' => '15mm',
                'customer_account_id' => $citizenAccount?->id,
                'property_stand' => 'AV-1024',
                'suburb' => 'Avenues',
                'location_address' => 'Stand 1024, 14 Jason Moyo Avenue',
                'gps_lat' => -18.9721000,
                'gps_lng' => 32.6715000,
                'initial_reading' => 124.500,
                'last_reading' => 142.200, // 17.7 m³ consumption
                'last_reading_date' => '2026-10-25',
                'average_monthly_consumption' => 16.5,
                'tariff_category' => 'domestic',
                'status' => 'active',
            ],
            [
                'meter_number' => 'WTR-MTR-00204',
                'serial_number' => 'ELSTER-23-441029',
                'brand_model' => 'Elster V100 15mm Volumetric',
                'meter_size' => '15mm',
                'customer_account_id' => null,
                'property_stand' => 'STD-4821-CHK',
                'suburb' => 'Chikanga 2',
                'location_address' => 'Stand 4821 Chikanga Phase 2',
                'gps_lat' => -18.9680000,
                'gps_lng' => 32.6450000,
                'initial_reading' => 310.000,
                'last_reading' => 378.400, // 68.4 m³ - High Consumption Burst Alert!
                'last_reading_date' => '2026-10-25',
                'average_monthly_consumption' => 20.0,
                'tariff_category' => 'domestic',
                'status' => 'active',
            ],
            [
                'meter_number' => 'WTR-MTR-00305',
                'serial_number' => 'SENSUS-22-881920',
                'brand_model' => 'Sensus 405S Multi-Jet 20mm',
                'meter_size' => '20mm',
                'customer_account_id' => null,
                'property_stand' => 'STD-192-DNG',
                'suburb' => 'Dangamvura',
                'location_address' => 'Stand 192 Area 3 Dangamvura',
                'gps_lat' => -18.9950000,
                'gps_lng' => 32.6320000,
                'initial_reading' => 840.100,
                'last_reading' => 852.300,
                'last_reading_date' => '2026-10-25',
                'average_monthly_consumption' => 12.0,
                'tariff_category' => 'domestic',
                'status' => 'active',
            ],
            [
                'meter_number' => 'WTR-MTR-00412',
                'serial_number' => 'ITRON-21-394821',
                'brand_model' => 'Itron Flostar M 50mm Industrial',
                'meter_size' => '50mm',
                'customer_account_id' => null,
                'property_stand' => 'IND-004-NYK',
                'suburb' => 'Nyakamete Industrial',
                'location_address' => 'Plot 4 Simon Mazorodze Road',
                'gps_lat' => -18.9830000,
                'gps_lng' => 32.6580000,
                'initial_reading' => 4500.000,
                'last_reading' => 4850.000,
                'last_reading_date' => '2026-10-25',
                'average_monthly_consumption' => 350.0,
                'tariff_category' => 'industrial',
                'status' => 'active',
            ],
        ];

        foreach ($sampleMeters as $m) {
            $meter = WaterMeter::firstOrCreate(['meter_number' => $m['meter_number']], $m);

            if ($meter->readings()->count() === 0) {
                // Record past reading
                $isBurst = ($m['meter_number'] === 'WTR-MTR-00204');
                $meter->readings()->create([
                    'reading_period' => '2026-10',
                    'previous_reading' => $m['initial_reading'],
                    'current_reading' => $m['last_reading'],
                    'consumption' => $m['last_reading'] - $m['initial_reading'],
                    'reading_source' => 'mobile_app',
                    'gps_lat' => $m['gps_lat'],
                    'gps_lng' => $m['gps_lng'],
                    'gps_distance_meters' => 8,
                    'anomaly_flag' => $isBurst ? 'burst_alert' : 'normal',
                    'billed_status' => 'pending',
                    'notes' => $isBurst ? 'ALERT: Consumption exceeds 220% of 3-month average. Inspect underground pipe on stand.' : 'Verified meter reading.',
                ]);
            }
        }
    }
}

