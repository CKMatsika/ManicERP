<?php

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZimbabwePayrollService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public static function calculateNssa(int $grossSalaryMinor): int
    {
        $ceilingMinor = 70000; // USD 700.00 insurable earnings ceiling
        $insurableMinor = min($grossSalaryMinor, $ceilingMinor);
        return (int) round($insurableMinor * 0.045);
    }

    public static function calculateLapf(int $basicSalaryMinor): int
    {
        return (int) round($basicSalaryMinor * 0.06); // 6% Local Authorities Pension Fund
    }

    public static function calculatePaye(int $taxableIncomeMinor): array
    {
        $usd = $taxableIncomeMinor / 100;
        $tax = 0.0;

        if ($usd > 3000) {
            $tax += ($usd - 3000) * 0.40;
            $usd = 3000;
        }
        if ($usd > 2000) {
            $tax += ($usd - 2000) * 0.35;
            $usd = 2000;
        }
        if ($usd > 1000) {
            $tax += ($usd - 1000) * 0.30;
            $usd = 1000;
        }
        if ($usd > 300) {
            $tax += ($usd - 300) * 0.25;
            $usd = 300;
        }
        if ($usd > 100) {
            $tax += ($usd - 100) * 0.20;
        }

        $baseTaxMinor = (int) round($tax * 100);
        $aidsLevyMinor = (int) round($baseTaxMinor * 0.03); // 3% AIDS Levy
        $totalPayeMinor = $baseTaxMinor + $aidsLevyMinor;

        return [
            'base_tax_minor' => $baseTaxMinor,
            'aids_levy_minor' => $aidsLevyMinor,
            'total_paye_minor' => $totalPayeMinor,
        ];
    }

    public function ensurePayElements(): void
    {
        $elements = [
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => 'earning', 'account_code' => '5010', 'is_taxable' => true],
            ['code' => 'HOUSING', 'name' => 'Housing Allowance', 'type' => 'earning', 'account_code' => '5011', 'is_taxable' => true],
            ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'type' => 'earning', 'account_code' => '5012', 'is_taxable' => true],
            ['code' => 'PAYE', 'name' => 'ZIMRA PAYE & AIDS Levy', 'type' => 'deduction', 'account_code' => '2110', 'is_taxable' => false],
            ['code' => 'NSSA', 'name' => 'NSSA Pension (POBS)', 'type' => 'deduction', 'account_code' => '2120', 'is_taxable' => false],
            ['code' => 'LAPF', 'name' => 'Local Authorities Pension Fund', 'type' => 'deduction', 'account_code' => '2130', 'is_taxable' => false],
            ['code' => 'MEDAID', 'name' => 'Medical Aid Scheme', 'type' => 'deduction', 'account_code' => '2140', 'is_taxable' => false],
        ];

        foreach ($elements as $el) {
            $exists = DB::connection('tenant')->table('hr_pay_elements')->where('code', $el['code'])->first();
            if (! $exists) {
                DB::connection('tenant')->table('hr_pay_elements')->insert([
                    'id' => (string) Str::ulid(),
                    'code' => $el['code'],
                    'name' => $el['name'],
                    'type' => $el['type'],
                    'account_code' => $el['account_code'],
                    'is_taxable' => $el['is_taxable'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function runMonthlyPayroll(string $periodStart, string $periodEnd, User $preparer): array
    {
        $this->ensurePayElements();

        $employees = DB::connection('tenant')
            ->table('hr_employees')
            ->where('status', 'active')
            ->get();

        if ($employees->isEmpty()) {
            throw new \DomainException('No active municipal employees found to process.');
        }

        $elementsMap = DB::connection('tenant')
            ->table('hr_pay_elements')
            ->pluck('id', 'code')
            ->all();

        return DB::connection('tenant')->transaction(function () use ($employees, $elementsMap, $periodStart, $periodEnd, $preparer) {
            $runId = (string) Str::ulid();
            $ref = 'PAY-' . date('Ym') . '-' . strtoupper(Str::random(4));

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            $linesToInsert = [];

            foreach ($employees as $emp) {
                $basic = (int) $emp->basic_salary_minor;
                $housing = (int) $emp->housing_allowance_minor;
                $transport = (int) $emp->transport_allowance_minor;
                $gross = $basic + $housing + $transport;

                // Statutory Deductions
                $nssa = self::calculateNssa($gross);
                $lapf = self::calculateLapf($basic);
                $medAid = (int) $emp->medical_aid_minor;

                // Taxable income = Gross - NSSA - LAPF
                $taxableIncome = max(0, $gross - $nssa - $lapf);
                $payeResult = self::calculatePaye($taxableIncome);
                $paye = $payeResult['total_paye_minor'];

                $empDeductions = $nssa + $lapf + $paye + $medAid;
                $net = max(0, $gross - $empDeductions);

                $totalGross += $gross;
                $totalDeductions += $empDeductions;
                $totalNet += $net;

                $now = now();

                // 1. Basic
                $linesToInsert[] = [
                    'id' => (string) Str::ulid(),
                    'payroll_run_id' => $runId,
                    'employee_id' => $emp->id,
                    'pay_element_id' => $elementsMap['BASIC'],
                    'element_code' => 'BASIC',
                    'element_name' => 'Basic Salary',
                    'element_type' => 'earning',
                    'amount_minor' => $basic,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // 2. Housing
                if ($housing > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['HOUSING'],
                        'element_code' => 'HOUSING',
                        'element_name' => 'Housing Allowance',
                        'element_type' => 'earning',
                        'amount_minor' => $housing,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 3. Transport
                if ($transport > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['TRANSPORT'],
                        'element_code' => 'TRANSPORT',
                        'element_name' => 'Transport Allowance',
                        'element_type' => 'earning',
                        'amount_minor' => $transport,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 4. PAYE
                if ($paye > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['PAYE'],
                        'element_code' => 'PAYE',
                        'element_name' => 'ZIMRA PAYE & AIDS Levy',
                        'element_type' => 'deduction',
                        'amount_minor' => $paye,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 5. NSSA
                if ($nssa > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['NSSA'],
                        'element_code' => 'NSSA',
                        'element_name' => 'NSSA Pension',
                        'element_type' => 'deduction',
                        'amount_minor' => $nssa,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 6. LAPF
                if ($lapf > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['LAPF'],
                        'element_code' => 'LAPF',
                        'element_name' => 'Local Authorities Pension',
                        'element_type' => 'deduction',
                        'amount_minor' => $lapf,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // 7. Medical Aid
                if ($medAid > 0) {
                    $linesToInsert[] = [
                        'id' => (string) Str::ulid(),
                        'payroll_run_id' => $runId,
                        'employee_id' => $emp->id,
                        'pay_element_id' => $elementsMap['MEDAID'],
                        'element_code' => 'MEDAID',
                        'element_name' => 'Medical Aid',
                        'element_type' => 'deduction',
                        'amount_minor' => $medAid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Create Run
            DB::connection('tenant')->table('hr_payroll_runs')->insert([
                'id' => $runId,
                'reference' => $ref,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'approved',
                'total_gross_minor' => $totalGross,
                'total_deductions_minor' => $totalDeductions,
                'total_net_minor' => $totalNet,
                'prepared_by' => $preparer->id,
                'approved_by' => $preparer->id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tenant')->table('hr_payroll_lines')->insert($linesToInsert);

            $this->audit->record(
                action: 'hr.payroll.processed',
                subject: null,
                after: [
                    'reference' => $ref,
                    'employees_count' => $employees->count(),
                    'gross_usd' => number_format($totalGross / 100, 2),
                    'net_usd' => number_format($totalNet / 100, 2),
                ],
                module: 'hr',
            );

            return [
                'run_id' => $runId,
                'reference' => $ref,
                'employees_count' => $employees->count(),
                'total_gross_usd' => number_format($totalGross / 100, 2),
                'total_deductions_usd' => number_format($totalDeductions / 100, 2),
                'total_net_usd' => number_format($totalNet / 100, 2),
            ];
        });
    }

    public function getPayrollSummary(): array
    {
        $employees = DB::connection('tenant')
            ->table('hr_employees')
            ->orderBy('employee_number')
            ->get();

        $runs = DB::connection('tenant')
            ->table('hr_payroll_runs')
            ->orderByDesc('period_start')
            ->take(6)
            ->get();

        $latestRun = $runs->first();
        $payslips = [];

        if ($latestRun) {
            $lines = DB::connection('tenant')
                ->table('hr_payroll_lines')
                ->where('payroll_run_id', $latestRun->id)
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $emp) {
                $empLines = $lines->get($emp->id, collect());
                $earnings = $empLines->where('element_type', 'earning')->values();
                $deductions = $empLines->where('element_type', 'deduction')->values();

                $gross = $earnings->sum('amount_minor');
                $totDeductions = $deductions->sum('amount_minor');
                $net = $gross - $totDeductions;

                $payslips[] = [
                    'employee_id' => $emp->id,
                    'employee_number' => $emp->employee_number,
                    'full_name' => $emp->full_name,
                    'national_id' => $emp->national_id,
                    'designation' => $emp->designation,
                    'grade' => $emp->grade,
                    'department' => $emp->department,
                    'bank_name' => $emp->bank_name,
                    'account_number' => $emp->bank_account_number,
                    'nssa_number' => $emp->nssa_number,
                    'lapf_number' => $emp->lapf_number,
                    'gross_usd' => number_format($gross / 100, 2),
                    'deductions_usd' => number_format($totDeductions / 100, 2),
                    'net_usd' => number_format($net / 100, 2),
                    'earnings' => $earnings->map(fn ($e) => [
                        'name' => $e->element_name,
                        'amount_usd' => number_format($e->amount_minor / 100, 2),
                    ])->all(),
                    'deductions_list' => $deductions->map(fn ($d) => [
                        'name' => $d->element_name,
                        'amount_usd' => number_format($d->amount_minor / 100, 2),
                    ])->all(),
                ];
            }
        }

        return [
            'total_staff_count' => $employees->count(),
            'monthly_gross_wage_usd' => number_format($employees->sum(fn ($e) => $e->basic_salary_minor + $e->housing_allowance_minor + $e->transport_allowance_minor) / 100, 2),
            'employees' => $employees->map(function ($e) {
                return [
                    'id' => $e->id,
                    'ec_number' => $e->employee_number,
                    'name' => $e->full_name,
                    'national_id' => $e->national_id,
                    'department' => $e->department,
                    'designation' => $e->designation,
                    'grade' => $e->grade,
                    'basic_salary_usd' => number_format($e->basic_salary_minor / 100, 2),
                    'gross_salary_usd' => number_format(($e->basic_salary_minor + $e->housing_allowance_minor + $e->transport_allowance_minor) / 100, 2),
                    'bank' => $e->bank_name,
                    'account' => $e->bank_account_number,
                    'status' => $e->status,
                ];
            })->all(),
            'runs' => $runs->map(function ($r) {
                return [
                    'id' => $r->id,
                    'ref' => $r->reference,
                    'period' => "{$r->period_start} to {$r->period_end}",
                    'gross_usd' => number_format($r->total_gross_minor / 100, 2),
                    'deductions_usd' => number_format($r->total_deductions_minor / 100, 2),
                    'net_usd' => number_format($r->total_net_minor / 100, 2),
                    'status' => $r->status,
                ];
            })->all(),
            'latest_payslips' => $payslips,
        ];
    }
}
