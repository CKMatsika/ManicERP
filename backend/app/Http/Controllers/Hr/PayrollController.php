<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Services\ZimbabwePayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        private readonly ZimbabwePayrollService $payroll,
    ) {}

    public function index(): Response
    {
        $summary = $this->payroll->getPayrollSummary();

        return Inertia::render('Hr/Payroll', [
            'summary' => $summary,
        ]);
    }

    public function process(Request $request)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $result = $this->payroll->runMonthlyPayroll(
            $data['period_start'],
            $data['period_end'],
            $request->user()
        );

        return redirect()->back()->with(
            'success',
            "Monthly payroll {$result['reference']} generated for {$result['employees_count']} employees. Net Payable: USD {$result['total_net_usd']}."
        );
    }

    public function exportBank(Request $request, string $id)
    {
        $run = DB::connection('tenant')->table('hr_payroll_runs')->where('id', $id)->firstOrFail();
        $employees = DB::connection('tenant')->table('hr_employees')->get()->keyBy('id');

        $lines = DB::connection('tenant')
            ->table('hr_payroll_lines')
            ->where('payroll_run_id', $run->id)
            ->get()
            ->groupBy('employee_id');

        $csvRows = [];
        $csvRows[] = ['EC_NUMBER', 'EMPLOYEE_NAME', 'BANK_NAME', 'ACCOUNT_NUMBER', 'NET_AMOUNT_USD', 'PAYMENT_REFERENCE'];

        foreach ($lines as $empId => $empLines) {
            $emp = $employees->get($empId);
            if (! $emp) continue;

            $gross = $empLines->where('element_type', 'earning')->sum('amount_minor');
            $deductions = $empLines->where('element_type', 'deduction')->sum('amount_minor');
            $netMinor = max(0, $gross - $deductions);

            $csvRows[] = [
                $emp->employee_number,
                $emp->full_name,
                $emp->bank_name,
                $emp->bank_account_number ?? 'PENDING',
                number_format($netMinor / 100, 2, '.', ''),
                "SAL-{$run->reference}-{$emp->employee_number}",
            ];
        }

        $filename = "Bank_Disbursement_{$run->reference}.csv";
        $handle = fopen('php://temp', 'r+');
        foreach ($csvRows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

