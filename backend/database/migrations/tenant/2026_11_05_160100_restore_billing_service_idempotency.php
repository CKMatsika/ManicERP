<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') { DB::statement('DROP INDEX IF EXISTS bills_active_period_unique'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement("CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <> 'void' AND origin = 'billing' AND licence_application_key IS NULL"); }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') { DB::statement('DROP INDEX IF EXISTS bills_active_period_unique'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement("CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end) WHERE status <> 'void'"); }
    }
};
