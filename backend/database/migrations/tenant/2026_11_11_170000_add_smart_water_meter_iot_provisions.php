<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_meters', function (Blueprint $table): void {
            $table->string('communication_type', 32)->default('manual')->after('tariff_category'); // manual, lorawan, nbiot, sts_prepaid
            $table->string('device_eui', 64)->nullable()->after('communication_type'); // LoRaWAN DevEUI / IMEI
            $table->string('valve_status', 24)->default('open')->after('device_eui'); // open, closed
            $table->integer('battery_level_percent')->default(100)->after('valve_status');
            $table->timestamp('last_telemetry_at')->nullable()->after('battery_level_percent');
            $table->string('tamper_status', 32)->default('secure')->after('last_telemetry_at'); // secure, magnetic_tamper, reverse_flow, burst_leak
        });

        Schema::create('water_meter_telemetry_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('water_meter_id')->constrained('water_meters')->cascadeOnDelete();
            $table->decimal('index_reading', 10, 3);
            $table->decimal('hourly_consumption', 10, 3)->default(0);
            $table->integer('battery_percent')->default(100);
            $table->integer('rssi_signal_dbm')->nullable();
            $table->string('valve_state', 24)->default('open');
            $table->string('alarm_event', 48)->nullable(); // e.g. CONTINUOUS_FLOW_BURST, MAGNET_TAMPER, LOW_BATTERY
            $table->jsonb('raw_payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['water_meter_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_meter_telemetry_logs');
        Schema::table('water_meters', function (Blueprint $table): void {
            $table->dropColumn([
                'communication_type',
                'device_eui',
                'valve_status',
                'battery_level_percent',
                'last_telemetry_at',
                'tamper_status',
            ]);
        });
    }
};

