<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /** Isolation tests that switch between physical databases disable the tenant transaction. */
    protected bool $useTenantTransaction = true;

    protected static bool $databasesPrepared = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->prepareDatabases();

        DB::connection('pgsql')->beginTransaction();

        if ($this->useTenantTransaction) {
            config(['database.connections.tenant.database' => $this->tenantDatabase()]);
            DB::purge('tenant');
            DB::connection('tenant')->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->useTenantTransaction && DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        while (DB::connection('pgsql')->transactionLevel() > 0) {
            DB::connection('pgsql')->rollBack();
        }

        parent::tearDown();
    }

    protected function tenantDatabase(): string
    {
        return (string) env('TENANT_DB_DATABASE', 'manicerp_test_demo');
    }

    protected function secondaryTenantDatabase(): string
    {
        return 'manicerp_test_demo_b';
    }

    protected function prepareDatabases(): void
    {
        if (static::$databasesPrepared) {
            return;
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'pgsql',
            '--path' => 'database/migrations/central',
            '--force' => true,
        ]);

        foreach ([$this->tenantDatabase(), $this->secondaryTenantDatabase()] as $database) {
            config(['database.connections.tenant.database' => $database]);
            DB::purge('tenant');

            Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        }

        config(['database.connections.tenant.database' => $this->tenantDatabase()]);
        DB::purge('tenant');

        static::$databasesPrepared = true;
    }
}
