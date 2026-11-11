<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('registration_number', 48)->unique();
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('fleet_fuel_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('litres', 10, 3);
            $table->bigInteger('cost_minor');
            $table->integer('odometer')->nullable();
            $table->timestamps();
        });

        Schema::create('fleet_maintenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
            $table->date('maintenance_date');
            $table->text('description');
            $table->bigInteger('cost_minor');
            $table->string('account_code', 32)->default('5020');
            $table->ulid('journal_entry_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_maintenance');
        Schema::dropIfExists('fleet_fuel_logs');
        Schema::dropIfExists('fleet_vehicles');
    }
};
