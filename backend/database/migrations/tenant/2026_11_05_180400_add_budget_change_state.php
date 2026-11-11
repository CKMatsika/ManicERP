<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_budget_changes', function (Blueprint $table): void {
            $table->string('status', 24)->default('pending_approval');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_budget_changes', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
