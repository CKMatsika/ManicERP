<?php

namespace Database\Seeders;

use App\Modules\Land\Models\LandParcel;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZimbabweLandParcelsSeeder extends Seeder
{
    public function run(?Council $council = null): void
    {
        $tenants = app(TenantManager::class);
        $target = $council ?? Council::where('code', 'DEMO')->first();
        if (! $target) return;

        $tenants->connect($target);

        $parcels = [
            [
                'parcel_number' => 'STD-4821-CHK',
                'description' => 'Stand 4821 Chikanga Phase 3 Residential',
                'ward' => 'W02',
                'suburb' => 'Chikanga',
                'stand_category' => 'high_density',
                'area_sqm' => 300.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 240000, // $2,400.00
                'deposit_required_minor' => 40000, // $400.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -18.9654210,
                'longitude' => 32.6412500,
                'coordinate_source' => 'Surveyor General Cadastral Diagram 418/2024',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
            [
                'parcel_number' => 'STD-4822-CHK',
                'description' => 'Stand 4822 Chikanga Phase 3 Residential',
                'ward' => 'W02',
                'suburb' => 'Chikanga',
                'stand_category' => 'high_density',
                'area_sqm' => 280.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 220000, // $2,200.00
                'deposit_required_minor' => 35000, // $350.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -18.9656100,
                'longitude' => 32.6415200,
                'coordinate_source' => 'Surveyor General Cadastral Diagram 418/2024',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
            [
                'parcel_number' => 'STD-1904-DNG',
                'description' => 'Stand 1904 Dangamvura Area 3 Residential',
                'ward' => 'W01',
                'suburb' => 'Dangamvura',
                'stand_category' => 'high_density',
                'area_sqm' => 320.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 260000, // $2,600.00
                'deposit_required_minor' => 45000, // $450.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -19.0125400,
                'longitude' => 32.6104200,
                'coordinate_source' => 'Surveyor General Cadastral Diagram 109/2025',
                'coordinate_accuracy' => 'Total Station 0.02m',
            ],
            [
                'parcel_number' => 'STD-0815-FBP',
                'description' => 'Stand 815 Fairbridge Park Medium Density',
                'ward' => 'W03',
                'suburb' => 'Fairbridge Park',
                'stand_category' => 'medium_density',
                'area_sqm' => 800.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 650000, // $6,500.00
                'deposit_required_minor' => 120000, // $1,200.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -18.9412000,
                'longitude' => 32.6685000,
                'coordinate_source' => 'Cadastral Plan FB/2023/12',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
            [
                'parcel_number' => 'STD-0210-MRN',
                'description' => 'Stand 210 Morningside Low Density Residential',
                'ward' => 'W03',
                'suburb' => 'Morningside',
                'stand_category' => 'low_density',
                'area_sqm' => 2200.00,
                'servicing_status' => 'partially_serviced',
                'purchase_price_minor' => 1800000, // $18,000.00
                'deposit_required_minor' => 350000, // $3,500.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -18.9324000,
                'longitude' => 32.6791000,
                'coordinate_source' => 'Surveyor General General Plan GP-8812',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
            [
                'parcel_number' => 'STD-0550-CBD',
                'description' => 'Stand 550 Herbert Chitepo Street Commercial Plaza',
                'ward' => 'W04',
                'suburb' => 'Central Business District',
                'stand_category' => 'commercial',
                'area_sqm' => 1500.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 8500000, // $85,000.00
                'deposit_required_minor' => 1500000, // $15,000.00
                'currency_code' => 'USD',
                'allocation_status' => 'allocated',
                'latitude' => -18.9734100,
                'longitude' => 32.6702500,
                'coordinate_source' => 'Surveyor General Title Deed 1928/1998',
                'coordinate_accuracy' => 'Cadastral Beacon Gold Pin 0.01m',
            ],
            [
                'parcel_number' => 'STD-0104-NYK',
                'description' => 'Stand 104 Nyakamete Heavy Industrial Zone',
                'ward' => 'W06',
                'suburb' => 'Nyakamete Industrial',
                'stand_category' => 'industrial',
                'area_sqm' => 5400.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 12500000, // $125,000.00
                'deposit_required_minor' => 2500000, // $25,000.00
                'currency_code' => 'USD',
                'allocation_status' => 'allocated',
                'latitude' => -18.9951200,
                'longitude' => 32.6324100,
                'coordinate_source' => 'Surveyor General Plan IND-992/2012',
                'coordinate_accuracy' => 'Total Station 0.02m',
            ],
            [
                'parcel_number' => 'STD-3120-HBH',
                'description' => 'Stand 3120 Hobhouse High Density Extension',
                'ward' => 'W05',
                'suburb' => 'Hobhouse',
                'stand_category' => 'high_density',
                'area_sqm' => 300.00,
                'servicing_status' => 'unserviced',
                'purchase_price_minor' => 190000, // $1,900.00
                'deposit_required_minor' => 30000, // $300.00
                'currency_code' => 'USD',
                'allocation_status' => 'available',
                'latitude' => -18.9812000,
                'longitude' => 32.6189000,
                'coordinate_source' => 'Surveyor General Pegging Diagram HBH-402',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
            [
                'parcel_number' => 'STD-0412-PLM',
                'description' => 'Stand 412 Palmerstone Medium Density Stand',
                'ward' => 'W03',
                'suburb' => 'Palmerstone',
                'stand_category' => 'medium_density',
                'area_sqm' => 1100.00,
                'servicing_status' => 'serviced',
                'purchase_price_minor' => 950000, // $9,500.00
                'deposit_required_minor' => 180000, // $1,800.00
                'currency_code' => 'USD',
                'allocation_status' => 'reserved',
                'latitude' => -18.9554000,
                'longitude' => 32.6811000,
                'coordinate_source' => 'Cadastral Plan PLM-2021-08',
                'coordinate_accuracy' => 'GPS RTK 0.05m',
            ],
        ];

        DB::connection('tenant')->transaction(function () use ($parcels) {
            foreach ($parcels as $p) {
                LandParcel::updateOrCreate(
                    ['parcel_number' => $p['parcel_number']],
                    $p
                );
            }
        });
    }
}

