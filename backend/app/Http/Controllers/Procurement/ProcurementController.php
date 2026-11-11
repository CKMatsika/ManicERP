<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Procurement\Models\Budget;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierBankAccount;
use App\Modules\Procurement\Models\SupplierInvoice;
use App\Modules\Procurement\Models\SupplierPaymentProposal;
use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function index(AccountsPayableService $ap, ProcurementDocumentService $documents): Response
    {
        $date = now()->toDateString();

        return Inertia::render('Procurement/Index', [
            'suppliers' => Supplier::with('party')->withCount('bankAccounts')->orderBy('supplier_number')->get()->map(fn ($s) => ['id' => $s->id, 'supplier_number' => $s->supplier_number, 'name' => $s->party->display_name, 'status' => $s->status, 'bank_change_count' => $s->bank_accounts_count]),
            'parties' => Party::query()->where('party_type', 'organisation')->where('status', 'active')->whereDoesntHave('roles', fn ($q) => $q->where('role', 'supplier'))->orderBy('display_name')->get(['id', 'display_name']),
            'bankChanges' => DB::connection('tenant')->table('procurement_supplier_bank_accounts as b')->join('procurement_suppliers as s', 's.id', '=', 'b.supplier_id')->join('parties as p', 'p.id', '=', 's.party_id')->select('b.id', 's.supplier_number', 'p.display_name', 'b.bank_name', 'b.account_name', 'b.status', 'b.submitted_by', 'b.approved_by')->orderByDesc('b.created_at')->get(),
            'budgets' => Budget::with('lines')->orderByDesc('created_at')->get()->map(fn (Budget $b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'status' => $b->status, 'version' => (int) $b->version, 'period_start' => $b->period_start->toDateString(), 'period_end' => $b->period_end->toDateString(), 'lines' => $b->lines->map(fn ($l) => ['id' => $l->id, 'account_code' => $l->account_code, 'dimensions' => $l->dimensions, 'approved_minor' => (int) $l->approved_minor, 'committed_minor' => (int) $l->committed_minor, 'actual_minor' => (int) $l->actual_minor, 'available_minor' => $l->availableMinor()])]),
            'budgetChanges' => DB::connection('tenant')->table('procurement_budget_changes as c')->join('procurement_budgets as b', 'b.id', '=', 'c.budget_id')->leftJoin('procurement_budget_lines as f', 'f.id', '=', 'c.from_line_id')->join('procurement_budget_lines as t', 't.id', '=', 'c.to_line_id')->select('c.*', 'b.code as budget_code', 'f.account_code as from_account_code', 't.account_code as to_account_code')->orderByDesc('c.created_at')->get(),
            'requisitions' => DB::connection('tenant')->table('procurement_requisitions')->orderByDesc('created_at')->get(),
            'quotations' => DB::connection('tenant')->table('procurement_quotations as q')->join('procurement_suppliers as s', 's.id', '=', 'q.supplier_id')->select('q.*', 's.supplier_number')->orderByDesc('q.created_at')->get(),
            'awards' => DB::connection('tenant')->table('procurement_awards as a')->join('procurement_requisitions as r', 'r.id', '=', 'a.requisition_id')->select('a.*', 'r.reference as requisition_reference')->orderByDesc('a.created_at')->get(),
            'purchaseOrders' => DB::connection('tenant')->table('procurement_purchase_orders as po')->join('procurement_suppliers as s', 's.id', '=', 'po.supplier_id')->join('parties as p', 'p.id', '=', 's.party_id')->select('po.*', 's.supplier_number', 'p.display_name as supplier_name')->orderByDesc('po.created_at')->get(),
            'orderLines' => DB::connection('tenant')->table('procurement_purchase_order_lines as l')->join('procurement_purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')->select('l.*', 'po.reference as purchase_order_reference')->orderBy('po.reference')->get(),
            'amendments' => DB::connection('tenant')->table('procurement_order_amendments')->orderByDesc('created_at')->get(),
            'orderEvents' => DB::connection('tenant')->table('procurement_order_events')->orderByDesc('occurred_at')->get(),
            'reopenRequests' => DB::connection('tenant')->table('procurement_order_reopen_requests')->orderByDesc('created_at')->get(),
            'documents' => $documents->visibleVersions(request()->user()),
            'documentTargets' => [
                'supplier' => Supplier::with('party')->orderBy('supplier_number')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->supplier_number.' · '.$row->party->display_name]),
                'quotation' => DB::connection('tenant')->table('procurement_quotations as q')->join('procurement_requisitions as r', 'r.id', '=', 'q.requisition_id')->select('q.id', 'q.quote_reference', 'r.reference as requisition_reference')->orderByDesc('q.created_at')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->requisition_reference.' · '.$row->quote_reference]),
                'purchase_order' => DB::connection('tenant')->table('procurement_purchase_orders')->orderByDesc('created_at')->get(['id', 'reference'])->map(fn ($row) => ['id' => $row->id, 'label' => $row->reference]),
                'receipt' => DB::connection('tenant')->table('procurement_receipts as r')->join('procurement_purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')->select('r.id', 'r.reference', 'p.reference as order_reference')->orderByDesc('r.created_at')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->reference.' · '.$row->order_reference]),
                'invoice' => SupplierInvoice::orderByDesc('created_at')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->supplier_invoice_number]),
            ],
            'invoices' => SupplierInvoice::with('supplier.party')->orderByDesc('created_at')->get()->map(fn ($i) => ['id' => $i->id, 'supplier' => $i->supplier->party->display_name, 'supplier_invoice_number' => $i->supplier_invoice_number, 'amount_minor' => (int) $i->total_minor, 'paid_minor' => (int) $i->paid_minor, 'credited_minor' => (int) $i->credited_minor, 'status' => $i->status, 'match_exception_reason' => $i->match_exception_reason, 'due_date' => $i->due_date]),
            'proposals' => DB::connection('tenant')->table('procurement_payment_proposals as p')->join('procurement_supplier_invoices as i', 'i.id', '=', 'p.invoice_id')->select('p.*', 'i.supplier_invoice_number')->orderByDesc('p.created_at')->get(),
            'reports' => $ap->reports($date),
            'demonstrationOnly' => true,
        ]);
    }

    public function action(Request $request, PermissionService $permissions, SupplierBudgetService $supplierBudget, SourcingService $sourcing, AccountsPayableService $ap): RedirectResponse
    {
        $data = $request->validate(['operation' => 'required|string|in:supplier.create,supplier.approve,bank.request,bank.approve,budget.create,budget.approve,budget.change.create,budget.change.approve,requisition.create,requisition.submit,requisition.approve,quote.submit,award.prepare,award.approve,order.cancel,order.amend.prepare,order.amend.approve,order.amend.reject,order.cancel.partial,order.close,order.reopen.request,order.reopen.approve,receipt.accept,invoice.create,invoice.exception.approve,invoice.approve,credit.create,credit.approve,payment.create,payment.approve,payment.settle']);
        $request->validate(match ($data['operation']) {
            'supplier.create' => ['party_id' => 'required|ulid', 'supplier_number' => 'required|string|max:40'],
            'supplier.approve' => ['supplier_id' => 'required|ulid'],
            'bank.request' => ['supplier_id' => 'required|ulid', 'bank_name' => 'required|string|max:120', 'account_name' => 'required|string|max:160', 'account_number' => 'required|string|max:100'],
            'bank.approve' => ['bank_change_id' => 'required|ulid'],
            'budget.create' => ['code' => 'required|string|max:64', 'name' => 'required|string|max:180', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'account_code' => 'required|string|max:32', 'amount_minor' => 'required|integer|min:1'],
            'budget.approve' => ['budget_id' => 'required|ulid'],
            'budget.change.create' => ['budget_id' => 'required|ulid', 'from_line_id' => 'nullable|ulid', 'to_line_id' => 'required|ulid', 'change_type' => 'required|in:transfer,amendment', 'amount_minor' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'idempotency_key' => 'required|string|max:191'],
            'budget.change.approve' => ['budget_change_id' => 'required|ulid'],
            'requisition.create' => ['budget_line_id' => 'required|ulid', 'title' => 'required|string|max:180', 'justification' => 'required|string|max:3000', 'estimate_minor' => 'required|integer|min:1'],
            'requisition.submit' => ['requisition_id' => 'required|ulid', 'budget_override_approver_id' => 'nullable|ulid', 'budget_override_reason' => 'nullable|required_with:budget_override_approver_id|string|max:2000'],
            'requisition.approve' => ['requisition_id' => 'required|ulid'],
            'quote.submit' => ['requisition_id' => 'required|ulid', 'supplier_id' => 'required|ulid', 'quote_reference' => 'required|string|max:100', 'amount_minor' => 'required|integer|min:1', 'evaluation_evidence' => 'required|string|max:5000'],
            'award.prepare' => ['quotation_id' => 'required|ulid', 'evaluation_summary' => 'required|string|max:5000'],
            'award.approve' => ['award_id' => 'required|ulid'],
            'order.cancel' => ['purchase_order_id' => 'required|ulid', 'reason' => 'required|string|max:2000'],
            'order.amend.prepare' => ['purchase_order_id' => 'required|ulid', 'purchase_order_line_id' => 'required|ulid', 'quantity_milli' => 'required|integer|min:1', 'unit_price_minor' => 'nullable|integer|min:1', 'effective_date' => 'required|date|after_or_equal:today', 'reason' => 'required|string|max:2000'],
            'order.amend.approve' => ['amendment_id' => 'required|ulid'],
            'order.amend.reject' => ['amendment_id' => 'required|ulid', 'reason' => 'required|string|max:2000'],
            'order.cancel.partial' => ['purchase_order_id' => 'required|ulid', 'purchase_order_line_id' => 'required|ulid', 'quantity_milli' => 'required|integer|min:1', 'reason' => 'required|string|max:2000'],
            'order.close' => ['purchase_order_id' => 'required|ulid', 'reason' => 'required|string|max:2000'],
            'order.reopen.request' => ['purchase_order_id' => 'required|ulid', 'reason' => 'required|string|max:2000'],
            'order.reopen.approve' => ['reopen_request_id' => 'required|ulid'],
            'receipt.accept' => ['purchase_order_id' => 'required|ulid', 'purchase_order_line_id' => 'required|ulid', 'quantity_milli' => 'required|integer|min:1', 'idempotency_key' => 'required|string|max:191', 'note' => 'required|string|max:2000'],
            'invoice.create' => ['supplier_id' => 'required|ulid', 'invoice_number' => 'required|string|max:100', 'invoice_date' => 'required|date', 'due_date' => 'required|date|after_or_equal:invoice_date', 'amount_minor' => 'required|integer|min:1', 'idempotency_key' => 'required|string|max:191', 'purchase_order_id' => 'required|ulid', 'purchase_order_line_id' => 'required|ulid', 'quantity_milli' => 'required|integer|min:1'],
            'invoice.exception.approve' => ['invoice_id' => 'required|ulid', 'reason' => 'required|string|max:2000'],
            'invoice.approve' => ['invoice_id' => 'required|ulid'],
            'credit.create' => ['invoice_id' => 'required|ulid', 'amount_minor' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'idempotency_key' => 'required|string|max:191'],
            'credit.approve' => ['credit_id' => 'required|ulid'],
            'payment.create' => ['invoice_id' => 'required|ulid', 'amount_minor' => 'required|integer|min:1', 'idempotency_key' => 'required|string|max:191'],
            'payment.approve' => ['proposal_id' => 'required|ulid'],
            'payment.settle' => ['proposal_id' => 'required|ulid', 'settlement_reference' => 'required|string|max:191'],
        });
        $actor = $request->user();
        $permission = match ($data['operation']) {
            'supplier.create' => 'procurement.supplier.manage', 'supplier.approve' => 'procurement.supplier.approve',
            'bank.request' => 'procurement.supplier.bank.change', 'bank.approve' => 'procurement.supplier.bank.approve',
            'budget.create' => 'procurement.budget.manage', 'budget.approve' => 'procurement.budget.approve',
            'budget.change.create' => 'procurement.budget.change.manage', 'budget.change.approve' => 'procurement.budget.change.approve',
            'requisition.create', 'requisition.submit' => 'procurement.requisition.manage', 'requisition.approve' => 'procurement.requisition.approve',
            'quote.submit', 'award.prepare' => 'procurement.sourcing.manage', 'award.approve' => 'procurement.award.approve',
            'order.cancel' => 'procurement.purchase_order.cancel', 'order.amend.prepare' => 'procurement.purchase_order.amend',
            'order.amend.approve', 'order.amend.reject' => 'procurement.purchase_order.amend.approve',
            'order.cancel.partial' => 'procurement.purchase_order.cancel.partial', 'order.close' => 'procurement.purchase_order.close',
            'order.reopen.request' => 'procurement.purchase_order.reopen', 'order.reopen.approve' => 'procurement.purchase_order.reopen.approve',
            'receipt.accept' => 'procurement.receipt.accept', 'invoice.create', 'payment.create' => 'procurement.invoice.manage',
            'invoice.exception.approve' => 'procurement.invoice.exception.approve', 'invoice.approve' => 'procurement.invoice.approve',
            'credit.create' => 'procurement.invoice.manage', 'credit.approve' => 'procurement.credit.approve',
            'payment.approve' => 'procurement.payment.approve', 'payment.settle' => 'procurement.payment.settle',
        };
        $permissions->authorize($permission);
        if ($data['operation'] === 'requisition.submit' && ($request->filled('budget_override_approver_id') || $request->filled('budget_override_reason'))) {
            $permissions->authorize('procurement.budget.override');
            $overrideApprover = User::findOrFail($request->string('budget_override_approver_id'));
            abort_unless($permissions->allows($overrideApprover, 'procurement.budget.override'), 403);
            abort_if($overrideApprover->is($actor), 422, 'Budget override approval must be independent.');
        }
        try {
            $this->dispatchAction($data['operation'], $request, $supplierBudget, $sourcing, $ap, $permissions);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Procurement workflow updated. Any displayed settlement is sandbox-only; no real money moved.');
    }

    private function dispatchAction(string $operation, Request $request, SupplierBudgetService $controls, SourcingService $sourcing, AccountsPayableService $ap, PermissionService $permissions): void
    {
        $actor = $request->user();
        match ($operation) {
            'supplier.create' => $controls->onboard(Party::findOrFail($request->string('party_id')), $request->string('supplier_number'), $actor),
            'supplier.approve' => $controls->approveSupplier(Supplier::findOrFail($request->string('supplier_id')), $actor),
            'bank.request' => $controls->requestBankDetails(Supplier::findOrFail($request->string('supplier_id')), $request->string('bank_name'), $request->string('account_name'), $request->string('account_number'), $actor),
            'bank.approve' => $controls->approveBankDetails(SupplierBankAccount::findOrFail($request->string('bank_change_id')), $actor),
            'budget.create' => $controls->createBudget($request->string('code'), $request->string('name'), $request->string('period_start'), $request->string('period_end'), [['account_code' => $request->string('account_code'), 'dimensions' => [], 'amount_minor' => $request->integer('amount_minor')]], $actor),
            'budget.approve' => $controls->approveBudget(Budget::findOrFail($request->string('budget_id')), $actor),
            'budget.change.create' => $controls->prepareBudgetChange(Budget::findOrFail($request->string('budget_id')), $request->filled('from_line_id') ? BudgetLine::findOrFail($request->string('from_line_id')) : null, BudgetLine::findOrFail($request->string('to_line_id')), $request->string('change_type'), $request->integer('amount_minor'), $request->string('reason'), $request->string('idempotency_key'), $actor),
            'budget.change.approve' => $controls->approveBudgetChange($request->string('budget_change_id'), $actor),
            'requisition.create' => $sourcing->prepareRequisition(BudgetLine::findOrFail($request->string('budget_line_id')), $request->string('title'), $request->string('justification'), $request->integer('estimate_minor'), $actor),
            'requisition.submit' => $sourcing->submitRequisition($request->string('requisition_id'), $actor, $request->filled('budget_override_approver_id') ? User::findOrFail($request->string('budget_override_approver_id')) : null, $request->input('budget_override_reason')),
            'requisition.approve' => $sourcing->approveRequisition($request->string('requisition_id'), $actor),
            'quote.submit' => $sourcing->submitQuotation($request->string('requisition_id'), Supplier::findOrFail($request->string('supplier_id')), $request->string('quote_reference'), $request->integer('amount_minor'), $request->string('evaluation_evidence'), $actor),
            'award.prepare' => $sourcing->prepareAward($request->string('quotation_id'), $request->string('evaluation_summary'), $actor),
            'award.approve' => $sourcing->approveAward($request->string('award_id'), $actor),
            'order.cancel' => $sourcing->cancelPurchaseOrder($request->string('purchase_order_id'), $request->string('reason'), $actor),
            'order.amend.prepare' => $sourcing->prepareOrderAmendment($request->string('purchase_order_id'), [$request->string('purchase_order_line_id') => ['quantity_milli' => $request->integer('quantity_milli'), ...($request->filled('unit_price_minor') ? ['unit_price_minor' => $request->integer('unit_price_minor')] : [])]], $request->string('reason'), $request->string('effective_date'), $actor),
            'order.amend.approve' => $sourcing->approveOrderAmendment($request->string('amendment_id'), $actor),
            'order.amend.reject' => $sourcing->rejectOrderAmendment($request->string('amendment_id'), $request->string('reason'), $actor),
            'order.cancel.partial' => $sourcing->cancelOutstandingQuantity($request->string('purchase_order_id'), $request->string('purchase_order_line_id'), $request->integer('quantity_milli'), $request->string('reason'), $actor),
            'order.close' => $sourcing->closePurchaseOrder($request->string('purchase_order_id'), $request->string('reason'), $actor),
            'order.reopen.request' => $sourcing->requestReopen($request->string('purchase_order_id'), $request->string('reason'), $actor),
            'order.reopen.approve' => $sourcing->approveReopen($request->string('reopen_request_id'), $actor),
            'receipt.accept' => $sourcing->acceptReceipt($request->string('purchase_order_id'), [$request->string('purchase_order_line_id') => $request->integer('quantity_milli')], $request->string('idempotency_key'), $request->string('note'), $actor),
            'invoice.create' => $ap->prepareInvoice(Supplier::findOrFail($request->string('supplier_id')), $request->string('invoice_number'), $request->string('invoice_date'), $request->string('due_date'), $request->integer('amount_minor'), $request->string('idempotency_key'), $actor, $request->string('purchase_order_id'), $request->string('purchase_order_line_id'), $request->integer('quantity_milli')),
            'invoice.exception.approve' => $ap->approveMatchException(SupplierInvoice::findOrFail($request->string('invoice_id')), $request->string('reason'), $actor),
            'invoice.approve' => $ap->approveInvoice(SupplierInvoice::findOrFail($request->string('invoice_id')), $actor),
            'credit.create' => $ap->prepareCreditNote(SupplierInvoice::findOrFail($request->string('invoice_id')), $request->integer('amount_minor'), $request->string('reason'), $request->string('idempotency_key'), $actor),
            'credit.approve' => $ap->approveCreditNote($request->string('credit_id'), $actor),
            'payment.create' => $ap->preparePayment(SupplierInvoice::findOrFail($request->string('invoice_id')), $request->integer('amount_minor'), $request->string('idempotency_key'), $actor),
            'payment.approve' => $ap->approvePayment(SupplierPaymentProposal::findOrFail($request->string('proposal_id')), $actor),
            'payment.settle' => $ap->settleSandbox(SupplierPaymentProposal::findOrFail($request->string('proposal_id')), $request->string('settlement_reference'), $actor),
        };
    }
}
