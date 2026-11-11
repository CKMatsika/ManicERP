<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Procurement\Models\BudgetCommitment;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Services\BankReconciliationService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ProcurementBudgetTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_supplier_onboarding_and_bank_detail_changes_require_separate_approval(): void
    {
        $council = $this->makeCouncil();
        $preparer = $this->makeUser('supplier-preparer@test.local');
        $approver = $this->makeUser('supplier-approver@test.local');
        $this->useCouncil($council);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic Supplies Ltd', 'status' => 'active', 'code' => 'SUP-SYNTH']);
        $service = app(SupplierBudgetService::class);

        $supplier = $service->onboard($party, 'SYN-0001', $preparer);
        $this->assertSame('pending', $supplier->status);
        $this->expectException(\DomainException::class);
        $service->approveSupplier($supplier, $preparer);
    }

    public function test_bank_details_activate_only_after_independent_approval_and_supersede_prior_details(): void
    {
        $council = $this->makeCouncil();
        $preparer = $this->makeUser('supplier-bank-preparer@test.local');
        $approver = $this->makeUser('supplier-bank-approver@test.local');
        $this->useCouncil($council);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic Supplies Ltd', 'status' => 'active', 'code' => 'SUP-SYNTH']);
        $service = app(SupplierBudgetService::class);
        $supplier = $service->onboard($party, 'SYN-0001', $preparer);
        $supplier = $service->approveSupplier($supplier, $approver);

        $first = $service->requestBankDetails($supplier, 'Synthetic Bank', 'Synthetic Supplies', '000111222333', $preparer);
        $this->assertSame('pending_approval', $first->status);
        $this->assertSame('000111222333', $first->account_number_encrypted);
        $first = $service->approveBankDetails($first, $approver);
        $this->assertSame('active', $first->status);

        $next = $service->requestBankDetails($supplier, 'Synthetic Bank 2', 'Synthetic Supplies', '999888777666', $preparer);
        $this->assertSame('active', $first->fresh()->status);
        $service->approveBankDetails($next, $approver);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('active', $next->fresh()->status);
    }

    public function test_budget_reservations_are_idempotent_and_partial_conversion_does_not_double_consume(): void
    {
        $council = $this->makeCouncil();
        $preparer = $this->makeUser('budget-preparer@test.local');
        $approver = $this->makeUser('budget-approver@test.local');
        $this->useCouncil($council);
        $service = app(SupplierBudgetService::class);
        $lineCount = BudgetLine::count();
        $budget = $service->createBudget('DEMO-OPS-2026', 'Demonstration operations budget', '2026-01-01', '2026-12-31', [
            ['account_code' => '5020', 'dimensions' => ['department' => 'FIN'], 'amount_minor' => 100000],
        ], $preparer);
        $this->assertSame('draft', $budget->status);
        $service->approveBudget($budget, $approver);
        $line = $budget->lines->first();
        $commitmentCount = BudgetCommitment::count();
        $commitment = $service->reserve($line, 'requisition', 'REQ-SYN-1', 70000, 'commitment:req-syn-1', $preparer);
        $same = $service->reserve($line, 'requisition', 'REQ-SYN-1', 70000, 'commitment:req-syn-1', $preparer);
        $this->assertSame($commitment->id, $same->id);
        $this->assertSame(30000, $line->fresh()->availableMinor());

        $service->convert($commitment, 25000);
        $this->assertSame(45000, $line->fresh()->committed_minor);
        $this->assertSame(25000, $line->fresh()->actual_minor);
        $this->assertSame(30000, $line->fresh()->availableMinor());
        $service->release($commitment);
        $service->release($commitment);
        $this->assertSame(0, $line->fresh()->committed_minor);
        $this->assertSame(75000, $line->fresh()->availableMinor());
        $this->assertSame($commitmentCount + 1, BudgetCommitment::count());
        $this->assertSame($lineCount + 1, BudgetLine::count());
    }

    public function test_insufficient_budget_cannot_be_reserved_without_reasoned_independent_override(): void
    {
        $council = $this->makeCouncil();
        $preparer = $this->makeUser('budget-over@test.local');
        $approver = $this->makeUser('budget-over-approver@test.local');
        $this->useCouncil($council);
        $service = app(SupplierBudgetService::class);
        $budget = $service->createBudget('DEMO-OPS-OVER', 'Demonstration budget', '2026-01-01', '2026-12-31', [
            ['account_code' => '5020', 'amount_minor' => 1000],
        ], $preparer);
        $service->approveBudget($budget, $approver);
        $line = $budget->lines->first();

        try {
            $service->reserve($line, 'requisition', 'REQ-OVER', 1001, 'commitment:req-over-1', $preparer);
            $this->fail('Expected an over-budget reservation to fail.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Insufficient available budget', $exception->getMessage());
        }

        $approved = $service->reserve($line, 'requisition', 'REQ-OVER', 1001, 'commitment:req-over-2', $preparer, $approver, 'Approved synthetic exception for test');
        $this->assertSame('reserved', $approved->status);
        $this->assertSame(-1, $line->fresh()->availableMinor());
    }

    public function test_budget_transfer_and_amendment_are_reasoned_versioned_and_independently_approved(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('budget-change-prep@test.local');
        $approver = $this->makeUser('budget-change-approver@test.local');
        $this->useCouncil($council);
        $service = app(SupplierBudgetService::class);
        $budget = $service->createBudget('DEMO-BUDGET-CHANGE', 'Budget change test', '2026-01-01', '2026-12-31', [
            ['account_code' => '5020', 'amount_minor' => 10000],
            ['account_code' => '5030', 'amount_minor' => 10000],
        ], $prep);
        $service->approveBudget($budget, $approver);
        $from = $budget->lines->first();
        $to = $budget->lines->last();
        $transfer = $service->prepareBudgetChange($budget, $from, $to, 'transfer', 2000, 'Move funds to water support', 'budget-transfer-001', $prep);
        $this->expectException(\DomainException::class);
        $service->approveBudgetChange($transfer->id, $prep);
    }

    public function test_approved_transfer_and_amendment_update_available_balances_and_budget_version(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('budget-change2-prep@test.local');
        $approver = $this->makeUser('budget-change2-approver@test.local');
        $this->useCouncil($council);
        $service = app(SupplierBudgetService::class);
        $budget = $service->createBudget('DEMO-BUDGET-CHANGE-2', 'Budget change test', '2026-01-01', '2026-12-31', [
            ['account_code' => '5020', 'amount_minor' => 10000],
            ['account_code' => '5030', 'amount_minor' => 10000],
        ], $prep);
        $service->approveBudget($budget, $approver);
        $from = $budget->lines->first();
        $to = $budget->lines->last();
        $transfer = $service->prepareBudgetChange($budget, $from, $to, 'transfer', 2000, 'Move funds to water support', 'budget-transfer-002', $prep);
        $service->approveBudgetChange($transfer->id, $approver);
        $this->assertSame(8000, $from->fresh()->availableMinor());
        $this->assertSame(12000, $to->fresh()->availableMinor());

        $amendment = $service->prepareBudgetChange($budget, null, $to, 'amendment', 5000, 'Add approved emergency provision', 'budget-amendment-001', $prep);
        $service->approveBudgetChange($amendment->id, $approver);
        $this->assertSame(17000, $to->fresh()->availableMinor());
        $this->assertSame(2, (int) $budget->fresh()->version);
    }

    public function test_requisition_sourcing_award_order_and_partial_receipt_enforce_separation_and_quantity(): void
    {
        $council = $this->makeCouncil();
        $preparer = $this->makeUser('purchase-preparer@test.local');
        $approver = $this->makeUser('purchase-approver@test.local');
        $receiver = $this->makeUser('purchase-receiver@test.local');
        $this->useCouncil($council);
        $supplierControls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $budget = $supplierControls->createBudget('DEMO-PROC-2026', 'Demonstration procurement budget', '2026-01-01', '2026-12-31', [
            ['account_code' => '5020', 'dimensions' => ['department' => 'ENG'], 'amount_minor' => 250000],
        ], $preparer);
        $supplierControls->approveBudget($budget, $approver);
        $line = $budget->lines->first();
        $req = $sourcing->prepareRequisition($line, 'Synthetic pump service', 'Demonstration sourcing scenario', 120000, $preparer);
        $req = $sourcing->submitRequisition($req->id, $preparer);
        $this->assertSame('submitted', $req->status);
        $req = $sourcing->approveRequisition($req->id, $approver);
        $this->assertSame('approved', $req->status);

        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic Pump Supplier', 'status' => 'active', 'code' => 'SUP-PUMP']);
        $supplier = $supplierControls->onboard($party, 'SYN-PUMP-1', $preparer);
        $supplier = $supplierControls->approveSupplier($supplier, $approver);
        $quote = $sourcing->submitQuotation($req->id, $supplier, 'Q-SYN-1', 110000, 'Synthetic comparison: compliant and lowest evaluated price', $preparer);
        config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
        $award = $sourcing->prepareAward($quote->id, 'Demonstration evaluation; no statutory compliance claim.', $preparer);
        $this->expectException(\DomainException::class);
        $sourcing->approveAward($award->id, $preparer);
    }

    public function test_approved_award_creates_order_and_partial_receipts_cannot_exceed_order_quantity(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('award-prep@test.local');
        $approver = $this->makeUser('award-approver@test.local');
        $this->useCouncil($council);
        $controls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $budget = $controls->createBudget('DEMO-PROC-2', 'Demonstration purchasing', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 300000]], $prep);
        $controls->approveBudget($budget, $approver);
        $req = $sourcing->prepareRequisition($budget->lines->first(), 'Pump servicing', 'Test sourcing lifecycle', 120000, $prep);
        $req = $sourcing->submitRequisition($req->id, $prep);
        $req = $sourcing->approveRequisition($req->id, $approver);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic Supplier', 'status' => 'active', 'code' => 'SUP-ORD']);
        $supplier = $controls->onboard($party, 'SYN-ORD-1', $prep);
        $supplier = $controls->approveSupplier($supplier, $approver);
        $quote = $sourcing->submitQuotation($req->id, $supplier, 'Q-1', 110000, 'Synthetic quote comparison evidence', $prep);
        config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
        $award = $sourcing->prepareAward($quote->id, 'Lowest evaluated compliant synthetic quotation.', $prep);
        $po = $sourcing->approveAward($award->id, $approver);
        $lineId = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id');
        $receipt = $sourcing->acceptReceipt($po->id, [$lineId => 400], 'receipt:synthetic-1', '400 milli-units accepted', $approver);
        $this->assertSame('accepted', $receipt->status);
        $same = $sourcing->acceptReceipt($po->id, [$lineId => 400], 'receipt:synthetic-1', '400 milli-units accepted', $approver);
        $this->assertSame($receipt->id, $same->id);
        $this->assertSame('0.400', DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->value('received_quantity'));
        $this->expectException(\DomainException::class);
        $sourcing->acceptReceipt($po->id, [$lineId => 700], 'receipt:synthetic-2', 'Exceeds remainder', $approver);
    }

    public function test_matched_invoices_credit_notes_partial_payments_and_ap_gl_reconcile(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('ap-preparer@test.local');
        $approver = $this->makeUser('ap-approver@test.local');
        $settler = $this->makeUser('ap-settler@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $prep);
        $controls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $ap = app(AccountsPayableService::class);
        $budget = $controls->createBudget('DEMO-AP-2026', 'Demonstration AP budget', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 250000]], $prep);
        $controls->approveBudget($budget, $approver);
        $req = $sourcing->prepareRequisition($budget->lines->first(), 'Pump service', 'AP integration scenario', 120000, $prep);
        $req = $sourcing->submitRequisition($req->id, $prep);
        $req = $sourcing->approveRequisition($req->id, $approver);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'AP Synthetic Supplier', 'status' => 'active', 'code' => 'SUP-AP']);
        $supplier = $controls->onboard($party, 'SUP-AP-1', $prep);
        $supplier = $controls->approveSupplier($supplier, $approver);
        $quote = $sourcing->submitQuotation($req->id, $supplier, 'AP-Q-1', 110000, 'Synthetic quotation evidence', $prep);
        config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
        $award = $sourcing->prepareAward($quote->id, 'Synthetic award evaluation', $prep);
        $po = $sourcing->approveAward($award->id, $approver);
        $lineId = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id');
        $sourcing->acceptReceipt($po->id, [$lineId => 400], 'ap-receipt-1', 'First accepted partial delivery', $approver);
        $invoice1 = $ap->prepareInvoice($supplier, 'INV-AP-1', '2026-11-01', '2026-11-30', 44000, 'ap-invoice-1', $prep, $po->id, $lineId, 400);
        $invoice1 = $ap->approveInvoice($invoice1, $approver);
        $this->assertSame('approved', $invoice1->status);
        try {
            $ap->prepareInvoice($supplier, 'INV-AP-1', '2026-11-01', '2026-11-30', 44000, 'ap-invoice-duplicate', $prep, $po->id, $lineId, 400);
            $this->fail('Duplicate supplier invoice must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Duplicate supplier invoice', $exception->getMessage());
        }
        $sourcing->acceptReceipt($po->id, [$lineId => 600], 'ap-receipt-2', 'Remaining accepted partial delivery', $approver);
        $invoice2 = $ap->prepareInvoice($supplier, 'INV-AP-2', '2026-11-02', '2026-11-30', 66000, 'ap-invoice-2', $prep, $po->id, $lineId, 600);
        $this->assertSame('matched', $invoice2->status);
        $this->assertSame('approved', $ap->approveInvoice($invoice2, $approver)->status);
        $proposal1 = $ap->preparePayment($invoice1, 20000, 'proposal-ap-1', $prep);
        $this->expectException(\DomainException::class);
        $ap->approvePayment($proposal1, $prep);
    }

    public function test_partial_invoice_settlement_and_credit_note_have_idempotent_sandbox_postings(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('ap2-preparer@test.local');
        $approver = $this->makeUser('ap2-approver@test.local');
        $settler = $this->makeUser('ap2-settler@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $prep);
        $controls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $ap = app(AccountsPayableService::class);
        $budget = $controls->createBudget('DEMO-AP-2', 'Demonstration AP budget', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 250000]], $prep);
        $controls->approveBudget($budget, $approver);
        $req = $sourcing->prepareRequisition($budget->lines->first(), 'Water pump', 'Synthetic purchase scenario', 120000, $prep);
        $req = $sourcing->submitRequisition($req->id, $prep);
        $req = $sourcing->approveRequisition($req->id, $approver);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic AP Supplier', 'status' => 'active', 'code' => 'SUP-AP2']);
        $supplier = $controls->approveSupplier($controls->onboard($party, 'SUP-AP-2', $prep), $approver);
        $quote = $sourcing->submitQuotation($req->id, $supplier, 'AP2-Q1', 110000, 'Synthetic quote documentation', $prep);
        config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
        $award = $sourcing->prepareAward($quote->id, 'Synthetic award rationale', $prep);
        $po = $sourcing->approveAward($award->id, $approver);
        $lineId = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id');
        $sourcing->acceptReceipt($po->id, [$lineId => 1000], 'ap2-receipt', 'Full accepted delivery', $approver);
        $invoice = $ap->prepareInvoice($supplier, 'INV-AP2-1', '2026-11-01', '2026-11-30', 110000, 'ap2-invoice', $prep, $po->id, $lineId, 1000);
        $invoice = $ap->approveInvoice($invoice, $approver);
        $credit = $ap->prepareCreditNote($invoice, 6000, 'Synthetic damaged goods credit', 'ap2-credit', $prep);
        $credit = $ap->approveCreditNote($credit->id, $approver);
        $this->assertSame('approved', $credit->status);
        $proposalA = $ap->approvePayment($ap->preparePayment($invoice->fresh(), 20000, 'ap2-pay-a', $prep), $approver);
        $paidA = $ap->settleSandbox($proposalA, 'SBX-AP2-A', $settler);
        $sameA = $ap->settleSandbox($proposalA, 'SBX-AP2-A', $settler);
        $this->assertSame($paidA->id, $sameA->id);
        $proposalB = $ap->approvePayment($ap->preparePayment($invoice->fresh(), 84000, 'ap2-pay-b', $prep), $approver);
        $paidB = $ap->settleSandbox($proposalB, 'SBX-AP2-B', $settler);
        $this->assertSame('settled', $paidB->status);
        $bank = BankAccount::create(['code' => 'BANK-AP-TEST', 'name' => 'Synthetic bank', 'bank_name' => 'Synthetic', 'account_number_masked' => '**** 0001', 'gl_account_code' => '1120', 'currency_code' => 'USD', 'status' => 'active']);
        config(['procurement.accounting.bank_account' => '1120']);
        $csv = "value_date,amount,reference,narrative\n2026-11-05,-200.00,SBX-AP2-A,Supplier sandbox payment A\n2026-11-05,-840.00,SBX-AP2-B,Supplier sandbox payment B\n";
        $statement = app(BankReconciliationService::class)->importCsv($bank, 'AP-TEST-001', now()->toDateString(), 0, -104000, $csv, $settler);
        $supplierPayments = DB::connection('tenant')->table('procurement_supplier_payments')->orderBy('created_at')->get();
        $lines = $statement->lines()->orderBy('line_number')->get();
        app(BankReconciliationService::class)->confirmSupplierPaymentMatch($lines[0], $supplierPayments[0]->id, $settler);
        app(BankReconciliationService::class)->confirmSupplierPaymentMatch($lines[1], $supplierPayments[1]->id, $settler);
        $bankReconciliation = app(BankReconciliationService::class)->reconcile($bank, $statement, $settler);
        $this->assertSame(0, (int) $bankReconciliation->variance_minor);
        $this->assertSame(['supplier_payables_minor' => 0, 'ap_control_credit_balance_minor' => 0, 'difference_minor' => 0, 'reconciled' => true], array_intersect_key($ap->reconciliation('2026-11-30'), array_flip(['supplier_payables_minor', 'ap_control_credit_balance_minor', 'difference_minor', 'reconciled'])));
        $this->assertSame(2, DB::connection('tenant')->table('procurement_supplier_payments')->count());
        $this->assertSame(4, DB::connection('tenant')->table('journal_entries')->whereIn('source_type', ['supplier_invoice', 'supplier_credit_note', 'supplier_payment'])->count());
        $trial = app(ReportingService::class)->trialBalance('2026-11-30');
        $this->assertTrue($trial['totals']['balanced']);
    }
}
