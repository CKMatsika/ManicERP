<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\FinancialYear;
use App\Modules\Accounting\Models\PostingRule;
use App\Modules\Accounting\Services\ChartImportService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\CouncilModule;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\Property;
use App\Modules\Revenue\Models\TariffVersion;
use App\Modules\Revenue\Models\Ward;
use App\Modules\Revenue\Services\MasterDataService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait BuildsMunicipalContext
{
    protected function makeCouncil(array $overrides = []): Council
    {
        $council = Council::create([
            'code' => $overrides['code'] ?? 'TEST',
            'name' => $overrides['name'] ?? 'Test City Council',
            'type' => 'urban',
            'status' => 'active',
            'database_name' => $overrides['database_name'] ?? $this->tenantDatabase(),
            'currency_code' => 'USD',
            'timezone' => 'Africa/Harare',
            'fiscal_year_start_month' => 1,
            'is_demo' => false,
            'provisioned_at' => now(),
        ]);

        $this->enableModules($council);

        return $council;
    }

    protected function enableModules(Council $council): void
    {
        foreach (config('modules.modules') as $id => $manifest) {
            $enabled = (bool) ($manifest['core'] ?? false) || (bool) ($manifest['available'] ?? false);

            CouncilModule::updateOrCreate(
                ['council_id' => $council->id, 'module_id' => $id],
                ['status' => $enabled ? 'enabled' : 'disabled', 'enabled_at' => $enabled ? now() : null],
            );
        }
    }

    protected function makeUser(string $email, string $name = 'Test User'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Secret!12345'),
            'status' => 'active',
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
    }

    protected function makeRole(Council $council, string $code, array $permissions, bool $requiresMfa = false): Role
    {
        return Role::updateOrCreate(
            ['council_id' => $council->id, 'code' => $code],
            [
                'name' => ucfirst(str_replace('_', ' ', $code)),
                'permissions' => $permissions,
                'requires_mfa' => $requiresMfa,
                'is_system' => true,
            ],
        );
    }

    protected function makeMembership(User $user, Council $council, Role $role, string $type = 'staff'): Membership
    {
        return Membership::create([
            'council_id' => $council->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'member_type' => $type,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    protected function useCouncil(Council $council, ?Membership $membership = null): void
    {
        app(TenantManager::class)->useContext($council, $membership);
    }

    protected function actingAsStaff(User $user, Council $council, Membership $membership): static
    {
        $this->useCouncil($council, $membership);
        $this->actingAs($user);
        session([
            'council_code' => $council->code,
            'mfa_verified_user' => $user->id,
            'mfa_verified_at' => time(),
        ]);

        return $this;
    }

    protected function actingAsCitizen(User $user, Council $council, Membership $membership): static
    {
        $this->useCouncil($council, $membership);
        $this->actingAs($user);
        session(['council_code' => $council->code]);

        return $this;
    }

    protected function seedAccounting(Council $council, User $admin, string $year = '2026'): void
    {
        $this->useCouncil($council);

        foreach (['fund', 'vote', 'programme', 'project', 'department', 'cost_centre', 'ward', 'function', 'funding_source', 'economic_item'] as $dimension) {
            DB::connection('tenant')->table('dimensions')->updateOrInsert(
                ['code' => $dimension],
                ['id' => (string) Str::ulid(), 'name' => ucfirst($dimension), 'is_required' => false, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $wardId = DB::connection('tenant')->table('dimensions')->where('code', 'ward')->value('id');
        DB::connection('tenant')->table('dimension_values')->updateOrInsert(
            ['dimension_id' => $wardId, 'code' => 'W01'],
            ['id' => (string) Str::ulid(), 'name' => 'Ward 1', 'created_at' => now(), 'updated_at' => now()],
        );

        $start = CarbonImmutable::create((int) $year, 1, 1);

        $financialYear = FinancialYear::create([
            'code' => $year,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addYear()->subDay()->toDateString(),
            'status' => 'open',
        ]);

        for ($i = 0; $i < 12; $i++) {
            $periodStart = $start->addMonths($i);
            FinancialPeriod::create([
                'financial_year_id' => $financialYear->id,
                'sequence' => $i + 1,
                'code' => $year.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodStart->addMonth()->subDay()->toDateString(),
                'status' => 'open',
            ]);
        }

        if (! ChartVersion::where('status', 'active')->exists()) {
            $service = app(ChartImportService::class);
            $batch = $service->importContents(
                'demo_chart.csv',
                file_get_contents(database_path('seeders/data/demo_chart.csv')),
                'csv',
                ['name' => 'Test chart', 'source_type' => 'demo'],
                $admin,
            );
            $service->approve($batch, $admin);
        }

        foreach ([
            ['billing.rates', '1310', '4010'],
            ['billing.water', '1320', '4020'],
            ['billing.refuse', '1320', '4030'],
            ['billing.rental', '1330', '4040'],
        ] as [$event, $debit, $credit]) {
            PostingRule::updateOrCreate(
                ['event_code' => $event],
                [
                    'name' => $event,
                    'debit_account_code' => $debit,
                    'credit_account_code' => $credit,
                    'required_dimensions' => [],
                    'dimensions' => [],
                    'status' => 'approved',
                    'version' => 1,
                    'effective_from' => $start->toDateString(),
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array{party: Party, property: Property, account: CustomerAccount}
     */
    protected function makeCustomer(Council $council, User $admin, array $options = []): array
    {
        $this->useCouncil($council);
        $services = app(MasterDataService::class);

        $ward = Ward::updateOrCreate(
            ['code' => $options['ward'] ?? 'W01'],
            ['name' => 'Ward 1', 'suburb' => 'Testville'],
        );

        $party = $services->createParty([
            'party_type' => 'individual',
            'display_name' => $options['name'] ?? 'Test Customer',
            'email' => $options['email'] ?? 'customer'.Str::lower(Str::random(5)).'@example.com',
            'phone' => '+263771000099',
            'roles' => ['resident', 'owner'],
        ], $admin);

        DB::connection('tenant')->table('contact_points')
            ->where('party_id', $party->id)
            ->where('type', 'email')
            ->update(['verified_at' => now()]);

        $property = $services->createProperty([
            'parcel_number' => $options['parcel'] ?? 'TEST-'.Str::upper(Str::random(6)),
            'stand_number' => 'Stand 1',
            'ward_code' => $ward->code,
            'address_line' => '1 Test Avenue',
            'suburb' => 'Testville',
            'area_sqm' => '700.00',
        ], $admin);

        $valuation = $services->recordValuation($property, '2025-06-01', 4_000_000, 6_000_000, $admin);
        $services->approveValuation($valuation, $admin);

        $account = $services->createAccount($property, $party, $options['account_type'] ?? 'rates', $admin);

        return ['party' => $party, 'property' => $property, 'account' => $account];
    }

    protected function makeTariff(Council $council, User $admin, string $service = 'water', array $bands = []): TariffVersion
    {
        $this->useCouncil($council);
        $services = app(MasterDataService::class);

        $defaultBands = $service === 'water'
            ? [
                ['from_units' => 0, 'to_units' => 10, 'rate_minor' => 85, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0],
                ['from_units' => 10, 'to_units' => 20, 'rate_minor' => 120, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0],
                ['from_units' => 20, 'to_units' => null, 'rate_minor' => 180, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0],
            ]
            : [['from_units' => 0, 'to_units' => null, 'rate_minor' => 200, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0]];

        $tariff = $services->createTariff(
            strtoupper($service).'-TEST',
            ucfirst($service).' test tariff',
            $service,
            $service === 'rates' ? 'valuation' : ($service === 'refuse' ? 'fixed' : 'metered'),
            '2026-01-01',
            $bands ?: $defaultBands,
            'USD',
            $admin,
        );

        return $services->approveTariff($tariff, $admin);
    }

    protected function money(string $amount, string $currency = 'USD'): int
    {
        return Decimal::toScaled($amount, 3) === 0 ? 0 : (int) round(((float) $amount) * 100);
    }
}
