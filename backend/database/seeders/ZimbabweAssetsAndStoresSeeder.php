<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\AssetCategory;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Services\AssetManagementService;
use App\Modules\Procurement\Models\StoresItem;
use App\Modules\Procurement\Services\CentralStoresService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZimbabweAssetsAndStoresSeeder extends Seeder
{
    public function run(AssetManagementService $assets, CentralStoresService $stores): void
    {
        // 1. Asset Categories (IPSAS 17)
        $categories = [
            [
                'code' => 'INFRA',
                'name' => 'Infrastructure Assets (Water, Sewer & Roads)',
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 30,
                'gl_asset_account' => '1210',
                'gl_depreciation_expense_account' => '5100',
                'gl_accumulated_depreciation_account' => '1290',
                'description' => 'Trunk water mains, sewer outfalls, bitumen roads, and storm drainage network.',
            ],
            [
                'code' => 'PLANT',
                'name' => 'Plant & Heavy Machinery',
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 12,
                'gl_asset_account' => '1215',
                'gl_depreciation_expense_account' => '5100',
                'gl_accumulated_depreciation_account' => '1290',
                'description' => 'Water treatment high-lift pumps, road graders, backhoes, and standby diesel generators.',
            ],
            [
                'code' => 'VEH',
                'name' => 'Motor Vehicles & Refuse Compactors',
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 7,
                'gl_asset_account' => '1220',
                'gl_depreciation_expense_account' => '5100',
                'gl_accumulated_depreciation_account' => '1290',
                'description' => 'Refuse compactor trucks, tractor trailers, ambulances, and fire tenders.',
            ],
            [
                'code' => 'BLDG',
                'name' => 'Land & Civic Buildings',
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 50,
                'gl_asset_account' => '1200',
                'gl_depreciation_expense_account' => '5100',
                'gl_accumulated_depreciation_account' => '1290',
                'description' => 'Town halls, municipal clinics, revenue halls, and workshop depots.',
            ],
        ];

        $catMap = [];
        foreach ($categories as $cat) {
            $catMap[$cat['code']] = AssetCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }

        // 2. Fixed Assets
        $sampleAssets = [
            [
                'asset_number' => 'AST-2026-00001',
                'asset_category_id' => $catMap['VEH']->id,
                'name' => 'Dennis Eagle Elite 19m³ Refuse Compactor Truck',
                'description' => 'Euro 5 6x2 rear-loading municipal waste compaction vehicle. Engine: Cummins ISB6.7.',
                'serial_or_vin' => 'VIN-DE-2024-884219',
                'department' => 'Health & Environmental Services',
                'custodian_name' => 'C. Nyathi (Chief Cleansing Superintendent)',
                'location_name' => 'Central Mechanical Workshop Depot',
                'gps_lat' => -18.9728000,
                'gps_lng' => 32.6711000,
                'acquisition_date' => '2024-03-15',
                'purchase_cost_minor' => 18500000, // USD 185,000.00
                'residual_value_minor' => 1500000, // USD 15,000.00
                'current_carrying_amount_minor' => 14850000,
                'accumulated_depreciation_minor' => 3650000,
                'condition' => 'excellent',
                'status' => 'in_service',
                'last_audit_date' => '2026-10-15',
                'qr_code_data' => 'MANIC-ERP:ASSET:AST-2026-00001:VERIFIED',
            ],
            [
                'asset_number' => 'AST-2026-00002',
                'asset_category_id' => $catMap['PLANT']->id,
                'name' => 'Dangamvura High-Lift Water Booster Pump Station #2',
                'description' => 'KSB 250kW multi-stage centrifugal pump unit delivering 450m³/hr to Dangamvura Reservoir.',
                'serial_or_vin' => 'KSB-PUMP-2022-771',
                'department' => 'Engineering & Works',
                'custodian_name' => 'Eng. T. Masango (Water Works Engineer)',
                'location_name' => 'Dangamvura Water Works',
                'gps_lat' => -18.9954000,
                'gps_lng' => 32.6342000,
                'acquisition_date' => '2022-07-10',
                'purchase_cost_minor' => 9200000, // USD 92,000.00
                'residual_value_minor' => 500000,
                'current_carrying_amount_minor' => 6070000,
                'accumulated_depreciation_minor' => 3130000,
                'condition' => 'good',
                'status' => 'in_service',
                'last_audit_date' => '2026-09-30',
                'qr_code_data' => 'MANIC-ERP:ASSET:AST-2026-00002:VERIFIED',
            ],
            [
                'asset_number' => 'AST-2026-00003',
                'asset_category_id' => $catMap['INFRA']->id,
                'name' => 'Sakubva Main Sewer Trunk Outfall (450mm uPVC 3.2km)',
                'description' => 'Gravity sewer outfall main conveying wastewater from Sakubva to Gimboki Treatment Works.',
                'serial_or_vin' => 'CAD-SEW-SKB-01',
                'department' => 'Engineering & Works',
                'custodian_name' => 'S. Sithole (Civil Superintendent)',
                'location_name' => 'Sakubva to Gimboki Sewer Corridor',
                'gps_lat' => -18.9880000,
                'gps_lng' => 32.6510000,
                'acquisition_date' => '2021-11-01',
                'purchase_cost_minor' => 34000000, // USD 340,000.00
                'residual_value_minor' => 0,
                'current_carrying_amount_minor' => 28333000,
                'accumulated_depreciation_minor' => 5667000,
                'condition' => 'good',
                'status' => 'in_service',
                'last_audit_date' => '2026-08-20',
                'qr_code_data' => 'MANIC-ERP:ASSET:AST-2026-00003:VERIFIED',
            ],
            [
                'asset_number' => 'AST-2026-00004',
                'asset_category_id' => $catMap['PLANT']->id,
                'name' => 'Komatsu GD655-5 Motor Grader',
                'description' => '165kW articulated road maintenance motor grader with 4.27m moldboard.',
                'serial_or_vin' => 'KOM-GD655-48192',
                'department' => 'Engineering & Works',
                'custodian_name' => 'P. Choto (Roads Foreman)',
                'location_name' => 'Town Yard Roads Section',
                'gps_lat' => -18.9712000,
                'gps_lng' => 32.6680000,
                'acquisition_date' => '2023-05-18',
                'purchase_cost_minor' => 21000000, // USD 210,000.00
                'residual_value_minor' => 2000000,
                'current_carrying_amount_minor' => 15500000,
                'accumulated_depreciation_minor' => 5500000,
                'condition' => 'good',
                'status' => 'in_service',
                'last_audit_date' => '2026-10-01',
                'qr_code_data' => 'MANIC-ERP:ASSET:AST-2026-00004:VERIFIED',
            ],
            [
                'asset_number' => 'AST-2026-00005',
                'asset_category_id' => $catMap['BLDG']->id,
                'name' => 'Civic Centre Main Administration Complex',
                'description' => '4-storey council headquarters housing Town Clerk, Chamber Secretary, Treasury & Council Chambers.',
                'serial_or_vin' => 'STAND-0001-CIVIC',
                'department' => 'Chamber Secretary',
                'custodian_name' => 'Estates & Valuations Manager',
                'location_name' => 'Civic Centre Grounds, Mutare',
                'gps_lat' => -18.9734000,
                'gps_lng' => 32.6705000,
                'acquisition_date' => '2015-01-01',
                'purchase_cost_minor' => 120000000, // USD 1,200,000.00
                'residual_value_minor' => 20000000,
                'current_carrying_amount_minor' => 96400000,
                'accumulated_depreciation_minor' => 23600000,
                'condition' => 'good',
                'status' => 'in_service',
                'last_audit_date' => '2026-06-15',
                'qr_code_data' => 'MANIC-ERP:ASSET:AST-2026-00005:VERIFIED',
            ],
        ];

        foreach ($sampleAssets as $astData) {
            FixedAsset::firstOrCreate(['asset_number' => $astData['asset_number']], $astData);
        }

        // 3. Central Stores Catalog
        $storesItems = [
            [
                'item_code' => 'STK-WTR-001',
                'name' => '110mm Class 16 uPVC Pressure Pipe (6m length)',
                'category' => 'Water & Reticulation',
                'unit_of_measure' => 'pipe',
                'reorder_level' => 30,
                'reorder_quantity' => 100,
                'quantity_on_hand' => 84.000,
                'average_unit_cost_minor' => 4500, // USD 45.00
                'total_valuation_minor' => 378000, // USD 3,780.00
                'bin_location' => 'Yard Bay 4 (Pipe Rack)',
                'description' => 'Solvent weld socket pipe for municipal domestic water distribution.',
            ],
            [
                'item_code' => 'STK-CHEM-002',
                'name' => 'Granular Aluminum Sulphate 17% (50kg Bag)',
                'category' => 'Water Treatment Chemicals',
                'unit_of_measure' => '50kg bag',
                'reorder_level' => 50,
                'reorder_quantity' => 200,
                'quantity_on_hand' => 160.000,
                'average_unit_cost_minor' => 2800, // USD 28.00
                'total_valuation_minor' => 448000, // USD 4,480.00
                'bin_location' => 'Chemical Shed Bay A',
                'description' => 'Primary coagulant for potable water clarification at Odzani and Chikanga works.',
            ],
            [
                'item_code' => 'STK-MTR-003',
                'name' => '15mm Class C Rotary Piston Volumetric Domestic Water Meter',
                'category' => 'Water & Reticulation',
                'unit_of_measure' => 'unit',
                'reorder_level' => 50,
                'reorder_quantity' => 250,
                'quantity_on_hand' => 38.000, // Below reorder level!
                'average_unit_cost_minor' => 2400, // USD 24.00
                'total_valuation_minor' => 91200, // USD 912.00
                'bin_location' => 'Store 1 Shelf B4',
                'description' => 'High precision residential billing water meters equipped with pulse output.',
            ],
            [
                'item_code' => 'STK-RDS-004',
                'name' => 'Cold Mix Bitumen Asphalt Premix (25kg Bag)',
                'category' => 'Road Maintenance',
                'unit_of_measure' => '25kg bag',
                'reorder_level' => 40,
                'reorder_quantity' => 150,
                'quantity_on_hand' => 110.000,
                'average_unit_cost_minor' => 1250, // USD 12.50
                'total_valuation_minor' => 137500, // USD 1,375.00
                'bin_location' => 'Civil Depot Bay 2',
                'description' => 'Ready-to-use pothole patching emulsion premix for municipal roads.',
            ],
            [
                'item_code' => 'STK-ELEC-005',
                'name' => '100W High Efficiency Solar LED Street Light Luminaire',
                'category' => 'Street Lighting & Electrical',
                'unit_of_measure' => 'unit',
                'reorder_level' => 20,
                'reorder_quantity' => 80,
                'quantity_on_hand' => 14.000, // Below reorder level!
                'average_unit_cost_minor' => 9500, // USD 95.00
                'total_valuation_minor' => 133000, // USD 1,330.00
                'bin_location' => 'Store 2 Rack C1',
                'description' => 'All-in-one solar street light with dusk-to-dawn intelligent optical sensor.',
            ],
        ];

        foreach ($storesItems as $stkData) {
            $item = StoresItem::firstOrCreate(['item_code' => $stkData['item_code']], $stkData);

            // Record initial GRV
            if ($item->transactions()->count() === 0) {
                $item->transactions()->create([
                    'transaction_number' => 'GRV-2026-' . strtoupper(Str::random(6)),
                    'type' => 'grv',
                    'quantity' => $stkData['quantity_on_hand'],
                    'unit_cost_minor' => $stkData['average_unit_cost_minor'],
                    'total_cost_minor' => $stkData['total_valuation_minor'],
                    'work_order_ref' => 'PO-2026-OPENING-STOCK',
                    'authorized_by' => 'Central Stores Controller',
                    'notes' => 'Opening verified inventory balance.',
                ]);
            }
        }
    }
}

