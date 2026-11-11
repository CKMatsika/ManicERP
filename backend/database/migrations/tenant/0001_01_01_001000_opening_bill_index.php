<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Re-applies the opening-balance-aware duplicate guard for databases
        // that already ran the earlier billing index migration.
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement("CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <> 'void' AND origin = 'billing'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement("CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <> 'void'");
    }
};
