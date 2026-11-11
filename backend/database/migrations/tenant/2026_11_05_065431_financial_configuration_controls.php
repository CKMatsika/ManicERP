<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_definitions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'version']);
            $table->ulid('created_by')->nullable();
        });
        Schema::table('revenue_policies', fn (Blueprint $table) => $table->ulid('created_by')->nullable());
        Schema::table('report_line_mappings', fn (Blueprint $table) => $table->ulid('created_by')->nullable());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_line_mappings', fn (Blueprint $table) => $table->dropColumn('created_by'));
        Schema::table('revenue_policies', fn (Blueprint $table) => $table->dropColumn('created_by'));
        Schema::table('report_definitions', function (Blueprint $table) {
            $table->dropUnique(['code', 'version']);
            $table->dropColumn('created_by');
        });
    }
};
