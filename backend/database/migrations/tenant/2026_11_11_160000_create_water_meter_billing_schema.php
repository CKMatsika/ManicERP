<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Water Meters Registry
        Schema::create('water_meters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('meter_number', 48)->unique(); // e.g. WTR-MTR-00481
            $table->string('serial_number', 64)->unique();
            $table->string('brand_model', 80)->default('Elster V100 15mm Volumetric');
            $table->string('meter_size', 24)->default('15mm'); // 15mm, 20mm, 25mm, 50mm, 100mm
            $table->foreignUlid('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
            $table->string('property_stand', 80); // e.g. AV-1024, Stand 4821 Chikanga
            $table->string('suburb', 80);
            $table->string('location_address', 180);
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->decimal('initial_reading', 10, 3)->default(0);
            $table->decimal('last_reading', 10, 3)->default(0);
            $table->date('last_reading_date')->nullable();
            $table->decimal('average_monthly_consumption', 10, 3)->default(15.0); // m³
            $table->string('tariff_category', 32)->default('domestic'); // domestic, commercial, industrial, institutional
            $table->string('status', 24)->default('active'); // active, stuck, faulty, disconnected
            $table->timestamps();

            $table->index(['suburb', 'status']);
            $table->index('customer_account_id');
        });

        // 2. Water Meter Field Readings & AMR Logs
        Schema::create('water_meter_readings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('water_meter_id')->constrained('water_meters')->cascadeOnDelete();
            $table->string('reading_period', 16); // e.g. 2026-11
            $table->decimal('previous_reading', 10, 3);
            $table->decimal('current_reading', 10, 3);
            $table->decimal('consumption', 10, 3); // m³
            $table->ulid('reader_user_id')->nullable();
            $table->string('reading_source', 32)->default('mobile_app'); // mobile_app, manual_walk, amr_pulse
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->integer('gps_distance_meters')->nullable();
            $table->string('anomaly_flag', 32)->default('normal'); // normal, burst_alert, stuck_meter, negative_consumption
            $table->string('billed_status', 24)->default('pending'); // pending, billed
            $table->ulid('bill_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['water_meter_id', 'reading_period']);
            $table->index(['reading_period', 'anomaly_flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_meter_readings');
        Schema::dropIfExists('water_meters');
    }
};

