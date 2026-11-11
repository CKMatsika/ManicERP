<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('employee_number', 48)->unique();
            $table->string('full_name', 180);
            $table->string('status', 24)->default('active');
            $table->date('hire_date')->nullable();
            $table->string('department', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('hr_pay_elements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 48)->unique();
            $table->string('name', 180);
            $table->string('type', 16); // earning, deduction
            $table->string('account_code', 32);
            $table->boolean('is_taxable')->default(false);
            $table->timestamps();
        });

        Schema::create('hr_payroll_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft');
            $table->bigInteger('total_gross_minor')->default(0);
            $table->bigInteger('total_deductions_minor')->default(0);
            $table->bigInteger('total_net_minor')->default(0);
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('journal_entry_id')->nullable();
            $table->timestamps();
            $table->index(['status', 'period_start']);
        });

        Schema::create('hr_payroll_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignUlid('pay_element_id')->constrained('hr_pay_elements')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamps();
            $table->index(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_lines');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_pay_elements');
        Schema::dropIfExists('hr_employees');
    }
};
