<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Services\AcceptanceStepRunner;
use App\Modules\Platform\Services\CouncilProvisioner;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\AccountTariff;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\TariffVersion;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BootstrapAcceptanceCommand extends Command
{
    protected $signature = 'manicerp:acceptance:bootstrap {--credentials-file= : New synthetic actor credentials path, under backend/storage/app/acceptance} {--recover-undisclosed-credentials : Rotate only acceptance actors that have never signed in or enrolled MFA}';

    protected $description = 'Create or reuse the isolated local acceptance council and synthetic staff actors';

    public function handle(CouncilProvisioner $provisioner, TenantManager $tenants, DemoDataSeeder $seeder, AcceptanceStepRunner $steps, LicensingService $licensing, ModuleRegistry $modules): int
    {
        $credentialsPath = (string) $this->option('credentials-file');
        $expectedPrefix = rtrim(str_replace('\\', '/', storage_path('app/acceptance')), '/').'/';

        try {
            if (app()->environment() !== 'local' || config('database.connections.pgsql.database') !== 'manicerp_acceptance_central') {
                throw new RuntimeException('Acceptance bootstrap requires APP_ENV=local and DB_DATABASE=manicerp_acceptance_central.');
            }
            $normalizedCredentialsPath = str_replace('\\', '/', $credentialsPath);
            if ($credentialsPath === '' || ! str_starts_with(strtolower($normalizedCredentialsPath), strtolower($expectedPrefix))) {
                throw new RuntimeException('Supply a credentials file beneath backend/storage/app/acceptance.');
            }
            if (file_exists($credentialsPath)) {
                throw new RuntimeException('Credentials file already exists; choose a new local file. Existing files are never overwritten.');
            }

            $council = Council::query()->where('code', 'ACCEPTANCE_DEMO')->first();
            if (! $council) {
                $council = $provisioner->provision([
                    'code' => 'ACCEPTANCE_DEMO',
                    'name' => 'ManicERP Acceptance Demonstration Council (Synthetic)',
                    'type' => 'urban',
                    'is_demo' => true,
                    'currency_code' => 'USD',
                    'fiscal_year_start_month' => 1,
                ]);
            } elseif (! $council->is_demo || $council->database_name !== 'manicerp_acceptance_demo') {
                throw new RuntimeException('Existing ACCEPTANCE_DEMO directory entry does not match the isolated database guard.');
            } else {
                // Apply only pending tenant migrations; never rebuild or reset
                // an existing acceptance database.
                $provisioner->migrateTenant($council);
            }

            if (! $council->isActive() || ! $provisioner->databaseExists('manicerp_acceptance_demo')) {
                throw new RuntimeException('Acceptance council or its tenant database is not safely available.');
            }

            $definitions = [
                ['role' => 'council_admin', 'email' => 'admin@acceptance.test', 'name' => 'Synthetic Acceptance Administrator', 'title' => 'Acceptance Administrator'],
                ['role' => 'cashier', 'email' => 'cashier@acceptance.test', 'name' => 'Synthetic Acceptance Cashier', 'title' => 'Cashier'],
                ['role' => 'licensing_officer', 'email' => 'reviewer@acceptance.test', 'name' => 'Synthetic Licensing Reviewer', 'title' => 'Licensing Reviewer'],
                ['role' => 'licensing_inspector', 'email' => 'inspector@acceptance.test', 'name' => 'Synthetic Licensing Inspector', 'title' => 'Licensing Inspector'],
                ['role' => 'licensing_approver', 'email' => 'approver@acceptance.test', 'name' => 'Synthetic Independent Approver', 'title' => 'Licensing Approver'],
                ['role' => 'finance_approver', 'email' => 'finance-approver@acceptance.test', 'name' => 'Synthetic Finance Approver', 'title' => 'Finance Approver'],
            ];

            Role::updateOrCreate(
                ['council_id' => $council->id, 'code' => 'licensing_inspector'],
                ['name' => 'Licensing Inspector', 'description' => 'Inspection assignments and findings only; MFA required.', 'permissions' => ['licensing.applications.view', 'licensing.inspections.manage'], 'requires_mfa' => true, 'is_system' => true],
            );
            $newCredentials = [];
            $users = [];

            foreach ($definitions as $definition) {
                $user = User::query()->where('email', $definition['email'])->first();
                if (! $user) {
                    $password = Str::password(32, symbols: true);
                    $user = User::create([
                        'name' => $definition['name'],
                        'email' => $definition['email'],
                        'password' => Hash::make($password),
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ]);
                    $newCredentials[] = ['email' => $user->email, 'password' => $password, 'role' => $definition['role']];
                } elseif ($this->option('recover-undisclosed-credentials')) {
                    if ($user->last_login_at !== null || $user->mfa_confirmed_at !== null || $user->mfa_secret !== null) {
                        throw new RuntimeException('Cannot recover credentials after an acceptance actor has signed in or enrolled MFA.');
                    }
                    $password = Str::password(32, symbols: true);
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    $newCredentials[] = ['email' => $user->email, 'password' => $password, 'role' => $definition['role']];
                }
                $role = Role::query()->where('council_id', $council->id)->where('code', $definition['role'])->firstOrFail();
                Membership::firstOrCreate(
                    ['council_id' => $council->id, 'user_id' => $user->id, 'role_id' => $role->id],
                    ['member_type' => 'staff', 'status' => 'active', 'job_title' => $definition['title'], 'started_at' => now()],
                );
                $users[$definition['role']] = $user;
            }

            if ($newCredentials !== []) {
                if (! is_dir(dirname($credentialsPath)) && ! mkdir(dirname($credentialsPath), 0700, true) && ! is_dir(dirname($credentialsPath))) {
                    throw new RuntimeException('Could not create the local credentials directory.');
                }
                $json = json_encode([
                    'label' => 'Synthetic local acceptance credentials; enroll MFA through the normal sign-in flow.',
                    'council_code' => $council->code,
                    'actors' => $newCredentials,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (file_put_contents($credentialsPath, $json, LOCK_EX) === false) {
                    throw new RuntimeException('Could not save new credentials to the requested local file.');
                }
            }

            $tenants->connect($council);
            $steps->run('acceptance.master_data.v1', ['council' => $council->code, 'schema' => 1], function () use ($seeder, $users): array {
                $recoveredPartial = Party::query()->exists();
                if (! $recoveredPartial) {
                    $seeder->run($users['council_admin'], $users['cashier'], null, $users['finance_approver']);
                }
                $citizenParty = Party::query()->where('display_name', 'Tendai Moyo')->first();
                if (! $citizenParty || ! CustomerAccount::query()->where('primary_party_id', $citizenParty->id)->exists()) {
                    throw new RuntimeException('Acceptance master data is partial and the synthetic citizen account cannot be safely identified; existing records were preserved.');
                }
                $citizenParty->contacts()->where('type', 'email')->where('is_primary', true)->update(['value' => 'citizen@acceptance.test', 'verified_at' => now()]);
                if (! $citizenParty->contacts()->where('type', 'email')->where('value', 'citizen@acceptance.test')->whereNotNull('verified_at')->exists()) {
                    throw new RuntimeException('Verified synthetic customer email could not be safely established.');
                }
                $requiredTariffs = ['RATES-STD', 'WATER-STD', 'REFUSE-STD'];
                $tariffs = TariffVersion::query()->whereIn('code', $requiredTariffs)->where('status', 'approved')->get();
                if ($tariffs->count() !== count($requiredTariffs) || CustomerAccount::query()->count() < 3 || AccountTariff::query()->count() < 9 || ! BankAccount::query()->where('code', 'BANK1')->where('status', 'active')->exists()) {
                    throw new RuntimeException('Acceptance master data is incomplete (approved tariffs, account assignments or demonstration bank missing). No records were deleted or reseeded.');
                }

                return ['recovered_partial_seed' => $recoveredPartial, 'parties' => Party::query()->count(), 'properties' => DB::connection('tenant')->table('properties')->count(), 'accounts' => CustomerAccount::query()->count(), 'approved_tariffs' => $tariffs->count(), 'tariff_assignments' => AccountTariff::query()->count(), 'bank_account' => 'BANK1'];
            });
            $steps->run('acceptance.licensing_demo_configuration.v1', ['council' => $council->code, 'type' => 'DEMO_TRADER', 'version' => 1], function () use ($licensing): array {
                // This is a local-only, explicitly demo-scoped setup command;
                // user-facing configuration remains permission-gated.
                $type = $licensing->seedDemonstrationType();

                return ['type_id' => $type->id, 'code' => $type->code, 'versions' => $type->versions()->count(), 'status' => 'demonstration only'];
            });
            $steps->run('acceptance.module.procurement.v1', ['council' => $council->code, 'module' => 'procurement'], function () use ($modules, $council, $users): array {
                if (! $modules->enabled($council, 'procurement')) {
                    if (! $modules->canEnable($council, 'procurement')) {
                        throw new RuntimeException('The isolated acceptance council cannot enable procurement because dependencies are disabled.');
                    }
                    $modules->setEnabled($council, 'procurement', true, $users['council_admin']->getKey());
                }

                return ['module' => 'procurement', 'enabled' => $modules->enabled($council, 'procurement')];
            });

            $this->info('Isolated synthetic acceptance council ready.');
            $this->line('Council: ACCEPTANCE_DEMO / manicerp_acceptance_demo');
            $this->line('Synthetic citizen registration email: citizen@acceptance.test');
            $this->line('New credentials saved locally: '.($newCredentials ? $credentialsPath : 'none (all actors already existed)'));
            $this->warn('Privileged actors must enroll MFA through standard sign-in. Credentials are not printed. Existing data was not reset.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
