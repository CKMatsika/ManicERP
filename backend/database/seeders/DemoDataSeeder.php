<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\PostingRule;
use App\Modules\Accounting\Services\ChartImportService;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\TariffVersion;
use App\Modules\Revenue\Models\Ward;
use App\Modules\Revenue\Services\BillingService;
use App\Modules\Revenue\Services\CashierService;
use App\Modules\Revenue\Services\MasterDataService;
use App\Modules\Revenue\Services\MeterReadingService;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clearly labelled demonstration data for the DEMO council. Everything is
 * reversible: no production council, official chart or real integration is
 * implied.
 */
class DemoDataSeeder
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ChartImportService $chartImport,
        private readonly MasterDataService $masterData,
        private readonly MeterReadingService $readings,
        private readonly BillingService $billing,
        private readonly PaymentService $payments,
        private readonly CashierService $cashier,
        private readonly AuditLogger $audit,
    ) {}

    public function run(User $admin, ?User $cashierUser = null, ?User $citizen = null, ?User $approver = null): array
    {
        $summary = [];

        DB::connection('tenant')->transaction(function () use (&$summary, $admin) {
            $wards = $this->seedWards();
            $this->seedDepartments();
            $this->seedDimensions($wards);
            $this->seedChart($admin);
            $this->seedPostingRules($admin);
            $this->seedBankAccount();
        });

        $summary['chart'] = ChartVersion::where('status', 'active')->value('name');
        $summary['wards'] = Ward::count();

        if (Party::count() === 0) {
            $this->seedCustomers($admin, $citizen);
        }

        $summary['parties'] = Party::count();
        $summary['accounts'] = CustomerAccount::count();

        if (Bill::count() === 0) {
            $summary['billing'] = $this->runBilling($admin, $cashierUser, $approver);
        }

        $summary['bills'] = Bill::count();
        $summary['payments'] = Payment::count();

        $this->audit->record(
            action: 'demo.data.seeded',
            after: $summary,
            metadata: ['warning' => 'Demonstration data only. Not a production implementation.'],
            module: 'platform.core',
        );

        return $summary;
    }

    private function seedWards(): array
    {
        $wards = [
            ['code' => 'W01', 'name' => 'Ward 1 - Avondale', 'suburb' => 'Avondale', 'population' => 12400],
            ['code' => 'W02', 'name' => 'Ward 2 - Mbare', 'suburb' => 'Mbare', 'population' => 28100],
            ['code' => 'W03', 'name' => 'Ward 3 - Borrowdale', 'suburb' => 'Borrowdale', 'population' => 7300],
        ];

        $models = [];
        foreach ($wards as $ward) {
            $models[$ward['code']] = Ward::updateOrCreate(['code' => $ward['code']], $ward);
        }

        return $models;
    }

    private function seedDepartments(): void
    {
        foreach ([
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'ENG', 'name' => 'Engineering and water'],
            ['code' => 'HLT', 'name' => 'Health and environment'],
        ] as $department) {
            DB::connection('tenant')->table('departments')->updateOrInsert(
                ['code' => $department['code']],
                [...$department, 'id' => (string) Str::ulid(), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function seedDimensions(array $wards): void
    {
        $values = [
            'ward' => collect($wards)->map(fn (Ward $ward) => ['code' => $ward->code, 'name' => $ward->name])->all(),
            'department' => [
                ['code' => 'FIN', 'name' => 'Finance'],
                ['code' => 'ENG', 'name' => 'Engineering and water'],
                ['code' => 'HLT', 'name' => 'Health and environment'],
            ],
            'fund' => [
                ['code' => 'GENERAL', 'name' => 'General fund'],
                ['code' => 'WATER', 'name' => 'Water fund'],
            ],
            'vote' => [
                ['code' => 'V01', 'name' => 'Vote 1 - Finance and administration'],
                ['code' => 'V02', 'name' => 'Vote 2 - Water and sanitation'],
            ],
            'function' => [
                ['code' => '7011', 'name' => 'Local authority administration'],
                ['code' => '7041', 'name' => 'Water supply'],
            ],
        ];

        foreach ($values as $dimensionCode => $items) {
            $dimensionId = DB::connection('tenant')->table('dimensions')->where('code', $dimensionCode)->value('id');

            if (! $dimensionId) {
                continue;
            }

            foreach ($items as $item) {
                DB::connection('tenant')->table('dimension_values')->updateOrInsert(
                    ['dimension_id' => $dimensionId, 'code' => $item['code']],
                    [
                        'id' => (string) Str::ulid(),
                        'name' => $item['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function seedChart(User $admin): void
    {
        if (ChartVersion::where('status', 'active')->exists()) {
            return;
        }

        $contents = Storage::disk('local')->exists('seed/demo_chart.csv')
            ? Storage::disk('local')->get('seed/demo_chart.csv')
            : file_get_contents(database_path('seeders/data/demo_chart.csv'));

        $batch = $this->chartImport->importContents(
            'demo_chart.csv',
            $contents,
            'csv',
            [
                'name' => 'DEMONSTRATION council chart (not official Treasury schedule)',
                'source_type' => 'demo',
                'source_authority' => 'ManicERP demonstration data',
                'notes' => 'Illustrative codes only. Replace with an approved official schedule before production reporting.',
            ],
            $admin,
        );

        $this->chartImport->approve($batch, $admin);
    }

    private function seedPostingRules(User $admin): void
    {
        $rules = [
            ['event_code' => 'billing.rates', 'name' => 'Rates billing', 'debit_account_code' => '1310', 'credit_account_code' => '4010'],
            ['event_code' => 'billing.water', 'name' => 'Water billing', 'debit_account_code' => '1320', 'credit_account_code' => '4020'],
            ['event_code' => 'billing.refuse', 'name' => 'Refuse billing', 'debit_account_code' => '1320', 'credit_account_code' => '4030'],
            ['event_code' => 'billing.rental', 'name' => 'Rental billing', 'debit_account_code' => '1330', 'credit_account_code' => '4040'],
        ];

        foreach ($rules as $rule) {
            PostingRule::updateOrCreate(
                ['event_code' => $rule['event_code']],
                [
                    'name' => $rule['name'],
                    'description' => 'Demonstration posting rule. Requires council approval before production.',
                    'debit_account_code' => $rule['debit_account_code'],
                    'credit_account_code' => $rule['credit_account_code'],
                    'required_dimensions' => [],
                    'dimensions' => [],
                    'status' => 'approved',
                    'version' => 1,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'approved_by' => $admin->getKey(),
                    'approved_at' => now(),
                ],
            );
        }
    }

    private function seedBankAccount(): void
    {
        BankAccount::updateOrCreate(
            ['code' => 'BANK1'],
            [
                'name' => 'Council main account (DEMO)',
                'bank_name' => 'Demonstration Bank',
                'account_number_masked' => '**** 4821',
                'gl_account_code' => '1120',
                'currency_code' => 'USD',
                'status' => 'active',
            ],
        );
    }

    private function seedCustomers(User $admin, ?User $citizen): void
    {
        $people = [
            ['display_name' => 'Tendai Moyo', 'email' => 'tendai.moyo@example.com', 'phone' => '+263771000001', 'suburb' => 'Avondale', 'ward' => 'W01', 'parcel' => 'AV-1024', 'value' => 8500000],
            ['display_name' => 'Rudo Chikafu', 'email' => 'rudo.chikafu@example.com', 'phone' => '+263771000002', 'suburb' => 'Mbare', 'ward' => 'W02', 'parcel' => 'MB-2088', 'value' => 3200000],
            ['display_name' => 'Farai Ncube', 'email' => 'farai.ncube@example.com', 'phone' => '+263771000003', 'suburb' => 'Borrowdale', 'ward' => 'W03', 'parcel' => 'BW-3001', 'value' => 19000000],
        ];

        $firstAccounts = [];

        foreach ($people as $index => $person) {
            $party = $this->masterData->createParty([
                'party_type' => 'individual',
                'display_name' => $person['display_name'],
                'email' => $person['email'],
                'phone' => $person['phone'],
                'roles' => ['resident', 'owner'],
            ], $admin);

            DB::connection('tenant')->table('contact_points')
                ->where('party_id', $party->id)
                ->where('type', 'email')
                ->update(['verified_at' => now()]);

            $property = $this->masterData->createProperty([
                'parcel_number' => $person['parcel'],
                'stand_number' => 'Stand '.($index + 10),
                'ward_code' => $person['ward'],
                'address_line' => ($index + 1).' Demonstration Avenue',
                'suburb' => $person['suburb'],
                'area_sqm' => '800.00',
                'land_use' => 'residential',
            ], $admin);

            $valuation = $this->masterData->recordValuation($property, now()->subMonths(3)->toDateString(), (int) ($person['value'] * 0.4), (int) ($person['value'] * 0.6), $admin);
            $this->masterData->approveValuation($valuation, $admin);
            $this->masterData->createOwnership($property, $party, 'owner', now()->subYears(5)->toDateString(), '100', $admin);

            $account = $this->masterData->createAccount($property, $party, 'rates', $admin);
            $firstAccounts[] = $account;

            $connection = $this->masterData->addConnection($account, 'water', $property, $admin);
            $this->masterData->addConnection($account, 'refuse', $property, $admin);
            $meter = $this->masterData->addMeter($connection, 'WM-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT), '0', $admin);

            for ($monthsAgo = 3; $monthsAgo >= 1; $monthsAgo--) {
                $readingValue = ((4 - $monthsAgo) * 10) + ($index * 3);
                $this->readings->record(
                    $meter,
                    (string) $readingValue,
                    now()->subMonthsNoOverflow($monthsAgo)->addDays(5)->toDateTimeString(),
                    'actual',
                    $admin,
                );
            }

            $this->readings->record(
                $meter,
                (string) (60 + ($index * 10)),
                now()->subDays(2)->toDateTimeString(),
                'actual',
                $admin,
            );
        }

        // Business customer with a representative notification preference.
        $business = $this->masterData->createParty([
            'party_type' => 'organisation',
            'display_name' => 'Zimbabwe Hardware Suppliers (Pvt) Ltd',
            'registration_number' => 'DEMO/2021/0042',
            'email' => 'accounts@zhs.example.com',
            'phone' => '+263242700000',
            'roles' => ['business', 'owner'],
        ], $admin);

        DB::connection('tenant')->table('contact_points')
            ->where('party_id', $business->id)
            ->where('type', 'email')
            ->update(['verified_at' => now()]);

        if ($citizen) {
            PortalAccountLink::updateOrCreate(
                ['user_id' => $citizen->getKey(), 'customer_account_id' => $firstAccounts[0]->id],
                [
                    'relationship' => 'owner',
                    'status' => 'verified',
                    'verification_method' => 'demo_seed',
                    'requested_at' => now(),
                    'verified_at' => now(),
                ],
            );

            $this->audit->record(
                action: 'demo.portal_link.seeded',
                after: ['account' => $firstAccounts[0]->account_number, 'user' => $citizen->email],
                module: 'customer',
            );
        }
    }

    private function runBilling(User $admin, ?User $cashierUser, ?User $approver = null): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->toDateString();

        $result = [];

        $this->masterData->createTariff(
            'RATES-STD',
            'Residential property rates (DEMO)',
            'rates',
            'valuation',
            now()->startOfMonth()->toDateString(),
            [[
                'from_units' => 0,
                'to_units' => null,
                'rate_minor' => 200,
                'fixed_charge_minor' => 0,
                'minimum_charge_minor' => 500,
                'description' => '2% of valuation (DEMO only)',
            ]],
            'USD',
            $admin,
        );

        $water = $this->masterData->createTariff(
            'WATER-STD',
            'Residential water (DEMO)',
            'water',
            'metered',
            now()->startOfMonth()->toDateString(),
            [
                ['from_units' => 0, 'to_units' => 10, 'rate_minor' => 85, 'fixed_charge_minor' => 500, 'minimum_charge_minor' => 500, 'description' => 'First 10 m3'],
                ['from_units' => 10, 'to_units' => 20, 'rate_minor' => 120, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0, 'description' => 'Next 10 m3'],
                ['from_units' => 20, 'to_units' => null, 'rate_minor' => 180, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0, 'description' => 'Above 20 m3'],
            ],
            'USD',
            $admin,
        );

        $refuse = $this->masterData->createTariff(
            'REFUSE-STD',
            'Residential refuse (DEMO)',
            'refuse',
            'fixed',
            now()->startOfMonth()->toDateString(),
            [['from_units' => 0, 'to_units' => null, 'rate_minor' => 0, 'fixed_charge_minor' => 1000, 'minimum_charge_minor' => 1000, 'description' => 'Monthly refuse charge']],
            'USD',
            $admin,
        );

        foreach ([$water, $refuse] as $tariff) {
            $this->masterData->approveTariff($tariff, $admin);
        }

        $ratesTariff = TariffVersion::where('code', 'RATES-STD')->first();
        $this->masterData->approveTariff($ratesTariff, $admin);

        foreach (CustomerAccount::all() as $account) {
            $this->masterData->assignTariff($account, $ratesTariff, now()->startOfMonth()->toDateString());
            $this->masterData->assignTariff($account, $water, now()->startOfMonth()->toDateString());
            $this->masterData->assignTariff($account, $refuse, now()->startOfMonth()->toDateString());
        }

        foreach (['rates', 'water', 'refuse'] as $serviceType) {
            $run = $this->billing->preview($serviceType, $start, $end, [], $admin);
            $this->billing->approve($run, $approver ?? $admin);
            $posted = $this->billing->post($run, $approver ?? $admin);
            $result[$serviceType] = $posted->control_totals;
        }

        $firstBill = Bill::where('status', 'issued')->orderBy('bill_number')->first();

        if ($firstBill) {
            $intent = $this->payments->createIntent($firstBill->account, (int) $firstBill->balance_minor, 'web', 'sandbox', 'demo-intent', $cashierUser);
            $this->payments->settle($intent, 'succeeded', 'SBX-DEMO-0001', ['amount_minor' => $firstBill->balance_minor]);
            $result['payment'] = 'sandbox settlement recorded with receipt and allocation';
        }

        if ($cashierUser) {
            $session = $this->cashier->open($cashierUser, 0);
            $paid = Payment::first();

            if ($paid) {
                $nextBill = Bill::where('status', 'issued')
                    ->where('customer_account_id', '!=', $paid->customer_account_id)
                    ->orderBy('bill_number')
                    ->first();

                if ($nextBill) {
                    $cashPayment = $this->payments->captureCash($nextBill->account, (int) $nextBill->balance_minor, 'cash', 'DEMO-CASH-1', $session, $cashierUser);
                    $this->cashier->close($session, (int) $cashPayment->amount_minor, $cashierUser);
                    $result['cash'] = 'cash receipt captured in an open cashier session';
                } else {
                    $this->cashier->close($session, 0, $cashierUser);
                }
            } else {
                $this->cashier->close($session, 0, $cashierUser);
            }
        }

        return $result;
    }
}
