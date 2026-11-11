<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSetting;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\MigrationBatch;
use App\Modules\Accounting\Services\OpeningBalanceService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\Receipt;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    private User $approver;

    private Membership $adminMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('opening@test.local', 'Opening Preparer');
        $this->approver = $this->makeUser('opening-approver@test.local', 'Opening Approver');

        $adminRole = $this->makeRole($this->council, 'council_admin', ['*']);
        $approverRole = $this->makeRole($this->council, 'finance_approver', ['*']);
        $this->adminMembership = $this->makeMembership($this->admin, $this->council, $adminRole);
        $this->makeMembership($this->approver, $this->council, $approverRole);

        $this->actingAsStaff($this->admin, $this->council, $this->adminMembership);
        $this->seedAccounting($this->council, $this->admin);
    }

    public function test_balanced_gl_opening_balances_post_once_and_reconcile(): void
    {
        $csv = "account_code,debit,credit,description,dimensions\n"
            ."1110,1000.00,0.00,Opening cash,\n"
            ."3010,0.00,1000.00,Opening surplus,\n";

        $batch = $this->import('opening_gl', $csv, 'gl.csv', '2026-02-01');
        $this->assertSame('validated', $batch->status);
        $this->assertSame(0, $batch->error_count);

        $this->approveAndPost($batch);

        $batch->refresh();
        $this->assertSame('posted', $batch->status);
        $this->assertSame(100000, $batch->reconciliation['debit_minor']);
        $this->assertTrue($batch->reconciliation['balanced']);

        $journal = JournalEntry::findOrFail($batch->journal_entry_id);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(100000, $journal->total_debit_minor);

        // Re-posting is idempotent and does not create a second journal.
        app(OpeningBalanceService::class)->post($batch->fresh(), $this->approver);
        $this->assertSame(1, JournalEntry::where('source_type', 'opening_gl_batch')->count());
    }

    public function test_an_unbalanced_gl_batch_cannot_be_approved(): void
    {
        $csv = "account_code,debit,credit,description\n"
            ."1110,1000.00,0.00,Opening cash\n"
            ."3010,0.00,900.00,Opening surplus\n";

        $batch = $this->import('opening_gl', $csv, 'unbalanced.csv', '2026-02-01');
        $this->assertGreaterThan(0, $batch->error_count);
        $this->assertSame('validated_with_errors', $batch->status);

        $this->expectException(\DomainException::class);
        app(OpeningBalanceService::class)->approve($batch, $this->approver);
    }

    public function test_customer_opening_invoices_and_credits_create_no_cash_receipts_and_agree_to_the_gl(): void
    {
        $customer = $this->makeCustomer($this->council, $this->admin);

        $csv = "account_number,type,reference,amount,due_date,description,service_type\n"
            ."{$customer['account']->account_number},invoice,OB-INV-1,1700.00,2026-02-28,Opening rates invoice,rates\n"
            ."{$customer['account']->account_number},unapplied,OB-UNP-1,50.00,,Opening unapplied credit,\n";

        $batch = $this->import('opening_customer', $csv, 'customer.csv', '2026-02-01');
        $this->assertSame('validated', $batch->status, json_encode($batch->rows->pluck('errors')));

        $this->approveAndPost($batch);
        $batch->refresh();

        $this->assertSame('posted', $batch->status);
        $this->assertSame(1, Bill::where('origin', 'opening_balance')->count());
        $this->assertSame(1, AccountCredit::where('source', 'opening_balance')->count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Receipt::count());

        $recon = $batch->reconciliation;
        $this->assertSame(170000, $recon['invoice_total_minor']);
        $this->assertSame(5000, $recon['credit_total_minor']);
        $this->assertSame(170000, $recon['journal_receivable_minor']);
        $this->assertTrue($recon['balanced']);
        $this->assertTrue($recon['receivable_reconciles']);
        $this->assertSame(0, $recon['payments_created']);

        $bill = Bill::where('origin', 'opening_balance')->firstOrFail();
        $this->assertSame(170000, $bill->balance_minor);
        $this->assertStringContainsString('no historic cash receipt', $bill->calculation_snapshot['migration_note']);
    }

    public function test_duplicate_import_is_idempotent_and_duplicate_references_are_rejected(): void
    {
        $csv = "account_code,debit,credit,description\n1110,10.00,0.00,Cash\n3010,0.00,10.00,Surplus\n";
        $first = $this->import('opening_gl', $csv, 'same.csv', '2026-02-01');
        $second = $this->import('opening_gl', $csv, 'same.csv', '2026-02-01');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, MigrationBatch::where('type', 'opening_gl')->count());

        $customer = $this->makeCustomer($this->council, $this->admin);
        $duplicate = "account_number,type,reference,amount,due_date,description\n"
            ."{$customer['account']->account_number},invoice,DUP-1,10.00,2026-02-28,One\n"
            ."{$customer['account']->account_number},invoice,DUP-1,20.00,2026-02-28,Two\n";

        $batch = $this->import('opening_customer', $duplicate, 'duplicate.csv', '2026-02-01');
        $this->assertGreaterThan(0, $batch->error_count);
    }

    public function test_preparer_cannot_approve_their_own_batch(): void
    {
        $batch = $this->import('opening_gl', "account_code,debit,credit,description\n1110,10.00,0.00,Cash\n3010,0.00,10.00,Surplus\n", 'sod.csv', '2026-02-01');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Separation of duties');

        app(OpeningBalanceService::class)->approve($batch, $this->admin);
    }

    public function test_a_locked_cutover_period_blocks_the_import(): void
    {
        FinancialPeriod::where('code', '2026-02')->update(['status' => 'locked']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('locked');

        $this->import('opening_gl', "account_code,debit,credit,description\n1110,10.00,0.00,Cash\n3010,0.00,10.00,Surplus\n", 'locked.csv', '2026-02-01');
    }

    public function test_adoption_dates_are_not_reset_by_migration_cutover(): void
    {
        app(OpeningBalanceService::class)->recordAdoption('2026-01-01', '2026-02-01', $this->approver);

        $batch = $this->import('opening_gl', "account_code,debit,credit,description\n1110,10.00,0.00,Cash\n3010,0.00,10.00,Surplus\n", 'adoption.csv', '2026-02-01');
        $this->approveAndPost($batch);

        $adoption = AccountingSetting::adoption();

        $this->assertNotNull($adoption);
        $this->assertSame('2026-01-01', $adoption->value['adoption_start_date']);
        $this->assertSame('2026-02-01', $adoption->value['migration_cutover_date']);
    }

    public function test_gl_control_balances_cannot_double_count_customer_opening_debt(): void
    {
        $customer = $this->makeCustomer($this->council, $this->admin);

        $customerBatch = $this->import(
            'opening_customer',
            "account_number,type,reference,amount,due_date,description\n{$customer['account']->account_number},invoice,OB-X,100.00,2026-02-28,Invoice\n",
            'customer-conflict.csv',
            '2026-02-01',
        );
        app(OpeningBalanceService::class)->approve($customerBatch, $this->approver);

        $gl = $this->import(
            'opening_gl',
            "account_code,debit,credit,description\n1310,100.00,0.00,Rates receivable\n3010,0.00,100.00,Surplus\n",
            'gl-conflict.csv',
            '2026-02-01',
        );
        app(OpeningBalanceService::class)->approve($gl, $this->approver);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('receivable control accounts');

        app(OpeningBalanceService::class)->post($gl, $this->approver);
    }

    public function test_unknown_dimensions_and_malformed_money_produce_row_errors(): void
    {
        $batch = $this->import('opening_gl', "account_code,debit,credit,dimensions\n1110,10.00,garbage,\n3010,0.00,10.00,\"{\"\"unknown\"\":\"\"value\"\"}\"\n", 'invalid-fields.csv', '2026-02-01');
        $this->assertSame(2, $batch->error_count);
        $errors = json_encode($batch->rows->pluck('errors'));
        $this->assertStringContainsString('Invalid monetary', $errors);
        $this->assertStringContainsString('Unknown accounting dimension', $errors);
    }

    public function test_duplicate_gl_account_dimension_combinations_are_rejected(): void
    {
        $batch = $this->import('opening_gl', "account_code,debit,credit\n1110,10.00,0.00\n1110,10.00,0.00\n3010,0.00,20.00\n", 'duplicate-gl.csv', '2026-02-01');
        $this->assertGreaterThan(0, $batch->error_count);
        $this->assertStringContainsString('Duplicate GL account', json_encode($batch->rows->pluck('errors')));
    }

    public function test_customer_credit_control_is_reconciled_and_protected_from_double_counting(): void
    {
        $customer = $this->makeCustomer($this->council, $this->admin);
        $batch = $this->import('opening_customer', "account_number,type,reference,amount\n{$customer['account']->account_number},deposit,DEP-1,50.00\n", 'deposit.csv', '2026-02-01');
        $this->approveAndPost($batch);
        $this->assertTrue($batch->fresh()->reconciliation['credit_reconciles']);
        $this->assertSame(0, Receipt::count());
        $gl = $this->import('opening_gl', "account_code,debit,credit\n3010,50.00,0.00\n2050,0.00,50.00\n", 'credit-control.csv', '2026-02-01');
        app(OpeningBalanceService::class)->approve($gl, $this->approver);
        $this->expectException(\DomainException::class);
        app(OpeningBalanceService::class)->post($gl, $this->approver);
    }

    private function import(string $type, string $csv, string $fileName, string $cutover): MigrationBatch
    {
        $this->useCouncil($this->council, $this->adminMembership);

        return app(OpeningBalanceService::class)->import($type, $csv, $fileName, $cutover, 'USD', $this->admin);
    }

    private function approveAndPost(MigrationBatch $batch): void
    {
        app(OpeningBalanceService::class)->approve($batch, $this->approver);
        app(OpeningBalanceService::class)->post($batch->fresh(), $this->approver);
    }
}
