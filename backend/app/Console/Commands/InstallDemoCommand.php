<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Services\CouncilProvisioner;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Party;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions the clearly labelled DEMO council, demo roles/users and
 * demonstration operational data. Idempotent: reruns do not duplicate data.
 */
class InstallDemoCommand extends Command
{
    protected $signature = 'manicerp:demo
        {--password= : Password for every demonstration user}
        {--force-seed : Re-run the demo data seeder even if data exists}';

    protected $description = 'Provision the DEMO council and seed demonstration data (not production)';

    public function handle(
        CouncilProvisioner $provisioner,
        TenantManager $tenants,
        DemoDataSeeder $seeder,
    ): int {
        $password = (string) ($this->option('password') ?: env('DEMO_PASSWORD', 'Demo!Pass2026'));

        $council = Council::where('code', 'DEMO')->first();

        if (! $council) {
            $this->info('Provisioning DEMO council and its dedicated database...');

            $council = $provisioner->provision([
                'code' => 'DEMO',
                'name' => 'Demo City Council (DEMONSTRATION - not a real council)',
                'type' => 'urban',
                'is_demo' => true,
                'currency_code' => 'USD',
                'fiscal_year_start_month' => 1,
                'domains' => ['demo.manicerp.test', 'demo.localhost'],
            ]);
        } elseif ($council->status !== 'active') {
            $this->warn("DEMO council status is [{$council->status}]; attempting to migrate its database.");
            $provisioner->migrateTenant($council);
            $council->forceFill(['status' => 'active', 'provisioned_at' => now()])->save();
        }

        $tenants->connect($council);

        $accounts = [
            ['name' => 'Demo Administrator', 'email' => 'admin@demo.manicerp.test', 'role' => 'council_admin', 'title' => 'Town Clerk'],
            ['name' => 'Demo Finance Officer', 'email' => 'finance@demo.manicerp.test', 'role' => 'finance_officer', 'title' => 'Finance Officer'],
            ['name' => 'Demo Finance Approver', 'email' => 'approver@demo.manicerp.test', 'role' => 'finance_approver', 'title' => 'Chief Finance Officer'],
            ['name' => 'Demo Revenue Clerk', 'email' => 'clerk@demo.manicerp.test', 'role' => 'revenue_clerk', 'title' => 'Revenue Clerk'],
            ['name' => 'Demo Cashier', 'email' => 'cashier@demo.manicerp.test', 'role' => 'cashier', 'title' => 'Cashier'],
            ['name' => 'Demo Auditor', 'email' => 'auditor@demo.manicerp.test', 'role' => 'auditor', 'title' => 'Internal Auditor'],
        ];

        $users = [];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'locale' => 'en',
                ],
            );

            $role = Role::where('council_id', $council->id)->where('code', $account['role'])->firstOrFail();

            Membership::updateOrCreate(
                ['council_id' => $council->id, 'user_id' => $user->id, 'role_id' => $role->id],
                [
                    'member_type' => 'staff',
                    'status' => 'active',
                    'job_title' => $account['title'],
                    'started_at' => now(),
                    'revoked_at' => null,
                ],
            );

            $users[$account['role']] = $user;
        }

        $citizen = User::firstOrCreate(
            ['email' => 'citizen@example.com'],
            [
                'name' => 'Tendai Moyo (demo citizen)',
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );

        $citizenRole = Role::where('council_id', $council->id)->where('code', 'citizen')->firstOrFail();

        Membership::updateOrCreate(
            ['council_id' => $council->id, 'user_id' => $citizen->id, 'role_id' => $citizenRole->id],
            ['member_type' => 'citizen', 'status' => 'active', 'started_at' => now()],
        );

        $anything = Party::count();

        if ($anything === 0 || $this->option('force-seed')) {
            $this->info('Seeding demonstration data (chart, parties, properties, tariffs, billing, payments)...');

            $summary = $seeder->run($users['council_admin'], $users['cashier'], $citizen, $users['finance_approver']);

            foreach ($summary as $key => $value) {
                $this->line(sprintf('  %-12s %s', $key, is_array($value) ? json_encode($value) : $value));
            }
        } else {
            $this->info('Demonstration data already present; skipping seeder.');
        }

        $this->newLine();
        $this->info('DEMO council ready (DEMONSTRATION DATA ONLY - not a production implementation).');
        $this->table(['Role', 'Email', 'Password'], collect($accounts)->map(fn ($a) => [$a['role'], $a['email'], $password])->push(['citizen', 'citizen@example.com', $password])->all());
        $this->warn('Change or delete these demonstration credentials before any real deployment.');

        return self::SUCCESS;
    }
}
