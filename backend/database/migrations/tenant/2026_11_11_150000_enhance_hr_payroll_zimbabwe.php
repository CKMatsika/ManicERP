<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('national_id', 32)->nullable()->after('full_name');
            $table->string('designation', 100)->nullable()->after('department');
            $table->string('grade', 32)->default('Band 7')->after('designation');
            $table->bigInteger('basic_salary_minor')->default(50000)->after('grade'); // USD 500.00
            $table->bigInteger('housing_allowance_minor')->default(15000)->after('basic_salary_minor'); // USD 150.00
            $table->bigInteger('transport_allowance_minor')->default(8000)->after('housing_allowance_minor'); // USD 80.00
            $table->string('bank_name', 80)->default('Stanbic Bank Zimbabwe')->after('transport_allowance_minor');
            $table->string('bank_account_number', 48)->nullable()->after('bank_name');
            $table->string('nssa_number', 32)->nullable()->after('bank_account_number');
            $table->string('lapf_number', 32)->nullable()->after('nssa_number');
            $table->string('medical_aid_scheme', 48)->default('CIMAS Standard')->after('lapf_number');
            $table->bigInteger('medical_aid_minor')->default(3500)->after('medical_aid_scheme'); // USD 35.00
        });

        // Add statutory tax calculation fields to hr_payroll_lines
        Schema::table('hr_payroll_lines', function (Blueprint $table): void {
            $table->string('element_code', 32)->nullable()->after('pay_element_id');
            $table->string('element_name', 100)->nullable()->after('element_code');
            $table->string('element_type', 16)->default('earning')->after('element_name'); // earning, deduction
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_lines', function (Blueprint $table): void {
            $table->dropColumn(['element_code', 'element_name', 'element_type']);
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn([
                'national_id',
                'designation',
                'grade',
                'basic_salary_minor',
                'housing_allowance_minor',
                'transport_allowance_minor',
                'bank_name',
                'bank_account_number',
                'nssa_number',
                'lapf_number',
                'medical_aid_scheme',
                'medical_aid_minor',
            ]);
        });
    }
};

