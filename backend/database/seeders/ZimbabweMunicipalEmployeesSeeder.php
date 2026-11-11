<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hr\Services\ZimbabwePayrollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZimbabweMunicipalEmployeesSeeder extends Seeder
{
    public function run(ZimbabwePayrollService $payroll): void
    {
        $payroll->ensurePayElements();

        $employees = [
            [
                'employee_number' => 'EC-00101',
                'full_name' => 'Dr. K. Mandaza',
                'national_id' => '63-048210-A-42',
                'department' => 'Executive Office',
                'designation' => 'Town Clerk',
                'grade' => 'Band 1',
                'hire_date' => '2018-02-01',
                'basic_salary_minor' => 280000, // USD 2,800.00
                'housing_allowance_minor' => 60000, // USD 600.00
                'transport_allowance_minor' => 35000, // USD 350.00
                'bank_name' => 'Stanbic Bank Zimbabwe',
                'bank_account_number' => '9140003829101',
                'nssa_number' => 'NSSA-772109',
                'lapf_number' => 'LAPF-00192',
                'medical_aid_scheme' => 'CIMAS Executive',
                'medical_aid_minor' => 12000, // USD 120.00
                'status' => 'active',
            ],
            [
                'employee_number' => 'EC-00102',
                'full_name' => 'Eng. T. Masango',
                'national_id' => '63-128490-K-18',
                'department' => 'Engineering & Works',
                'designation' => 'Director of Engineering Services',
                'grade' => 'Band 2',
                'hire_date' => '2019-06-15',
                'basic_salary_minor' => 240000, // USD 2,400.00
                'housing_allowance_minor' => 50000, // USD 500.00
                'transport_allowance_minor' => 30000, // USD 300.00
                'bank_name' => 'CBZ Bank',
                'bank_account_number' => '0112482910291',
                'nssa_number' => 'NSSA-881920',
                'lapf_number' => 'LAPF-00214',
                'medical_aid_scheme' => 'CIMAS Private',
                'medical_aid_minor' => 9500,
                'status' => 'active',
            ],
            [
                'employee_number' => 'EC-00103',
                'full_name' => 'Mrs. F. Mutasa',
                'national_id' => '63-294018-C-54',
                'department' => 'Treasury & Finance',
                'designation' => 'City Treasurer',
                'grade' => 'Band 2',
                'hire_date' => '2020-01-10',
                'basic_salary_minor' => 240000,
                'housing_allowance_minor' => 50000,
                'transport_allowance_minor' => 30000,
                'bank_name' => 'Stanbic Bank Zimbabwe',
                'bank_account_number' => '9140008472910',
                'nssa_number' => 'NSSA-662819',
                'lapf_number' => 'LAPF-00301',
                'medical_aid_scheme' => 'CIMAS Private',
                'medical_aid_minor' => 9500,
                'status' => 'active',
            ],
            [
                'employee_number' => 'EC-00204',
                'full_name' => 'C. Nyathi',
                'national_id' => '63-882019-M-32',
                'department' => 'Health & Environmental Services',
                'designation' => 'Chief Cleansing Superintendent',
                'grade' => 'Band 6',
                'hire_date' => '2021-04-01',
                'basic_salary_minor' => 85000, // USD 850.00
                'housing_allowance_minor' => 20000,
                'transport_allowance_minor' => 12000,
                'bank_name' => 'BancABC Zimbabwe',
                'bank_account_number' => '448102948201',
                'nssa_number' => 'NSSA-448291',
                'lapf_number' => 'LAPF-00842',
                'medical_aid_scheme' => 'PSMAS Gold',
                'medical_aid_minor' => 4500,
                'status' => 'active',
            ],
            [
                'employee_number' => 'EC-00315',
                'full_name' => 'G. Chitepo',
                'national_id' => '63-718290-P-44',
                'department' => 'Engineering & Works',
                'designation' => 'Senior Water Reticulation Artisan',
                'grade' => 'Band 9',
                'hire_date' => '2022-09-01',
                'basic_salary_minor' => 52000, // USD 520.00
                'housing_allowance_minor' => 15000,
                'transport_allowance_minor' => 10000,
                'bank_name' => 'FBC Bank',
                'bank_account_number' => '301928471029',
                'nssa_number' => 'NSSA-551928',
                'lapf_number' => 'LAPF-01492',
                'medical_aid_scheme' => 'PSMAS Standard',
                'medical_aid_minor' => 3500,
                'status' => 'active',
            ],
            [
                'employee_number' => 'EC-00422',
                'full_name' => 'M. Sithole',
                'national_id' => '63-559102-R-67',
                'department' => 'Housing & Community Services',
                'designation' => 'Housing Waiting List Officer',
                'grade' => 'Band 8',
                'hire_date' => '2023-03-15',
                'basic_salary_minor' => 64000, // USD 640.00
                'housing_allowance_minor' => 18000,
                'transport_allowance_minor' => 11000,
                'bank_name' => 'Stanbic Bank Zimbabwe',
                'bank_account_number' => '9140009928104',
                'nssa_number' => 'NSSA-991824',
                'lapf_number' => 'LAPF-01628',
                'medical_aid_scheme' => 'CIMAS Standard',
                'medical_aid_minor' => 4000,
                'status' => 'active',
            ],
        ];

        foreach ($employees as $emp) {
            $exists = DB::connection('tenant')->table('hr_employees')->where('employee_number', $emp['employee_number'])->first();
            if ($exists) {
                DB::connection('tenant')->table('hr_employees')->where('id', $exists->id)->update($emp);
            } else {
                DB::connection('tenant')->table('hr_employees')->insert(array_merge($emp, [
                    'id' => (string) Str::ulid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // Process a sample payroll run for 2026-10
        $admin = User::first() ?? User::factory()->create();
        $payroll->runMonthlyPayroll('2026-10-01', '2026-10-31', $admin);
    }
}

