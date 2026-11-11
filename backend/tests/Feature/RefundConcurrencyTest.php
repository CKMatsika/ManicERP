<?php

namespace Tests\Feature;

use App\Modules\Platform\Services\CouncilProvisioner;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class RefundConcurrencyTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    public function test_two_processes_contend_on_one_refund_and_only_one_approval_commits(): void
    {
        $suffix = strtolower(Str::random(12));
        $database = 'manicerp_test_refund_race_'.$suffix;
        $provisioner = app(CouncilProvisioner::class);
        $created = false;
        $council = null;
        $users = [];
        $processes = [];
        DB::connection('pgsql')->commit();
        try {
            $this->assertFalse($provisioner->databaseExists($database));
            $provisioner->createDatabase($database);
            $created = true;
            $council = $this->makeCouncil(['code' => 'RACE'.strtoupper($suffix), 'database_name' => $database]);
            $provisioner->migrateTenant($council);
            $role = $this->makeRole($council, 'race_finance', ['*']);
            foreach (['preparer', 'first', 'second'] as $name) {
                $users[$name] = $this->makeUser($name.$suffix.'@refund-race.test');
                $this->makeMembership($users[$name], $council, $role);
            }
            $customer = $this->makeCustomer($council, $users['preparer']);
            AccountCredit::create(['customer_account_id' => $customer['account']->id, 'type' => 'unapplied', 'source' => 'test',
                'amount_minor' => 10000, 'remaining_minor' => 10000, 'currency_code' => 'USD', 'effective_date' => '2026-01-01', 'status' => 'open']);
            $refund = app(RefundService::class)->request($customer['account']->id, 3000, requester: $users['preparer'], idempotencyKey: 'race-'.$suffix);

            $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => config('database.connections.pgsql.host'),
                'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_DATABASE' => 'manicerp_test_central',
                'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '',
                'TENANT_DB_HOST' => config('database.connections.tenant.host'), 'TENANT_DB_PORT' => (string) config('database.connections.tenant.port'),
                'TENANT_DB_USERNAME' => config('database.connections.tenant.username'), 'TENANT_DB_PASSWORD' => config('database.connections.tenant.password') ?? '',
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
            $input = new InputStream;
            $marker = 'refund-race-'.$suffix;
            $first = new Process([PHP_BINARY, base_path('tests/Support/approve_refund.php'), $council->id, $users['first']->id, $refund->id, 'hold', $marker.'-a'], base_path(), $env, $input, 20);
            $processes[] = $first;
            $first->start();
            $this->assertTrue($first->waitUntil(fn ($type, $out) => str_contains($out, 'locked')), $first->getErrorOutput());
            $second = new Process([PHP_BINARY, base_path('tests/Support/approve_refund.php'), $council->id, $users['second']->id, $refund->id, 'compete', $marker.'-b'], base_path(), $env, null, 20);
            $processes[] = $second;
            $second->start();
            $this->assertTrue($second->waitUntil(fn ($type, $out) => str_contains($out, 'attempting')), $second->getErrorOutput());
            $blocked = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $blocked = (bool) DB::connection('pgsql')->selectOne("SELECT 1 AS blocked FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$marker.'-b']);
                if ($blocked) {
                    break;
                }
                usleep(20000);
            }
            $this->assertTrue($blocked, 'Second approval never contended on the held refund row.');
            $input->write("go\n");
            $input->close();
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->assertStringContainsString('approved', $first->getOutput());
            $this->assertStringContainsString('rejected', $second->getOutput());
            $this->assertSame($users['first']->id, Refund::findOrFail($refund->id)->approved_by);
            $this->assertSame(1, DB::connection('tenant')->table('audit_events')->where('action', 'revenue.refund.approved')->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            app(TenantManager::class)->clear();
            if ($council) {
                DB::connection('pgsql')->table('council_memberships')->where('council_id', $council->id)->delete();
                DB::connection('pgsql')->table('councils')->where('id', $council->id)->delete();
            }
            foreach ($users as $user) {
                DB::connection('pgsql')->table('users')->where('id', $user->id)->delete();
            }
            if ($created && preg_match('/^manicerp_test_refund_race_[a-z0-9]{12}$/', $database)) {
                $provisioner->dropDatabase($database);
            }
        }
    }
}
