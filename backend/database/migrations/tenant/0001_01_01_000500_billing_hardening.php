<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_run_items', function (Blueprint $table) {
            $table->string('charge_type', 32)->nullable();
            $table->ulid('tariff_version_id')->nullable();
            $table->jsonb('calculation')->nullable();
        });

        // One active bill per account and billing period prevents double billing
        // even if a run is submitted twice concurrently.
        DB::statement('CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end) WHERE status <> \'void\'');

        Schema::table('receipts', function (Blueprint $table) {
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
        });

        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');

        Schema::table('billing_run_items', function (Blueprint $table) {
            $table->dropColumn(['charge_type', 'tariff_version_id', 'calculation']);
        });
    }
};
