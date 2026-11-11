<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZimbabweSpecializedServicesSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection('tenant');

        // 1. CEMETERIES & GRAVES
        if ($conn->table('cemeteries')->count() === 0) {
            $cemPioneerId = (string) Str::ulid();
            $cemYeovilId = (string) Str::ulid();

            $conn->table('cemeteries')->insert([
                [
                    'id' => $cemPioneerId,
                    'name' => 'Pioneer Municipal Cemetery',
                    'location' => 'Memorial Way & Aerodrome Road, Mutare',
                    'status' => 'active',
                    'total_plots' => 1200,
                    'occupied_plots' => 980,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $cemYeovilId,
                    'name' => 'Yeovil Memorial Lawn Cemetery',
                    'location' => 'Off Vumba Road, Yeovil, Mutare',
                    'status' => 'active',
                    'total_plots' => 2500,
                    'occupied_plots' => 450,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Plots
            $plot1Id = (string) Str::ulid();
            $plot2Id = (string) Str::ulid();
            $plot3Id = (string) Str::ulid();
            $plot4Id = (string) Str::ulid();

            $conn->table('grave_plots')->insert([
                [
                    'id' => $plot1Id,
                    'cemetery_id' => $cemYeovilId,
                    'section' => 'Section A - Lawn',
                    'block' => 'BLK-04',
                    'plot_number' => 'PLOT-A-0421',
                    'depth' => 'double_8ft',
                    'status' => 'interred',
                    'reserved_by' => 'Chiweshe Family',
                    'reserved_contact' => '+263 77 219 8812',
                    'reserved_at' => '2026-09-12 10:00:00',
                    'plot_fee_minor' => 20000, // $200.00
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $plot2Id,
                    'cemetery_id' => $cemYeovilId,
                    'section' => 'Section A - Lawn',
                    'block' => 'BLK-04',
                    'plot_number' => 'PLOT-A-0422',
                    'depth' => 'double_8ft',
                    'status' => 'reserved',
                    'reserved_by' => 'Chiweshe Family (Reserved for Spouse)',
                    'reserved_contact' => '+263 77 219 8812',
                    'reserved_at' => '2026-09-12 10:00:00',
                    'plot_fee_minor' => 20000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $plot3Id,
                    'cemetery_id' => $cemYeovilId,
                    'section' => 'Section B - General',
                    'block' => 'BLK-02',
                    'plot_number' => 'PLOT-B-0118',
                    'depth' => 'single_6ft',
                    'status' => 'available',
                    'reserved_by' => null,
                    'reserved_contact' => null,
                    'reserved_at' => null,
                    'plot_fee_minor' => 15000, // $150.00
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $plot4Id,
                    'cemetery_id' => $cemPioneerId,
                    'section' => 'Section C - Anglican',
                    'block' => 'BLK-09',
                    'plot_number' => 'PLOT-C-0911',
                    'depth' => 'single_6ft',
                    'status' => 'interred',
                    'reserved_by' => 'Musarurwa Estate',
                    'reserved_contact' => '+263 71 498 1234',
                    'reserved_at' => '2026-10-04 09:30:00',
                    'plot_fee_minor' => 15000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Interments
            $conn->table('cemetery_interments')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'grave_plot_id' => $plot1Id,
                    'interment_order_number' => 'BUR-2026-0182',
                    'deceased_name' => 'Late Sekuru Tinashe Chiweshe',
                    'deceased_national_id' => '63-049182-B-42',
                    'date_of_death' => '2026-09-10',
                    'date_of_interment' => '2026-09-14',
                    'burial_permit_number' => 'MUT-MED-BP-8912/26',
                    'cause_of_death' => 'Cardio-respiratory arrest / Natural causes',
                    'undertaker_name' => 'Nyaradzo Funeral Services',
                    'next_of_kin_name' => 'Grace Chiweshe (Daughter)',
                    'next_of_kin_phone' => '+263 77 219 8812',
                    'interment_fee_minor' => 12000, // $120.00
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'notes' => 'Depth checked by Cemetery Supervisor: 8 feet achieved. Headstone permit issued.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'grave_plot_id' => $plot4Id,
                    'interment_order_number' => 'BUR-2026-0195',
                    'deceased_name' => 'Late Mbuya Christine Musarurwa',
                    'deceased_national_id' => '75-104921-M-75',
                    'date_of_death' => '2026-10-02',
                    'date_of_interment' => '2026-10-06',
                    'burial_permit_number' => 'MUT-MED-BP-9041/26',
                    'cause_of_death' => 'Hypertensive heart disease',
                    'undertaker_name' => 'Doves Funeral Assurance',
                    'next_of_kin_name' => 'David Musarurwa (Son)',
                    'next_of_kin_phone' => '+263 71 498 1234',
                    'interment_fee_minor' => 10000,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'notes' => 'Interment completed at 11:30am. Anglican priest officiating.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 2. EMERGENCY SERVICES & FIRE BRIGADE
        if ($conn->table('fire_hydrants')->count() === 0) {
            $conn->table('fire_hydrants')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'hydrant_code' => 'HYD-CBD-001',
                    'location_address' => 'Corner Herbert Chitepo St & 1st Avenue',
                    'suburb' => 'CBD',
                    'static_pressure_bar' => 4.20,
                    'flow_rate_lpm' => 1450.00,
                    'status' => 'operational',
                    'last_inspected_at' => '2026-10-15',
                    'inspected_by' => 'Station Officer C. Matambo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'hydrant_code' => 'HYD-IND-012',
                    'location_address' => '5 Simon Mazorodze Road, Nyakamete Industrial',
                    'suburb' => 'Nyakamete',
                    'static_pressure_bar' => 3.80,
                    'flow_rate_lpm' => 1600.00,
                    'status' => 'operational',
                    'last_inspected_at' => '2026-10-18',
                    'inspected_by' => 'Leading Firefighter E. Zhou',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'hydrant_code' => 'HYD-CHK-045',
                    'location_address' => 'Chikanga Shopping Centre, Near Rank',
                    'suburb' => 'Chikanga',
                    'static_pressure_bar' => 2.10,
                    'flow_rate_lpm' => 850.00,
                    'status' => 'low_pressure',
                    'last_inspected_at' => '2026-10-22',
                    'inspected_by' => 'Sub Officer P. Nyathi',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $conn->table('fire_safety_inspections')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'certificate_number' => 'FSC-2026-0142',
                    'premises_name' => 'Meikles Department Store & Supermarket',
                    'stand_number' => 'STD-1042-CBD',
                    'owner_name' => 'Meikles Limited',
                    'occupancy_type' => 'Commercial Retail',
                    'extinguishers_valid' => true,
                    'hose_reels_operational' => true,
                    'fire_escapes_clear' => true,
                    'compliance_status' => 'compliant',
                    'inspection_date' => '2026-10-12',
                    'expires_at' => '2027-10-12',
                    'officer_name' => 'Chief Fire Officer T. Marume',
                    'inspection_fee_minor' => 8000, // $80.00
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'certificate_number' => 'FSC-2026-0149',
                    'premises_name' => 'TotalEnergies Service Station & Fuel Depot',
                    'stand_number' => 'STD-4890-IND',
                    'owner_name' => 'TotalEnergies Zimbabwe',
                    'occupancy_type' => 'Petroleum & Flammable Liquids',
                    'extinguishers_valid' => true,
                    'hose_reels_operational' => true,
                    'fire_escapes_clear' => true,
                    'compliance_status' => 'compliant',
                    'inspection_date' => '2026-10-20',
                    'expires_at' => '2027-10-20',
                    'officer_name' => 'Chief Fire Officer T. Marume',
                    'inspection_fee_minor' => 15000, // $150.00
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $conn->table('emergency_incidents')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'incident_number' => 'INC-2026-041',
                    'incident_type' => 'structural_fire',
                    'caller_name' => 'Farai Shumba (Security Guard)',
                    'caller_phone' => '+263 77 410 9921',
                    'location_address' => 'Stand 12, Riverside Light Industrial Park',
                    'suburb' => 'Riverside',
                    'station_code' => 'STN-CENTRAL',
                    'appliances_dispatched' => 'Major Tender 1, Water Bowser 3, Utility 4',
                    'dispatched_at' => '2026-10-24 14:12:00',
                    'arrived_at' => '2026-10-24 14:19:00',
                    'contained_at' => '2026-10-24 15:45:00',
                    'casualties' => 0,
                    'service_charge_minor' => 35000, // $350.00 statutory fire callout
                    'billed_account_number' => 'ACC-0000001',
                    'status' => 'closed',
                    'incident_report' => 'Electrical distribution board overload in packaging warehouse. Extinguished with dry chemical powder and Class A foam. No structural collapse.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'incident_number' => 'INC-2026-048',
                    'incident_type' => 'ambulance_run',
                    'caller_name' => 'Sister-in-Charge M. Gumbo',
                    'caller_phone' => '+263 71 291 0041',
                    'location_address' => 'Chikanga Clinic, Transfer to Mutare Provincial Hospital',
                    'suburb' => 'Chikanga',
                    'station_code' => 'STN-CENTRAL',
                    'appliances_dispatched' => 'Advanced Life Support Ambulance AMB-02',
                    'dispatched_at' => '2026-11-01 08:30:00',
                    'arrived_at' => '2026-11-01 08:38:00',
                    'contained_at' => '2026-11-01 09:15:00',
                    'casualties' => 1,
                    'service_charge_minor' => 4500, // $45.00 ambulance transfer
                    'billed_account_number' => 'ACC-0000001',
                    'status' => 'closed',
                    'incident_report' => 'Emergency inter-facility obstetric transfer with midwife onboard. Patient admitted safely to Provincial Hospital Maternity.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 3. FLEET & HEAVY PLANT
        if ($conn->table('fleet_vehicles')->count() === 0) {
            $vCompactorId = (string) Str::ulid();
            $vTipperId = (string) Str::ulid();
            $vGraderId = (string) Str::ulid();
            $vAmbulanceId = (string) Str::ulid();

            $conn->table('fleet_vehicles')->insert([
                [
                    'id' => $vCompactorId,
                    'registration_number' => 'AED-4819',
                    'make' => 'FAW',
                    'model' => 'CA3250 15-Tonne Compactor',
                    'plant_type' => 'refuse_compactor',
                    'department' => 'Health & Environment',
                    'assigned_driver' => 'T. Chari (Licence Class 2)',
                    'current_odometer' => 124890,
                    'fuel_type' => 'diesel',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $vTipperId,
                    'registration_number' => 'AEG-9021',
                    'make' => 'Sinotruk HOWO',
                    'model' => '371 10m³ Tipper Truck',
                    'plant_type' => 'tipper_truck',
                    'department' => 'Engineering & Roads',
                    'assigned_driver' => 'J. Musasa (Licence Class 2)',
                    'current_odometer' => 89400,
                    'fuel_type' => 'diesel',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $vGraderId,
                    'registration_number' => 'CM-GR-002',
                    'make' => 'Caterpillar',
                    'model' => 'CAT 140K Motor Grader',
                    'plant_type' => 'heavy_plant_grader',
                    'department' => 'Engineering & Roads',
                    'assigned_driver' => 'E. Mawere (Plant Operator)',
                    'current_odometer' => 4520, // Hours
                    'fuel_type' => 'diesel',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $vAmbulanceId,
                    'registration_number' => 'GVT-1142',
                    'make' => 'Toyota',
                    'model' => 'Land Cruiser 79 4x4 Ambulance',
                    'plant_type' => 'ambulance',
                    'department' => 'Emergency Services',
                    'assigned_driver' => 'K. Nyambiya (Driver / Paramedic)',
                    'current_odometer' => 48210,
                    'fuel_type' => 'diesel',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Fuel Coupons
            $conn->table('fleet_fuel_coupons')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'vehicle_id' => $vCompactorId,
                    'coupon_serial' => 'FL-CPN-2026-0812',
                    'issue_date' => '2026-11-04',
                    'litres' => 120.00,
                    'fuel_type' => 'diesel',
                    'purpose' => 'Dangamvura Weekly Refuse Collection Route B',
                    'authorized_by' => 'Director of Health & Services',
                    'driver_name' => 'T. Chari',
                    'cost_minor' => 19200, // $192.00 @ $1.60/L
                    'status' => 'redeemed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'vehicle_id' => $vGraderId,
                    'coupon_serial' => 'FL-CPN-2026-0819',
                    'issue_date' => '2026-11-08',
                    'litres' => 180.00,
                    'fuel_type' => 'diesel',
                    'purpose' => 'Chikanga Phase 3 Access Roads Regravelling',
                    'authorized_by' => 'Director of Works & Engineering',
                    'driver_name' => 'E. Mawere',
                    'cost_minor' => 28800, // $288.00
                    'status' => 'redeemed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Logbooks
            $conn->table('fleet_logbook_entries')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'vehicle_id' => $vCompactorId,
                    'trip_date' => '2026-11-05',
                    'driver_name' => 'T. Chari',
                    'purpose' => 'Refuse collection Chikanga wards 12, 14, 15 to landfill',
                    'start_odometer' => 124810,
                    'end_odometer' => 124890,
                    'distance_km' => 80,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 4. DEVOLUTION CAPITAL INFRASTRUCTURE PROJECTS
        if ($conn->table('capital_projects')->count() === 0) {
            $pRoadId = (string) Str::ulid();
            $pWaterId = (string) Str::ulid();
            $pClinicId = (string) Str::ulid();

            $conn->table('capital_projects')->insert([
                [
                    'id' => $pRoadId,
                    'project_code' => 'DEV-2026-RDS-01',
                    'title' => 'Chikanga to Dangamvura 4.2km Road Rehabilitation & Tarring',
                    'category' => 'roads',
                    'funding_source' => 'intergovernmental_devolution_grant',
                    'approved_budget_minor' => 42000000, // $420,000.00
                    'contract_value_minor' => 39500000, // $395,000.00
                    'contractor_name' => 'Bitumen World (Pvt) Ltd',
                    'contractor_praz_number' => 'PRAZ/CONST/2026/8912',
                    'commencement_date' => '2026-03-01',
                    'expected_completion_date' => '2026-12-15',
                    'physical_progress_percent' => 72.50,
                    'retention_rate_percent' => 10.00,
                    'retention_held_minor' => 2863750, // $28,637.50
                    'certified_claims_minor' => 28637500, // $286,375.00
                    'paid_to_date_minor' => 25773750, // $257,737.50
                    'status' => 'in_progress',
                    'scope_of_works' => 'Sub-base soil stabilization, 150mm crushed stone base, prime coat, 35mm asphalt concrete overlay and precast concrete stormwater drains.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $pWaterId,
                    'project_code' => 'DEV-2026-WTR-02',
                    'title' => 'Dangamvura 10-Megalitre High-Pressure Water Reservoir & Booster Pump',
                    'category' => 'water_sanitation',
                    'funding_source' => 'intergovernmental_devolution_grant',
                    'approved_budget_minor' => 68000000, // $680,000.00
                    'contract_value_minor' => 65000000, // $650,000.00
                    'contractor_name' => 'Masimba Construction Zimbabwe',
                    'contractor_praz_number' => 'PRAZ/CONST/2026/0014',
                    'commencement_date' => '2026-01-15',
                    'expected_completion_date' => '2026-11-30',
                    'physical_progress_percent' => 88.00,
                    'retention_rate_percent' => 10.00,
                    'retention_held_minor' => 5720000, // $57,200.00
                    'certified_claims_minor' => 57200000, // $572,000.00
                    'paid_to_date_minor' => 51480000, // $514,800.00
                    'status' => 'in_progress',
                    'scope_of_works' => 'Reinforced circular concrete water reservoir, dual suction booster pumps, telemetry SCADA link and 400mm ductile iron delivery main.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $pClinicId,
                    'project_code' => 'DEV-2026-HLT-03',
                    'title' => 'Hobhouse Municipal Maternity Clinic & Solar Cold-Chain Facility',
                    'category' => 'health_clinic',
                    'funding_source' => 'intergovernmental_devolution_grant',
                    'approved_budget_minor' => 18500000, // $185,000.00
                    'contract_value_minor' => 17800000, // $178,000.00
                    'contractor_name' => 'Fossil Contracting (Pvt) Ltd',
                    'contractor_praz_number' => 'PRAZ/CONST/2026/4102',
                    'commencement_date' => '2026-06-01',
                    'expected_completion_date' => '2027-02-28',
                    'physical_progress_percent' => 45.00,
                    'retention_rate_percent' => 10.00,
                    'retention_held_minor' => 801000, // $8,010.00
                    'certified_claims_minor' => 8010000, // $80,100.00
                    'paid_to_date_minor' => 7209000, // $72,090.00
                    'status' => 'in_progress',
                    'scope_of_works' => '20-bed maternal post-natal ward, delivery suites, sluice room, 15kVA solar backup system and incinerator.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // Certificates
            $conn->table('capital_project_certificates')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'capital_project_id' => $pRoadId,
                    'certificate_number' => 'IPC-01',
                    'valuation_date' => '2026-05-30',
                    'gross_certified_minor' => 14000000, // $140,000.00
                    'retention_deducted_minor' => 1400000, // $14,000.00 (10%)
                    'net_payable_minor' => 12600000, // $126,000.00
                    'cumulative_progress_percent' => 35.00,
                    'certifying_engineer' => 'Eng. C. Sithole (Director of Works)',
                    'approval_status' => 'paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'capital_project_id' => $pRoadId,
                    'certificate_number' => 'IPC-02',
                    'valuation_date' => '2026-09-30',
                    'gross_certified_minor' => 14637500, // $146,375.00
                    'retention_deducted_minor' => 1463750, // $14,637.50
                    'net_payable_minor' => 13173750, // $131,737.50
                    'cumulative_progress_percent' => 72.50,
                    'certifying_engineer' => 'Eng. C. Sithole (Director of Works)',
                    'approval_status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}

