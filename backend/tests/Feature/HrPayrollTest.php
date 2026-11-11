<?php

namespace Tests\Feature;

use App\Modules\Hr\Services\PayrollService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class HrPayrollTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_payroll_run_prepares_submits_and_posts_balanced_journal(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('hr-prep@test.local');
        $approver = $this->makeUser('hr-approver@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $prep);

        $service = app(PayrollService::class);
        $employee = $service->createEmployee('EMP-001', 'Synthetic Employee');
        $earning = $service->createPayElement('BASE', 'Base salary', 'earning', '5020');
        $deduction = $service->createPayElement('DED', 'Deduction', 'deduction', '2100');

        $run = $service->prepareRun('2026-01-01', '2026-01-31', [
            ['employee_id' => $employee->id, 'pay_element_id' => $earning->id, 'amount_minor' => 100000],
            ['employee_id' => $employee->id, 'pay_element_id' => $deduction->id, 'amount_minor' => 10000],
        ], $prep);
        $this->assertSame('draft', $run->status);
        $this->assertSame(100000, (int) $run->total_gross_minor);
        $this->assertSame(90000, (int) $run->total_net_minor);

        $submitted = $service->submitRun($run->id, $prep);
        $this->assertSame('pending_approval', $submitted->status);

        try {
            $service->approveRun($run->id, $prep);
            $this->fail('Expected independent approval.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('independent approver', $exception->getMessage());
        }

        $approved = $service->approveRun($run->id, $approver);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->journal_entry_id);
    }
}
