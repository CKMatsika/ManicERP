<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // 11. CEMETERIES & GRAVE REGISTER
        // =========================================================================
        if (! Schema::hasTable('cemeteries')) {
            Schema::create('cemeteries', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name', 120);
                $table->string('location', 160);
                $table->string('status', 32)->default('active'); // active, full, closed
                $table->integer('total_plots')->default(0);
                $table->integer('occupied_plots')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grave_plots')) {
            Schema::create('grave_plots', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('cemetery_id')->constrained('cemeteries')->cascadeOnDelete();
                $table->string('section', 60); // e.g. Catholic, Anglican, General, Muslim, Cremation, Infant
                $table->string('block', 32);
                $table->string('plot_number', 48); // PLOT-A1-042
                $table->string('depth', 32)->default('single_6ft'); // single_6ft, double_8ft, triple_10ft
                $table->string('status', 32)->default('available'); // available, reserved, interred, exhumed
                $table->string('reserved_by', 120)->nullable();
                $table->string('reserved_contact', 60)->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->bigInteger('plot_fee_minor')->default(15000); // e.g. USD 150.00
                $table->timestamps();
                $table->unique(['cemetery_id', 'section', 'block', 'plot_number']);
            });
        }

        if (! Schema::hasTable('cemetery_interments')) {
            Schema::create('cemetery_interments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('grave_plot_id')->constrained('grave_plots')->cascadeOnDelete();
                $table->string('interment_order_number', 64)->unique(); // BUR-2026-0042
                $table->string('deceased_name', 160);
                $table->string('deceased_national_id', 48)->nullable();
                $table->date('date_of_death');
                $table->date('date_of_interment');
                $table->string('burial_permit_number', 80);
                $table->string('cause_of_death', 160)->nullable();
                $table->string('undertaker_name', 120)->nullable();
                $table->string('next_of_kin_name', 120);
                $table->string('next_of_kin_phone', 60);
                $table->bigInteger('interment_fee_minor')->default(10000); // USD 100.00
                $table->string('payment_status', 32)->default('paid');
                $table->string('status', 32)->default('completed');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // =========================================================================
        // 12. EMERGENCY SERVICES & FIRE BRIGADE
        // =========================================================================
        if (! Schema::hasTable('fire_hydrants')) {
            Schema::create('fire_hydrants', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('hydrant_code', 48)->unique(); // HYD-CBD-014
                $table->string('location_address', 180);
                $table->string('suburb', 80);
                $table->decimal('static_pressure_bar', 5, 2)->default(3.5);
                $table->decimal('flow_rate_lpm', 8, 2)->default(1200);
                $table->string('status', 32)->default('operational'); // operational, low_pressure, vandalized, out_of_order
                $table->date('last_inspected_at')->nullable();
                $table->string('inspected_by', 120)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fire_safety_inspections')) {
            Schema::create('fire_safety_inspections', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('certificate_number', 64)->unique(); // FSC-2026-0812
                $table->string('premises_name', 160);
                $table->string('stand_number', 80);
                $table->string('owner_name', 120);
                $table->string('occupancy_type', 80); // Commercial, Industrial, Petroleum, Educational
                $table->boolean('extinguishers_valid')->default(true);
                $table->boolean('hose_reels_operational')->default(true);
                $table->boolean('fire_escapes_clear')->default(true);
                $table->string('compliance_status', 32)->default('compliant'); // compliant, rectification_notice, condemned
                $table->date('inspection_date');
                $table->date('expires_at');
                $table->string('officer_name', 120);
                $table->bigInteger('inspection_fee_minor')->default(6000); // USD 60.00
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('emergency_incidents')) {
            Schema::create('emergency_incidents', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('incident_number', 64)->unique(); // INC-2026-091
                $table->string('incident_type', 48); // structural_fire, veld_fire, road_traffic_accident, ambulance_run, chemical_hazard
                $table->string('caller_name', 120);
                $table->string('caller_phone', 60);
                $table->string('location_address', 180);
                $table->string('suburb', 80);
                $table->string('station_code', 32)->default('STN-CENTRAL');
                $table->string('appliances_dispatched', 160); // e.g. "Major Tender 1, Water Bowser, Ambulance 2"
                $table->timestamp('dispatched_at');
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('contained_at')->nullable();
                $table->integer('casualties')->default(0);
                $table->bigInteger('service_charge_minor')->default(0); // Ambulance trip fee or fire run charge
                $table->string('billed_account_number', 48)->nullable();
                $table->string('status', 32)->default('dispatched'); // dispatched, on_scene, contained, closed
                $table->text('incident_report')->nullable();
                $table->timestamps();
            });
        }

        // =========================================================================
        // 13. FLEET & HEAVY PLANT MANAGEMENT ENHANCEMENTS
        // =========================================================================
        if (! Schema::hasTable('fleet_vehicles')) {
            Schema::create('fleet_vehicles', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('registration_number', 48)->unique();
                $table->string('make', 80)->nullable();
                $table->string('model', 80)->nullable();
                $table->string('plant_type', 60)->default('pickup');
                $table->string('department', 80)->default('Engineering');
                $table->string('assigned_driver', 120)->nullable();
                $table->integer('current_odometer')->default(0);
                $table->string('fuel_type', 24)->default('diesel');
                $table->string('status', 24)->default('active');
                $table->timestamps();
            });
        } else {
            Schema::table('fleet_vehicles', function (Blueprint $table) {
                if (! Schema::hasColumn('fleet_vehicles', 'plant_type')) {
                    $table->string('plant_type', 60)->default('pickup');
                }
                if (! Schema::hasColumn('fleet_vehicles', 'department')) {
                    $table->string('department', 80)->default('Engineering');
                }
                if (! Schema::hasColumn('fleet_vehicles', 'assigned_driver')) {
                    $table->string('assigned_driver', 120)->nullable();
                }
                if (! Schema::hasColumn('fleet_vehicles', 'current_odometer')) {
                    $table->integer('current_odometer')->default(0);
                }
                if (! Schema::hasColumn('fleet_vehicles', 'fuel_type')) {
                    $table->string('fuel_type', 24)->default('diesel');
                }
            });
        }

        if (! Schema::hasTable('fleet_fuel_coupons')) {
            Schema::create('fleet_fuel_coupons', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
                $table->string('coupon_serial', 64)->unique(); // FL-CPN-2026-9901
                $table->date('issue_date');
                $table->decimal('litres', 8, 2);
                $table->string('fuel_type', 24)->default('diesel');
                $table->string('purpose', 160);
                $table->string('authorized_by', 120);
                $table->string('driver_name', 120);
                $table->bigInteger('cost_minor');
                $table->string('status', 32)->default('disbursed');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fleet_logbook_entries')) {
            Schema::create('fleet_logbook_entries', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('vehicle_id')->constrained('fleet_vehicles')->cascadeOnDelete();
                $table->date('trip_date');
                $table->string('driver_name', 120);
                $table->string('purpose', 180);
                $table->integer('start_odometer');
                $table->integer('end_odometer');
                $table->integer('distance_km');
                $table->timestamps();
            });
        }

        // =========================================================================
        // 14. DEVOLUTION CAPITAL INFRASTRUCTURE PROJECTS
        // =========================================================================
        if (! Schema::hasTable('capital_projects')) {
            Schema::create('capital_projects', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('project_code', 48)->unique(); // DEV-2026-RDS-01
                $table->string('title', 180);
                $table->string('category', 60); // roads, water_sanitation, health_clinic, education, public_lighting
                $table->string('funding_source', 80); // devolution_grant, internal_capital_fund, road_fund_zinara
                $table->bigInteger('approved_budget_minor');
                $table->bigInteger('contract_value_minor');
                $table->string('contractor_name', 160);
                $table->string('contractor_praz_number', 64)->nullable();
                $table->date('commencement_date');
                $table->date('expected_completion_date');
                $table->decimal('physical_progress_percent', 5, 2)->default(0.0);
                $table->decimal('retention_rate_percent', 4, 2)->default(10.0);
                $table->bigInteger('retention_held_minor')->default(0);
                $table->bigInteger('certified_claims_minor')->default(0);
                $table->bigInteger('paid_to_date_minor')->default(0);
                $table->string('status', 32)->default('in_progress');
                $table->text('scope_of_works')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('capital_project_certificates')) {
            Schema::create('capital_project_certificates', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('capital_project_id')->constrained('capital_projects')->cascadeOnDelete();
                $table->string('certificate_number', 48); // IPC-01, IPC-02
                $table->date('valuation_date');
                $table->bigInteger('gross_certified_minor');
                $table->bigInteger('retention_deducted_minor');
                $table->bigInteger('net_payable_minor');
                $table->decimal('cumulative_progress_percent', 5, 2);
                $table->string('certifying_engineer', 120);
                $table->string('approval_status', 32)->default('approved');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_project_certificates');
        Schema::dropIfExists('capital_projects');
        Schema::dropIfExists('fleet_logbook_entries');
        Schema::dropIfExists('fleet_fuel_coupons');
        Schema::dropIfExists('emergency_incidents');
        Schema::dropIfExists('fire_safety_inspections');
        Schema::dropIfExists('fire_hydrants');
        Schema::dropIfExists('cemetery_interments');
        Schema::dropIfExists('grave_plots');
        Schema::dropIfExists('cemeteries');
    }
};
