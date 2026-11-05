<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingStandardsService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\CouncilDomain;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a council directory entry, its dedicated database, runs tenant
 * migrations and seeds baseline reference data. Failures are recorded rather
 * than silently reported as success.
 */
class CouncilProvisioner
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ModuleRegistry $modules,
        private readonly DefaultRoles $roles,
        private readonly PlatformAuditLogger $platformAudit,
    ) {}

    /**
     * @param  array{code: string, name: string, type?: string, is_demo?: bool, currency_code?: string, fiscal_year_start_month?: int, timezone?: string, domains?: array<int, string>}  $attributes
     */
    public function provision(array $attributes, ?User $actor = null): Council
    {
        $code = strtoupper(trim($attributes['code']));

        if (! preg_match('/^[A-Z][A-Z0-9_]{2,20}$/', $code)) {
            throw new \DomainException('Council code must be 3-21 characters: letters, digits or underscore.');
        }

        if (Council::where('code', $code)->exists()) {
            throw new \DomainException("Council code [{$code}] already exists.");
        }

        $databaseName = $this->databaseNameFor($code);

        if ($this->databaseExists($databaseName)) {
            throw new \DomainException("Database [{$databaseName}] already exists; refusing to overwrite it.");
        }

        $this->createDatabase($databaseName);

        $council = Council::create([
            'code' => $code,
            'name' => $attributes['name'],
            'type' => $attributes['type'] ?? 'urban',
            'status' => 'provisioning',
            'database_name' => $databaseName,
            'currency_code' => $attributes['currency_code'] ?? config('manicerp.default_currency', 'USD'),
            'timezone' => $attributes['timezone'] ?? 'Africa/Harare',
            'fiscal_year_start_month' => $attributes['fiscal_year_start_month'] ?? 1,
            'is_demo' => $attributes['is_demo'] ?? false,
        ]);

        try {
            foreach ($attributes['domains'] ?? [] as $host) {
                CouncilDomain::create([
                    'council_id' => $council->id,
                    'host' => strtolower($host),
                    'is_primary' => false,
                    'verified_at' => now(),
                ]);
            }

            $this->roles->seed($council);
            $this->seedModules($council);
            $this->migrateTenant($council);
            $this->seedBaseline($council);

            $council->status = 'active';
            $council->provisioned_at = now();
            $council->save();

            $this->platformAudit->record(
                action: 'council.provisioned',
                councilId: $council->id,
                subjectType: Council::class,
                subjectId: $council->id,
                after: ['code' => $code, 'database' => $databaseName, 'demo' => $council->is_demo],
            );

            return $council;
        } catch (Throwable $exception) {
            $council->status = 'provisioning_failed';
            $council->save();

            $this->platformAudit->record(
                action: 'council.provisioning_failed',
                councilId: $council->id,
                subjectType: Council::class,
                subjectId: $council->id,
                after: ['code' => $code, 'error' => Str::limit($exception->getMessage(), 500)],
            );

            throw $exception;
        }
    }

    public function migrateTenant(Council $council): void
    {
        $this->tenants->connect($council, null, true);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    public function databaseNameFor(string $code): string
    {
        return 'manicerp_'.strtolower(Str::slug($code, '_'));
    }

    public function databaseExists(string $name): bool
    {
        $this->assertSafeIdentifier($name);

        return (bool) DB::connection('pgsql')
            ->selectOne('select 1 as found from pg_database where datname = ?', [$name]);
    }

    public function createDatabase(string $name): void
    {
        $this->assertSafeIdentifier($name);

        if ($this->databaseExists($name)) {
            return;
        }

        DB::connection('pgsql')->getPdo()
            ->exec(sprintf('CREATE DATABASE "%s"', $name));
    }

    public function dropDatabase(string $name): void
    {
        $this->assertSafeIdentifier($name);

        DB::connection('pgsql')->getPdo()
            ->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
    }

    private function seedModules(Council $council): void
    {
        foreach ($this->modules->manifests() as $id => $manifest) {
            $enabled = $this->modules->isCore($id)
                || (bool) ($manifest['available'] ?? false);

            $council->modules()->updateOrCreate(
                ['module_id' => $id],
                [
                    'status' => $enabled ? 'enabled' : 'disabled',
                    'enabled_at' => $enabled ? now() : null,
                ],
            );
        }
    }

    private function seedBaseline(Council $council): void
    {
        $this->tenants->connect($council, null, true);

        $dimensions = [
            'fund' => 'Funds',
            'vote' => 'Votes / heads',
            'programme' => 'Programmes',
            'subprogramme' => 'Subprogrammes',
            'project' => 'Projects',
            'department' => 'Departments',
            'cost_centre' => 'Cost centres',
            'ward' => 'Wards / locations',
            'function' => 'Functions',
            'funding_source' => 'Funding sources',
            'economic_item' => 'Economic items',
        ];

        foreach ($dimensions as $code => $name) {
            DB::connection('tenant')->table('dimensions')->updateOrInsert(
                ['code' => $code],
                ['id' => (string) Str::ulid(), 'name' => $name, 'is_required' => in_array($code, ['fund', 'vote'], true), 'created_at' => now(), 'updated_at' => now()],
            );
        }

        if ($this->modules->enabled($council, 'accounting')) {
            $year = CarbonImmutable::now($council->timezone)->year;
            $startMonth = (int) $council->fiscal_year_start_month;

            $yearStart = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
            $yearEnd = $yearStart->addYear()->subDay();

            $financialYear = DB::connection('tenant')->table('financial_years')->updateOrInsert(
                ['code' => (string) $year],
                [
                    'id' => (string) Str::ulid(),
                    'start_date' => $yearStart->toDateString(),
                    'end_date' => $yearEnd->toDateString(),
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $yearRow = DB::connection('tenant')->table('financial_years')->where('code', (string) $year)->first();

            for ($i = 0; $i < 12; $i++) {
                $periodStart = $yearStart->addMonths($i);
                $periodEnd = $periodStart->addMonth()->subDay();

                DB::connection('tenant')->table('financial_periods')->updateOrInsert(
                    ['code' => $yearStart->format('Y').'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                    [
                        'id' => (string) Str::ulid(),
                        'financial_year_id' => $yearRow->id,
                        'sequence' => $i + 1,
                        'start_date' => $periodStart->toDateString(),
                        'end_date' => $periodEnd->toDateString(),
                        'status' => 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        DB::connection('tenant')->table('settings')->updateOrInsert(
            ['key' => 'council.billing'],
            [
                'value' => json_encode([
                    'default_billing_day' => 1,
                    'currency' => $council->currency_code,
                    'estimated_reading_policy' => 'average_last_three_actual',
                ]),
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        if ($this->modules->enabled($council, 'accounting')) {
            app(AccountingStandardsService::class)->seedDefaults();
        }
    }

    private function assertSafeIdentifier(string $name): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{2,62}$/', $name)) {
            throw new \RuntimeException('Unsafe database identifier.');
        }
    }
}
