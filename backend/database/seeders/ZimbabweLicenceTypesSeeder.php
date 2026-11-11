<?php

namespace Database\Seeders;

use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Licensing\Models\LicenceTypeVersion;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZimbabweLicenceTypesSeeder extends Seeder
{
    public function run(?Council $council = null): void
    {
        $tenants = app(TenantManager::class);
        $targetCouncil = $council ?? Council::where('code', 'DEMO')->first();
        if (! $targetCouncil) {
            return;
        }

        $tenants->connect($targetCouncil);

        $licences = [
            [
                'code' => 'SHOP_GENERAL',
                'name' => 'General Dealer & Retail Shop Licence',
                'authority' => 'Shop Licences Act [Chapter 14:17]',
                'currency' => 'USD',
                'application_minor' => 3500, // $35.00
                'renewal_minor' => 2500,     // $25.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'Premises health, fire safety, and zoning compliance check.',
                'fields' => [
                    ['name' => 'trading_name', 'label' => 'Trading / Business Name', 'required' => true, 'max_length' => 160],
                    ['name' => 'company_reg_number', 'label' => 'Company Registration / PB Number', 'required' => true, 'max_length' => 60],
                    ['name' => 'premises_address', 'label' => 'Physical Business Address & Stand No.', 'required' => true, 'max_length' => 240],
                    ['name' => 'goods_category', 'label' => 'Category of Goods / Retail Nature', 'required' => true, 'max_length' => 200],
                ],
                'documents' => [
                    ['code' => 'incorporation_or_id', 'label' => 'Certificate of Incorporation or National ID'],
                    ['code' => 'lease_or_deed', 'label' => 'Lease Agreement or Title Deed / Stand Allocation'],
                    ['code' => 'fire_safety_cert', 'label' => 'Municipal Fire Safety Clearance Certificate'],
                ],
            ],
            [
                'code' => 'FOOD_HYGIENE',
                'name' => 'Food Hygiene & Catering Licence (Restaurant / Bakery)',
                'authority' => 'Public Health Act [Chapter 15:17] & Food and Food Standards Act',
                'currency' => 'USD',
                'application_minor' => 5000, // $50.00
                'renewal_minor' => 3500,     // $35.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'City Health Inspector food handling, cold room and sanitation audit.',
                'fields' => [
                    ['name' => 'establishment_name', 'label' => 'Restaurant / Food Outlet Name', 'required' => true, 'max_length' => 160],
                    ['name' => 'seating_capacity', 'label' => 'Seating Capacity (or Takeaway only)', 'required' => true, 'max_length' => 60],
                    ['name' => 'waste_disposal_plan', 'label' => 'Grease Trap & Waste Disposal Plan', 'required' => true, 'max_length' => 240],
                    ['name' => 'water_source', 'label' => 'Municipal Water Meter No. / Back-up Source', 'required' => true, 'max_length' => 100],
                ],
                'documents' => [
                    ['code' => 'food_handlers_certs', 'label' => 'Certified Food Handlers Medical Certificates (All Staff)'],
                    ['code' => 'premises_plan', 'label' => 'Floor Plan showing kitchen, storage & grease trap'],
                    ['code' => 'pest_control_contract', 'label' => 'Valid Pest Control Service Agreement'],
                ],
            ],
            [
                'code' => 'LIQUOR_OUTLET',
                'name' => 'Liquor Outlet & Bar Licence (Municipal Endorsement)',
                'authority' => 'Liquor Act [Chapter 14:12] & Local Authority By-Laws',
                'currency' => 'USD',
                'application_minor' => 10000, // $100.00
                'renewal_minor' => 7500,      // $75.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'Joint City Health, Fire, Town Planning & ZRP CID vetting.',
                'fields' => [
                    ['name' => 'outlet_name', 'label' => 'Bar / Bottle Store / Club Name', 'required' => true, 'max_length' => 160],
                    ['name' => 'licence_class', 'label' => 'Liquor Class (Bottle Store / Sports Bar / Night Club / Wholesale)', 'required' => true, 'max_length' => 80],
                    ['name' => 'operating_hours', 'label' => 'Proposed Operating Hours', 'required' => true, 'max_length' => 80],
                    ['name' => 'soundproofing', 'label' => 'Sound Proofing & Security Arrangements', 'required' => true, 'max_length' => 240],
                ],
                'documents' => [
                    ['code' => 'police_clearance', 'label' => 'Zimbabwe Republic Police (CID) Fingerprint Clearance'],
                    ['code' => 'liquor_board_application', 'label' => 'Liquor Licensing Board Form 1 Copy'],
                    ['code' => 'newspaper_ad_proof', 'label' => 'Government Gazette / Newspaper Notice Publication'],
                ],
            ],
            [
                'code' => 'VENDOR_PERMIT',
                'name' => 'Hawkers & Street Vendors Operating Permit',
                'authority' => 'Urban Councils (Hawkers & Street Vendors) By-laws',
                'currency' => 'USD',
                'application_minor' => 1000, // $10.00
                'renewal_minor' => 1000,     // $10.00
                'validity_days' => 180,      // 6 months
                'inspection_required' => false,
                'inspection_desc' => 'Site allocation verification by municipal police.',
                'fields' => [
                    ['name' => 'vendor_full_name', 'label' => 'Vendor Full Legal Name', 'required' => true, 'max_length' => 160],
                    ['name' => 'national_id_number', 'label' => 'National ID Number (e.g. 63-123456-X-00)', 'required' => true, 'max_length' => 30],
                    ['name' => 'designated_zone', 'label' => 'Preferred Vending Zone / Market Stall (e.g. CBD Rank, Mbare Market)', 'required' => true, 'max_length' => 120],
                    ['name' => 'merchandise_type', 'label' => 'Type of Wares / Produce Sold', 'required' => true, 'max_length' => 160],
                ],
                'documents' => [
                    ['code' => 'national_id_copy', 'label' => 'Certified National ID Copy'],
                    ['code' => 'passport_photo', 'label' => 'Passport Size Photograph'],
                    ['code' => 'residence_proof', 'label' => 'Proof of Residence / Councillor Confirmation Letter'],
                ],
            ],
            [
                'code' => 'BUILDING_PERMIT',
                'name' => 'Development & Building Plan Approval Permit',
                'authority' => 'Regional, Town and Country Planning Act & Model Building By-laws',
                'currency' => 'USD',
                'application_minor' => 6000, // $60.00
                'renewal_minor' => 3000,     // $30.00
                'validity_days' => 730,      // 2 years to construct
                'inspection_required' => true,
                'inspection_desc' => 'City Architect, Town Planning & Structural Engineering plan appraisal.',
                'fields' => [
                    ['name' => 'stand_number', 'label' => 'Stand / Parcel Number & Township', 'required' => true, 'max_length' => 80],
                    ['name' => 'development_type', 'label' => 'Development Type (New Residential / Extension / Commercial / Industrial)', 'required' => true, 'max_length' => 80],
                    ['name' => 'architect_name', 'label' => 'Architect / Draughtsman Name & Institute Reg', 'required' => true, 'max_length' => 160],
                    ['name' => 'estimated_cost', 'label' => 'Estimated Construction Value ($)', 'required' => true, 'max_length' => 60],
                ],
                'documents' => [
                    ['code' => 'title_deed_or_offer', 'label' => 'Title Deed or Council Stand Allocation / Offer Letter'],
                    ['code' => 'architectural_drawings', 'label' => 'Complete Architectural Working Drawings (4 sets PDF)'],
                    ['code' => 'structural_calculations', 'label' => 'Structural Engineering Calculations (if multi-storey/commercial)'],
                ],
            ],
            [
                'code' => 'SALON_BEAUTY',
                'name' => 'Health & Beauty (Hair Salon, Barbershop & Spa)',
                'authority' => 'Public Health (Barbers and Hairdressers) By-laws',
                'currency' => 'USD',
                'application_minor' => 2000, // $20.00
                'renewal_minor' => 1500,     // $15.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'Inspection of sterilizing autoclaves, UV equipment, water and ventilation.',
                'fields' => [
                    ['name' => 'salon_name', 'label' => 'Salon / Barbershop Trading Name', 'required' => true, 'max_length' => 160],
                    ['name' => 'stations_count', 'label' => 'Number of Cutting / Styling Stations', 'required' => true, 'max_length' => 40],
                    ['name' => 'sterilization_methods', 'label' => 'Sterilization & Blade Disposal Protocol', 'required' => true, 'max_length' => 240],
                ],
                'documents' => [
                    ['code' => 'national_id', 'label' => 'Owner / Manager National ID Copy'],
                    ['code' => 'premises_lease', 'label' => 'Lease Agreement or Shop Owner Consent'],
                    ['code' => 'cosmetology_quals', 'label' => 'Cosmetology / Hairdresser Certification (Lead Stylist)'],
                ],
            ],
            [
                'code' => 'COMMUTER_OPERATOR',
                'name' => 'Public Transport & Rank Permit (Kombi / Taxi)',
                'authority' => 'Road Motor Transportation Act & Municipal Traffic By-laws',
                'currency' => 'USD',
                'application_minor' => 4000, // $40.00
                'renewal_minor' => 3000,     // $30.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'Physical rank inspection and Certificate of Fitness check.',
                'fields' => [
                    ['name' => 'vehicle_reg_number', 'label' => 'Vehicle Registration (Number Plate)', 'required' => true, 'max_length' => 20],
                    ['name' => 'vehicle_type', 'label' => 'Vehicle Classification (18-Seater Kombi / Metered Taxi / Bus)', 'required' => true, 'max_length' => 60],
                    ['name' => 'route_destination', 'label' => 'Designated Route & Rank (e.g. Fourth Street to Chitungwiza)', 'required' => true, 'max_length' => 160],
                    ['name' => 'vid_cert_number', 'label' => 'VID Certificate of Fitness Number', 'required' => true, 'max_length' => 60],
                ],
                'documents' => [
                    ['code' => 'vid_fitness_cert', 'label' => 'Current Vehicle Inspection Department (VID) Fitness Certificate'],
                    ['code' => 'passenger_insurance', 'label' => 'Valid Passenger Liability Insurance Policy'],
                    ['code' => 'operator_licence', 'label' => 'Road Motor Transportation (RMT) Operator Licence Copy'],
                ],
            ],
            [
                'code' => 'OUTDOOR_ADVERT',
                'name' => 'Outdoor Advertising & Billboard Permit',
                'authority' => 'Control of Advertising and Billboards By-laws',
                'currency' => 'USD',
                'application_minor' => 4500, // $45.00
                'renewal_minor' => 3500,     // $35.00
                'validity_days' => 365,
                'inspection_required' => true,
                'inspection_desc' => 'Traffic sightline safety inspection & structural stability review.',
                'fields' => [
                    ['name' => 'advert_type', 'label' => 'Sign Type (Static Billboard / LED Digital Screen / Pylon / Wall Sign)', 'required' => true, 'max_length' => 80],
                    ['name' => 'dimensions_metric', 'label' => 'Display Dimensions (Height x Width in metres)', 'required' => true, 'max_length' => 60],
                    ['name' => 'location_description', 'label' => 'Road Name, Intersection & GPS Location', 'required' => true, 'max_length' => 200],
                    ['name' => 'illumination_type', 'label' => 'Illumination (Internal / Floodlight / Solar / Non-illuminated)', 'required' => true, 'max_length' => 80],
                ],
                'documents' => [
                    ['code' => 'site_plan', 'label' => 'Site Plan Showing Road Reserve & Distance from Intersection'],
                    ['code' => 'structural_drawing', 'label' => 'Engineer Certified Mounting & Windload Structural Drawing'],
                    ['code' => 'property_owner_consent', 'label' => 'Landowner / Property Consent Letter'],
                ],
            ],
        ];

        DB::connection('tenant')->transaction(function () use ($licences) {
            foreach ($licences as $item) {
                $type = LicenceType::updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'status' => 'published',
                        'configuration_authority' => $item['authority'],
                        'effective_from' => now()->subDays(30)->toDateString(),
                        'effective_to' => null,
                    ]
                );

                LicenceTypeVersion::updateOrCreate(
                    ['licence_type_id' => $type->id, 'version' => 1],
                    [
                        'approval_status' => 'approved',
                        'configuration' => [
                            'label' => $item['name'],
                            'authority' => $item['authority'],
                            'validity_days' => $item['validity_days'],
                            'charges' => [
                                'currency' => $item['currency'],
                                'application_minor' => $item['application_minor'],
                                'issuance_minor' => 0,
                                'inspection_minor' => 0,
                                'renewal_minor' => $item['renewal_minor'],
                                'refundable' => false,
                                'recognition' => 'immediate statutory municipal fee',
                            ],
                            'approval_stages' => ['review', 'inspection', 'approval', 'issuance'],
                            'inspection' => [
                                'required' => $item['inspection_required'],
                                'description' => $item['inspection_desc'],
                            ],
                            'application_fields' => $item['fields'],
                            'required_documents' => $item['documents'],
                        ],
                    ]
                );
            }
        });
    }
}

