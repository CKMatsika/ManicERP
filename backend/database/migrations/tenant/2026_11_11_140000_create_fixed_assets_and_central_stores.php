<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asset Categories (IPSAS 17 Property, Plant and Equipment)
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('depreciation_method', 32)->default('straight_line'); // straight_line, reducing_balance, none
            $table->integer('useful_life_years')->default(10);
            $table->string('gl_asset_account', 32)->default('1200');
            $table->string('gl_depreciation_expense_account', 32)->default('5100');
            $table->string('gl_accumulated_depreciation_account', 32)->default('1290');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Fixed Assets Register
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('asset_number', 48)->unique(); // e.g. AST-2026-0001
            $table->foreignUlid('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('serial_or_vin', 100)->nullable();
            $table->string('department', 80); // Health & Environment, Engineering & Works, Housing, Treasury, etc.
            $table->string('custodian_name', 120)->nullable();
            $table->string('location_name', 150); // e.g. Central Mechanical Workshop, Dangamvura Reservoir
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->date('acquisition_date');
            $table->bigInteger('purchase_cost_minor');
            $table->bigInteger('residual_value_minor')->default(0);
            $table->bigInteger('current_carrying_amount_minor'); // Net Book Value
            $table->bigInteger('accumulated_depreciation_minor')->default(0);
            $table->string('condition', 32)->default('good'); // excellent, good, fair, poor, scrap
            $table->string('status', 32)->default('in_service'); // in_service, under_maintenance, decommissioned, disposed
            $table->date('last_audit_date')->nullable();
            $table->string('qr_code_data', 255)->nullable();
            $table->timestamps();

            $table->index(['asset_category_id', 'status']);
            $table->index('department');
        });

        // 3. Asset Depreciation Run Logs
        Schema::create('asset_depreciation_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('financial_period', 16); // e.g. 2026-11
            $table->bigInteger('depreciation_amount_minor');
            $table->bigInteger('net_book_value_before_minor');
            $table->bigInteger('net_book_value_after_minor');
            $table->ulid('journal_entry_id')->nullable();
            $table->timestamps();

            $table->index(['fixed_asset_id', 'financial_period']);
        });

        // 4. Central Stores Catalog
        Schema::create('stores_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('item_code', 48)->unique(); // e.g. STK-WTR-001
            $table->string('name', 180);
            $table->string('category', 80); // Water Reticulation, Chemicals, Roads, Spares, Electrical, Protective
            $table->string('unit_of_measure', 32); // meter, unit, 50kg bag, drum, ton
            $table->integer('reorder_level')->default(10);
            $table->integer('reorder_quantity')->default(50);
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->bigInteger('average_unit_cost_minor')->default(0);
            $table->bigInteger('total_valuation_minor')->default(0);
            $table->string('bin_location', 60)->nullable(); // Aisle 3, Rack B
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('category');
        });

        // 5. Stores Transactions (GRVs, Issues, Adjustments)
        Schema::create('stores_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('transaction_number', 48)->unique(); // GRV-2026-0001 or SIN-2026-0001
            $table->string('type', 24); // grv, issue, adjustment
            $table->foreignUlid('stores_item_id')->constrained('stores_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost_minor');
            $table->bigInteger('total_cost_minor');
            $table->string('department_recipient', 80)->nullable();
            $table->string('work_order_ref', 80)->nullable();
            $table->string('authorized_by', 120)->nullable();
            $table->string('issued_by', 120)->nullable();
            $table->string('received_by', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stores_item_id', 'type']);
            $table->index('department_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores_transactions');
        Schema::dropIfExists('stores_items');
        Schema::dropIfExists('asset_depreciation_logs');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
    }
};

