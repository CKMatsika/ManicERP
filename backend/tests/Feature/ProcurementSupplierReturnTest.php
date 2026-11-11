<?php

namespace Tests\Feature;

use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\Party;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ProcurementSupplierReturnTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_supplier_return_reduces_received_quantity_and_posts_credit_for_invoiced_portion(): void
    {
        $council = $this->makeCouncil();
        $prep = $this->makeUser('sr-prep@test.local');
        $approver = $this->makeUser('sr-approver@test.local');
        $this->useCouncil($council);
        $this->seedAccounting($council, $prep);

        $controls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $ap = app(AccountsPayableService::class);

        $budget = $controls->createBudget('SR-BUD', 'Return test', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 250000]], $prep);
        $controls->approveBudget($budget, $approver);
        $req = $sourcing->prepareRequisition($budget->lines->first(), 'Pump', 'Return scenario', 100000, $prep);
        $req = $sourcing->submitRequisition($req->id, $prep);
        $req = $sourcing->approveRequisition($req->id, $approver);

        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'SR Supplier', 'status' => 'active', 'code' => 'SUP-SR']);
        $supplier = $controls->approveSupplier($controls->onboard($party, 'SUP-SR-1', $prep), $approver);
        $quote = $sourcing->submitQuotation($req->id, $supplier, 'SR-Q1', 100000, 'evidence', $prep);
        config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
        $award = $sourcing->prepareAward($quote->id, 'award rationale', $prep);
        $po = $sourcing->approveAward($award->id, $approver);
        $lineId = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id');

        $sourcing->acceptReceipt($po->id, [$lineId => 1000], 'sr-receipt', 'received', $approver);

        $invoice = $ap->prepareInvoice($supplier, 'INV-SR-1', '2026-11-01', '2026-11-30', 100000, 'sr-invoice', $prep, $po->id, $lineId, 1000);
        $invoice = $ap->approveInvoice($invoice, $approver);

        // prepare + approve a return of 400 of 1000
        $ret = $sourcing->prepareSupplierReturn($po->id, $lineId, 400, 'Damaged goods', $prep);
        $this->assertSame('pending_approval', $ret->status);
        $approved = $sourcing->approveSupplierReturn($ret->id, $approver);
        $this->assertSame('approved', $approved->status);

        $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->first();
        $this->assertEquals(0.6, (float) $line->received_quantity);
        $this->assertEquals(0.6, (float) $line->invoiced_quantity);

        // credit amount = 40% of 100000 = 40000
        $this->assertSame(40000, (int) $approved->credit_amount_minor);

        // rejection path
        $ret2 = $sourcing->prepareSupplierReturn($po->id, $lineId, 100, 'More damage', $prep);
        $rejected = $sourcing->rejectSupplierReturn($ret2->id, 'Not approved', $approver);
        $this->assertSame('rejected', $rejected->status);
    }
}
