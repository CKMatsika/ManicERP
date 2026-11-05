<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('councils', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 24)->unique();
            $table->string('name');
            $table->string('type', 24)->default('urban');
            $table->string('status', 24)->default('active');
            $table->string('database_name', 64)->unique();
            $table->string('currency_code', 3)->default('USD');
            $table->string('timezone', 64)->default('Africa/Harare');
            $table->unsignedSmallInteger('fiscal_year_start_month')->default(1);
            $table->boolean('is_demo')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('council_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('council_id')->constrained('councils')->cascadeOnDelete();
            $table->string('host', 191)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('council_modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('council_id')->constrained('councils')->cascadeOnDelete();
            $table->string('module_id', 64);
            $table->string('status', 16)->default('enabled');
            $table->jsonb('settings')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->ulid('enabled_by')->nullable();
            $table->timestamps();

            $table->unique(['council_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_modules');
        Schema::dropIfExists('council_domains');
        Schema::dropIfExists('councils');
    }
};
