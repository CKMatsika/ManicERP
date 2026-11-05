<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('suburb')->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cost_centres', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 24)->unique();
            $table->string('party_type', 16)->default('individual');
            $table->string('display_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('registration_number', 64)->nullable();
            $table->string('tax_number', 64)->nullable();
            $table->string('national_id', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index('display_name');
            $table->index('registration_number');
        });

        Schema::create('party_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('role', 32);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['party_id', 'role', 'effective_from']);
            $table->index('role');
        });

        Schema::create('contact_points', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('value', 191);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'value']);
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('parcel_number', 40)->unique();
            $table->string('stand_number', 40)->nullable();
            $table->foreignUlid('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('address_line')->nullable();
            $table->string('suburb')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->decimal('area_sqm', 14, 2)->nullable();
            $table->string('land_use', 32)->default('residential');
            $table->string('status', 16)->default('active');
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index('stand_number');
            $table->index('suburb');
        });

        Schema::create('property_valuations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('valuation_date');
            $table->bigInteger('land_value_minor')->default(0);
            $table->bigInteger('improvements_value_minor')->default(0);
            $table->bigInteger('total_value_minor')->default(0);
            $table->string('valuer')->nullable();
            $table->string('roll_reference')->nullable();
            $table->string('status', 16)->default('draft');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'valuation_date']);
        });

        Schema::create('ownership_interests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignUlid('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('interest_type', 16)->default('owner');
            $table->decimal('share_pct', 5, 2)->default(100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->ulid('evidence_document_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'party_id', 'interest_type', 'effective_from']);
            $table->index(['property_id', 'status']);
        });

        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('account_number', 24)->unique();
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignUlid('primary_party_id')->constrained('parties')->restrictOnDelete();
            $table->string('account_type', 24)->default('rates');
            $table->string('status', 16)->default('active');
            $table->date('opened_on');
            $table->date('closed_on')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedSmallInteger('billing_day')->default(1);
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index('primary_party_id');
            $table->index('property_id');
        });

        Schema::create('account_liabilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->foreignUlid('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('liability_type', 16)->default('primary');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['customer_account_id', 'party_id', 'liability_type', 'effective_from']);
        });

        Schema::create('service_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('connection_number', 24)->unique();
            $table->string('service_type', 24);
            $table->string('status', 24)->default('active');
            $table->date('installed_on')->nullable();
            $table->date('disconnected_on')->nullable();
            $table->string('route_code', 24)->nullable();
            $table->timestamps();

            $table->index('service_type');
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('service_connection_id')->constrained('service_connections')->cascadeOnDelete();
            $table->string('serial_number', 64)->unique();
            $table->string('meter_type', 24)->default('water');
            $table->unsignedSmallInteger('digits')->default(6);
            $table->date('installed_on')->nullable();
            $table->date('removed_on')->nullable();
            $table->string('status', 16)->default('active');
            $table->decimal('initial_reading', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meter_id')->constrained('meters')->restrictOnDelete();
            $table->foreignUlid('service_connection_id')->constrained('service_connections')->restrictOnDelete();
            $table->timestamp('read_at');
            $table->decimal('reading', 14, 3);
            $table->decimal('previous_reading', 14, 3)->nullable();
            $table->decimal('consumption', 14, 3)->nullable();
            $table->string('source', 16)->default('actual');
            $table->string('status', 16)->default('captured');
            $table->ulid('photo_document_id')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->ulid('captured_by')->nullable();
            $table->boolean('abnormal_flag')->default(false);
            $table->string('abnormal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meter_id', 'read_at']);
            $table->index(['service_connection_id', 'status']);
        });

        Schema::create('tariff_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32);
            $table->string('name');
            $table->string('service_type', 24);
            $table->string('charge_basis', 24)->default('metered');
            $table->string('status', 16)->default('draft');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedInteger('version_no')->default(1);
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version_no']);
            $table->index(['service_type', 'status']);
        });

        Schema::create('tariff_bands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tariff_version_id')->constrained('tariff_versions')->cascadeOnDelete();
            $table->unsignedSmallInteger('band_no');
            $table->decimal('from_units', 14, 3)->default(0);
            $table->decimal('to_units', 14, 3)->nullable();
            $table->bigInteger('rate_minor')->default(0);
            $table->bigInteger('fixed_charge_minor')->default(0);
            $table->bigInteger('minimum_charge_minor')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['tariff_version_id', 'band_no']);
        });

        Schema::create('account_tariffs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->string('service_type', 24);
            $table->foreignUlid('tariff_version_id')->constrained('tariff_versions')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_tariffs');
        Schema::dropIfExists('tariff_bands');
        Schema::dropIfExists('tariff_versions');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('service_connections');
        Schema::dropIfExists('account_liabilities');
        Schema::dropIfExists('customer_accounts');
        Schema::dropIfExists('ownership_interests');
        Schema::dropIfExists('property_valuations');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('contact_points');
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('cost_centres');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('wards');
    }
};
