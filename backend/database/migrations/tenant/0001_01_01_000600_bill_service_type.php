<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('service_type', 24)->nullable()->after('billing_run_id');
        });

        DB::statement('UPDATE bills SET service_type = \'rates\' WHERE service_type IS NULL');

        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement('CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <> \'void\'');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement('CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end) WHERE status <> \'void\'');

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
