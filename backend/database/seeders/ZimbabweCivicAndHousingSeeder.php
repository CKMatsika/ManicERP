<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Customer\Models\CivicItem;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Land\Models\HousingWaitingListEntry;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZimbabweCivicAndHousingSeeder extends Seeder
{
    public function run(?Council $council = null): void
    {
        $tenants = app(TenantManager::class);
        $targetCouncil = $council ?? Council::where('code', 'DEMO')->first();
        if (! $targetCouncil) {
            return;
        }

        $tenants->connect($targetCouncil);

        // Find demo user
        $demoUser = User::where('email', 'citizen@example.com')->first();
        $accountLink = $demoUser ? PortalAccountLink::where('user_id', $demoUser->id)->first() : null;

        DB::connection('tenant')->transaction(function () use ($demoUser, $accountLink) {
            // 1. Seed Civic Items
            $civicData = [
                [
                    'type' => 'event',
                    'title' => '2027 Municipal Budget Consultation & Ward Deliberations',
                    'category' => 'Budget Consultation',
                    'summary' => 'Residents and business stakeholders are invited to submit inputs for the 2027 Council Annual Budget priorities.',
                    'content' => "In terms of Section 219 of the Urban Councils Act [Chapter 29:15], notice is hereby given that the Council has commenced public consultations on the draft 2027 Capital and Revenue Estimates.\n\nWard Consultations Schedule:\n- Ward 1 to 4: Civic Centre Main Hall (10:00 AM)\n- Ward 5 to 8: Chikanga Community Hall (02:00 PM)\n- Ward 9 to 12: Dangamvura Beit Hall (10:00 AM)\n\nCitizens may also submit written memoranda via the citizen mobile app or to the Chamber Secretary.",
                    'author_role' => 'City Treasurer & Town Clerk',
                    'published_date' => now()->subDays(5)->toDateString(),
                    'event_date' => now()->addDays(7)->toDateString(),
                    'venue' => 'Civic Centre Main Auditorium & Ward Halls',
                    'resolution_number' => null,
                    'download_url' => 'https://council.gov.zw/docs/budget-consultation-guide-2027.pdf',
                    'is_featured' => true,
                ],
                [
                    'type' => 'resolution',
                    'title' => 'Council Resolution No. 89/2026: 50% Interest Waiver on Rates Arrears',
                    'category' => 'Full Council Resolution',
                    'summary' => 'Full Council resolution offering a 50% interest write-off on municipal service debts settled within 90 days.',
                    'content' => "At the 842nd Ordinary Full Council Meeting held on 28 October 2026, Council unanimously resolved (Resolution FC/89/2026):\n\n1. That a 50% discount on accumulated interest be granted to all residential and commercial rate payers who settle their outstanding balances in full or enter into structured 6-month payment arrangements.\n2. Payment channels approved for immediate automated settlement include Council Banking Halls, Point of Sale, and the Citizen Mobile App (via Paynow / EcoCash / Cards).\n3. The amnesty is valid until 31 January 2027.",
                    'author_role' => 'Chamber Secretary',
                    'published_date' => now()->subDays(10)->toDateString(),
                    'event_date' => null,
                    'venue' => 'Council Chambers',
                    'resolution_number' => 'FC/89/2026',
                    'download_url' => 'https://council.gov.zw/gazette/resolution-89-2026-rates-incentive.pdf',
                    'is_featured' => true,
                ],
                [
                    'type' => 'news',
                    'title' => 'City Water & Sanitation Maintenance Schedule: Wards 3, 7 and 11',
                    'category' => 'Engineering & Works',
                    'summary' => 'Planned maintenance on the Odzani trunk main line and pump station valve overhaul.',
                    'content' => "The City Engineer wishes to notify residents in Chikanga, Palmerstone, and Dangamvura that scheduled maintenance will take place on the Odzani pipeline to install new high-pressure control valves.\n\nWater bowsers will be stationed at designated community collection points during the 18-hour shutdown. Normal water pumping will resume immediately upon completion.",
                    'author_role' => 'Director of Engineering Services',
                    'published_date' => now()->subDays(2)->toDateString(),
                    'event_date' => null,
                    'venue' => null,
                    'resolution_number' => null,
                    'download_url' => null,
                    'is_featured' => false,
                ],
                [
                    'type' => 'newsletter',
                    'title' => 'The Municipal Voice - Q3 2026 Civic Bulletin',
                    'category' => 'Quarterly Magazine',
                    'summary' => 'Highlights: Smart City Digital Payments rollout, Solar Streetlight installations, and Housing Waiting List reforms.',
                    'content' => "Read the latest quarterly civic review detailing major progress in municipal services:\n- Digital citizen services rollout featuring live bill statements and mobile licence applications.\n- Transparent Housing Waiting List digital tracking to eliminate allocation irregularities.\n- Roads rehabilitation program under ERRP II with 14.8 km surfaced this quarter.",
                    'author_role' => 'Public Relations Department',
                    'published_date' => now()->subDays(15)->toDateString(),
                    'event_date' => null,
                    'venue' => null,
                    'resolution_number' => null,
                    'download_url' => 'https://council.gov.zw/publications/municipal-voice-q3-2026.pdf',
                    'is_featured' => false,
                ],
            ];

            foreach ($civicData as $item) {
                CivicItem::updateOrCreate(
                    ['title' => $item['title']],
                    $item
                );
            }

            // 2. Seed Background Housing Waiting List Entries to demonstrate realistic queue numbers
            $sampleEntries = [
                ['name' => 'Farai Moyo', 'nat_id' => '63-128945-K-18', 'points' => 38, 'date' => now()->subYears(3)->toDateString(), 'cat' => 'high_density'],
                ['name' => 'Tendai Chidziwa', 'nat_id' => '08-492100-M-24', 'points' => 32, 'date' => now()->subYears(2)->toDateString(), 'cat' => 'high_density'],
                ['name' => 'Kudzai Marufu', 'nat_id' => '29-847291-C-02', 'points' => 28, 'date' => now()->subYears(2)->addMonths(3)->toDateString(), 'cat' => 'high_density'],
                ['name' => 'Blessing Sibanda', 'nat_id' => '58-109247-B-75', 'points' => 22, 'date' => now()->subYear()->toDateString(), 'cat' => 'high_density'],
                ['name' => 'Tatenda Mutasa', 'nat_id' => '63-994821-E-19', 'points' => 18, 'date' => now()->subMonths(6)->toDateString(), 'cat' => 'high_density'],
            ];

            foreach ($sampleEntries as $idx => $sample) {
                HousingWaitingListEntry::updateOrCreate(
                    ['national_id' => $sample['nat_id']],
                    [
                        'waiting_list_number' => 'HWL-' . date('Y', strtotime($sample['date'])) . '-' . sprintf('%05d', $idx + 101),
                        'full_name' => $sample['name'],
                        'phone_number' => '+26377200' . sprintf('%04d', $idx),
                        'email' => strtolower(str_replace(' ', '.', $sample['name'])) . '@example.com',
                        'category' => $sample['cat'],
                        'preferred_suburbs' => ['Dangamvura', 'Chikanga', 'Hobhouse'],
                        'marital_status' => 'married',
                        'number_of_dependents' => 2,
                        'employment_status' => 'employed',
                        'monthly_income_minor' => 45000,
                        'currency_code' => 'USD',
                        'priority_points' => $sample['points'],
                        'status' => 'active',
                        'application_date' => $sample['date'],
                        'expiry_date' => now()->addMonths(6)->toDateString(),
                        'last_renewed_at' => now()->subMonths(6),
                        'notes' => 'Historical verified application.',
                    ]
                );
            }

            // 3. Demo Citizen's Active Waiting List Entry
            if ($demoUser) {
                HousingWaitingListEntry::updateOrCreate(
                    ['user_id' => $demoUser->id],
                    [
                        'waiting_list_number' => 'HWL-2024-00482',
                        'user_id' => $demoUser->id,
                        'customer_account_id' => $accountLink?->customer_account_id,
                        'national_id' => '63-2049182-W-42',
                        'full_name' => $demoUser->name ?: 'Citizen Demo Resident',
                        'phone_number' => '+263774123456',
                        'email' => $demoUser->email,
                        'category' => 'high_density',
                        'preferred_suburbs' => ['Chikanga Extension', 'Dangamvura Area 3'],
                        'marital_status' => 'married',
                        'number_of_dependents' => 3,
                        'employment_status' => 'employed',
                        'monthly_income_minor' => 65000, // $650.00
                        'currency_code' => 'USD',
                        'priority_points' => 25, // Has 25 priority points
                        'status' => 'active',
                        'application_date' => now()->subMonths(18)->toDateString(),
                        'expiry_date' => now()->addDays(42)->toDateString(), // 42 days until annual renewal
                        'last_renewed_at' => now()->subMonths(10),
                        'notes' => 'Registered citizen applicant with active rates account link.',
                    ]
                );
            }
        });
    }
}

