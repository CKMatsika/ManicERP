<?php

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollService
{
    public function __construct(private readonly PostingService $posting, private readonly AuditLogger $audit) {}

    public function createEmployee(string $employeeNumber, string $fullName, ?string $hireDate = null, ?string $department = null): object
    {
        if (trim($employeeNumber) === '' || trim($fullName) === '') {
            throw new \DomainException('Employee number and name are required.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('hr_employees')->insert([
            'id' => $id, 'employee_number' => $employeeNumber, 'full_name' => $fullName,
            'status' => 'active', 'hire_date' => $hireDate, 'department' => $department,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('hr_employees')->where('id', $id)->firstOrFail();
    }

    public function createPayElement(string $code, string $name, string $type, string $accountCode): object
    {
        if (! in_array($type, ['earning', 'deduction'], true)) {
            throw new \DomainException('Pay element type must be earning or deduction.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('hr_pay_elements')->insert([
            'id' => $id, 'code' => $code, 'name' => $name, 'type' => $type,
            'account_code' => $accountCode, 'is_taxable' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('hr_pay_elements')->where('id', $id)->firstOrFail();
    }

    /** @param array<int, array{employee_id: string, pay_element_id: string, amount_minor: int}> $lines */
    public function prepareRun(string $periodStart, string $periodEnd, array $lines, User $preparer): object
    {
        if ($periodEnd < $periodStart || $lines === []) {
            throw new \DomainException('A payroll run requires a valid period and at least one line.');
        }
        $id = (string) Str::ulid();
        $reference = 'PAY-'.strtoupper((string) Str::ulid());
        $gross = 0;
        $deductions = 0;
        foreach ($lines as $line) {
            if ((int) $line['amount_minor'] <= 0) {
                throw new \DomainException('Payroll amounts must be positive.');
            }
            $element = DB::connection('tenant')->table('hr_pay_elements')->where('id', $line['pay_element_id'])->firstOrFail();
            if ($element->type === 'earning') {
                $gross += (int) $line['amount_minor'];
            } else {
                $deductions += (int) $line['amount_minor'];
            }
        }
        DB::connection('tenant')->transaction(function () use ($id, $reference, $periodStart, $periodEnd, $lines, $preparer, $gross, $deductions) {
            DB::connection('tenant')->table('hr_payroll_runs')->insert([
                'id' => $id, 'reference' => $reference, 'period_start' => $periodStart, 'period_end' => $periodEnd,
                'status' => 'draft', 'total_gross_minor' => $gross, 'total_deductions_minor' => $deductions,
                'total_net_minor' => $gross - $deductions, 'prepared_by' => $preparer->getKey(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::connection('tenant')->table('hr_payroll_lines')->insert([
                    'id' => (string) Str::ulid(), 'payroll_run_id' => $id, 'employee_id' => $line['employee_id'],
                    'pay_element_id' => $line['pay_element_id'], 'amount_minor' => (int) $line['amount_minor'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit->record(action: 'hr.payroll.prepared', after: ['run_id' => $id, 'reference' => $reference, 'gross_minor' => $gross, 'net_minor' => $gross - $deductions], module: 'hr');
        });

        return DB::connection('tenant')->table('hr_payroll_runs')->where('id', $id)->firstOrFail();
    }

    public function submitRun(string $runId, User $submitter): object
    {
        $run = DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->firstOrFail();
        if ($run->status !== 'draft' || $run->prepared_by !== $submitter->getKey()) {
            throw new \DomainException('Only the preparer may submit a draft payroll run.');
        }
        DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->update(['status' => 'pending_approval', 'updated_at' => now()]);

        return DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->firstOrFail();
    }

    public function approveRun(string $runId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($runId, $approver) {
            $run = DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'pending_approval' || $run->prepared_by === $approver->getKey()) {
                throw new \DomainException('Payroll approval requires an independent approver.');
            }
            $lines = DB::connection('tenant')->table('hr_payroll_lines as l')
                ->join('hr_pay_elements as e', 'e.id', '=', 'l.pay_element_id')
                ->where('l.payroll_run_id', $runId)
                ->select('e.type', 'e.account_code', 'l.amount_minor')
                ->get();
            $debitLines = [];
            $creditLines = [];
            $expenseByAccount = [];
            $liabilityByAccount = [];
            foreach ($lines as $line) {
                if ($line->type === 'earning') {
                    $expenseByAccount[$line->account_code] = ($expenseByAccount[$line->account_code] ?? 0) + (int) $line->amount_minor;
                } else {
                    $liabilityByAccount[$line->account_code] = ($liabilityByAccount[$line->account_code] ?? 0) + (int) $line->amount_minor;
                }
            }
            $journalLines = [];
            foreach ($expenseByAccount as $code => $amount) {
                $journalLines[] = ['account_code' => $code, 'debit_minor' => $amount, 'memo' => 'Payroll expense'];
            }
            foreach ($liabilityByAccount as $code => $amount) {
                $journalLines[] = ['account_code' => $code, 'credit_minor' => $amount, 'memo' => 'Payroll deduction payable'];
            }
            // Net pay liability (credit to salary payable if not already credited)
            $net = (int) $run->total_net_minor;
            if ($net > 0) {
                $journalLines[] = ['account_code' => '2100', 'credit_minor' => $net, 'memo' => 'Net salaries payable'];
            }
            $journal = $this->posting->post([
                'journal_type' => 'payroll', 'entry_date' => $run->period_end,
                'description' => 'Payroll run '.$run->reference, 'currency_code' => 'USD',
                'lines' => $journalLines,
                'source_type' => 'payroll_run', 'source_id' => $run->id,
                'idempotency_key' => 'hr:payroll:'.$run->id, 'created_by' => $approver->getKey(),
            ]);
            DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->update([
                'status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(),
                'journal_entry_id' => $journal->id, 'updated_at' => now(),
            ]);
            $this->audit->record(action: 'hr.payroll.approved', after: ['run_id' => $runId, 'journal_id' => $journal->id], module: 'hr');

            return DB::connection('tenant')->table('hr_payroll_runs')->where('id', $runId)->firstOrFail();
        });
    }
}
