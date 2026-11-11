<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\AcceptanceStepRunner;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Procurement\Models\Budget;
use App\Modules\Procurement\Models\BudgetCommitment;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierInvoice;
use App\Modules\Procurement\Models\SupplierPaymentProposal;
use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\BankReconciliation;
use App\Modules\Revenue\Models\BankStatement;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Services\BankReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RunAcceptanceProcurementCommand extends Command
{
    protected $signature = 'manicerp:acceptance:procurement {--as-of= : Scenario date (YYYY-MM-DD)}';

    protected $description = 'Run the resumable synthetic requisition-to-AP acceptance scenario';

    public function handle(TenantManager $tenants, AcceptanceStepRunner $steps, SupplierBudgetService $controls, SourcingService $sourcing, AccountsPayableService $ap, BankReconciliationService $banking, ReportingService $reports): int
    {
        try {
            if (! app()->environment('local') || config('database.connections.pgsql.database') !== 'manicerp_acceptance_central') {
                throw new RuntimeException('Acceptance procurement requires APP_ENV=local and manicerp_acceptance_central.');
            }
            $council = Council::query()->where('code', 'ACCEPTANCE_DEMO')->firstOrFail();
            if (! $council->is_demo || $council->database_name !== 'manicerp_acceptance_demo' || ! $council->isActive()) {
                throw new RuntimeException('Acceptance council identity/database guard failed.');
            }
            $tenants->connect($council);
            if (DB::connection('tenant')->getDatabaseName() !== 'manicerp_acceptance_demo') {
                throw new RuntimeException('Tenant connection is outside the acceptance database.');
            }
            if (! $council->modules()->where('module_id', 'procurement')->where('status', 'enabled')->exists()) {
                throw new RuntimeException('Enable the isolated procurement module through the guarded acceptance setup first.');
            }

            $prep = User::where('email', 'admin@acceptance.test')->firstOrFail();
            $approver = User::where('email', 'finance-approver@acceptance.test')->firstOrFail();
            $settler = User::where('email', 'cashier@acceptance.test')->firstOrFail();
            $date = $this->option('as-of') ?: now()->toDateString();
            $party = Party::firstOrCreate(['code' => 'ACCEPT-SUPPLIER-001'], ['party_type' => 'organisation', 'display_name' => 'Acceptance Synthetic Pump Supplier', 'status' => 'active', 'created_by' => $prep->getKey()]);
            $result = [];

            $result['supplier'] = $steps->run('procurement.supplier.v1', ['party_code' => $party->code, 'supplier_number' => 'ACCEPT-SUP-001'], function () use ($controls, $party, $prep, $approver): array {
                $supplier = $controls->onboard($party, 'ACCEPT-SUP-001', $prep);
                if ($supplier->status === 'pending') {
                    $controls->approveSupplier($supplier, $approver);
                }

                return ['supplier_id' => $supplier->id, 'supplier_number' => $supplier->supplier_number, 'status' => $supplier->fresh()->status];
            });
            $supplier = Supplier::findOrFail($result['supplier']['supplier_id']);

            $result['bank_details'] = $steps->run('procurement.supplier.bank.v1', ['supplier_id' => $supplier->id, 'account_fingerprint' => hash('sha256', 'SYNTHETIC-ACCEPTANCE-001')], function () use ($controls, $supplier, $prep, $approver): array {
                $change = $supplier->bankAccounts()->where('status', 'active')->first();
                if (! $change) {
                    $change = $controls->requestBankDetails($supplier, 'Synthetic Acceptance Bank', 'Acceptance Synthetic Supplier', 'SYNTHETIC-ACCEPTANCE-001', $prep);
                    $change = $controls->approveBankDetails($change, $approver);
                }

                return ['bank_record_id' => $change->id, 'status' => $change->status, 'number_redacted' => true];
            });

            $result['budget'] = $steps->run('procurement.budget.v1', ['code' => 'ACCEPT-PROC-2026', 'amount_minor' => 250000, 'account_code' => '5020', 'period' => '2026'], function () use ($controls, $prep, $approver): array {
                $budget = Budget::where('code', 'ACCEPT-PROC-2026')->first();
                $budget ??= $controls->createBudget('ACCEPT-PROC-2026', 'Synthetic acceptance procurement budget', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'dimensions' => ['department' => 'ENG'], 'amount_minor' => 250000]], $prep);
                if ($budget->status === 'draft') {
                    $controls->approveBudget($budget, $approver);
                }

                return ['budget_id' => $budget->id, 'status' => $budget->fresh()->status, 'line_id' => $budget->lines()->firstOrFail()->id, 'approved_minor' => 250000];
            });
            $line = BudgetLine::findOrFail($result['budget']['line_id']);

            $result['requisition'] = $steps->run('procurement.requisition.v1', ['budget_line_id' => $line->id, 'estimate_minor' => 120000], function () use ($sourcing, $line, $prep): array {
                $req = $sourcing->prepareRequisition($line, 'Synthetic pump servicing', 'Acceptance scenario; demonstration sourcing only.', 120000, $prep);

                return ['requisition_id' => $req->id, 'reference' => $req->reference, 'status' => $req->status];
            });
            $result['requisition_submission'] = $steps->run('procurement.requisition.submit.v1', ['requisition_id' => $result['requisition']['requisition_id']], fn () => ['status' => $sourcing->submitRequisition($result['requisition']['requisition_id'], $prep)->status]);
            $result['requisition_approval'] = $steps->run('procurement.requisition.approve.v1', ['requisition_id' => $result['requisition']['requisition_id']], fn () => ['status' => $sourcing->approveRequisition($result['requisition']['requisition_id'], $approver)->status]);
            $result['quotation'] = $steps->run('procurement.quotation.v1', ['requisition_id' => $result['requisition']['requisition_id'], 'supplier_id' => $supplier->id, 'amount_minor' => 110000], function () use ($sourcing, $result, $supplier, $prep): array {
                $quote = $sourcing->submitQuotation($result['requisition']['requisition_id'], $supplier, 'ACCEPT-QUOTE-001', 110000, 'Synthetic evaluated quotation evidence; lowest evaluated demonstration bid.', $prep);

                return ['quotation_id' => $quote->id, 'amount_minor' => (int) $quote->quoted_minor];
            });
            $result['award'] = $steps->run('procurement.award.v1', ['quotation_id' => $result['quotation']['quotation_id']], fn () => ['award_id' => $sourcing->prepareAward($result['quotation']['quotation_id'], 'Demonstration evaluation only; no statutory procurement compliance asserted.', $prep)->id]);
            $result['purchase_order'] = $steps->run('procurement.purchase_order.v1', ['award_id' => $result['award']['award_id']], function () use ($sourcing, $result, $approver): array {
                $existing = DB::connection('tenant')->table('procurement_purchase_orders')->where('award_id', $result['award']['award_id'])->first();
                $po = $existing ?: $sourcing->approveAward($result['award']['award_id'], $approver);

                return ['purchase_order_id' => $po->id, 'reference' => $po->reference, 'amount_minor' => (int) $po->total_minor, 'line_id' => DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id')];
            });
            $result['commitment_adjustment'] = $steps->run('procurement.commitment_adjustment.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'awarded_minor' => $result['purchase_order']['amount_minor']], function () use ($controls, $result): array {
                $commitmentId = DB::connection('tenant')->table('procurement_purchase_orders as po')->join('procurement_requisitions as r', 'r.id', '=', 'po.requisition_id')->where('po.id', $result['purchase_order']['purchase_order_id'])->value('r.commitment_id');
                if ($commitmentId) {
                    $controls->reduceReservationTo(BudgetCommitment::findOrFail($commitmentId), (int) $result['purchase_order']['amount_minor']);
                }

                return ['awarded_minor' => (int) $result['purchase_order']['amount_minor'], 'remaining_reserved_minor' => (int) DB::connection('tenant')->table('procurement_commitments')->where('id', $commitmentId)->value('reserved_minor')];
            });
            $result['receipt'] = $steps->run('procurement.receipt.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'quantity_milli' => 400], function () use ($sourcing, $result, $approver): array {
                $receipt = $sourcing->acceptReceipt($result['purchase_order']['purchase_order_id'], [$result['purchase_order']['line_id'] => 400], 'ACCEPT-RECEIPT-001', '400 of 1000 milli-units accepted; balance remains open.', $approver);

                return ['receipt_id' => $receipt->id, 'status' => $receipt->status, 'accepted_quantity_milli' => 400, 'outstanding_quantity_milli' => 600];
            });
            $result['invoice'] = $steps->run('procurement.invoice.v1', ['supplier_id' => $supplier->id, 'purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'quantity_milli' => 400, 'amount_minor' => 44000], function () use ($ap, $supplier, $result, $prep, $date): array {
                $invoice = $ap->prepareInvoice($supplier, 'ACCEPT-INV-001', $date, now()->parse($date)->addDays(30)->toDateString(), 44000, 'acceptance:invoice:001', $prep, $result['purchase_order']['purchase_order_id'], $result['purchase_order']['line_id'], 400);

                return ['invoice_id' => $invoice->id, 'match_status' => $invoice->status, 'amount_minor' => (int) $invoice->total_minor];
            });
            $result['invoice_posting'] = $steps->run('procurement.invoice.approve.v1', ['invoice_id' => $result['invoice']['invoice_id']], fn () => ['status' => $ap->approveInvoice(SupplierInvoice::findOrFail($result['invoice']['invoice_id']), $approver)->status]);
            $result['payment_proposal'] = $steps->run('procurement.payment_proposal.v1', ['invoice_id' => $result['invoice']['invoice_id'], 'amount_minor' => 20000], fn () => ['proposal_id' => $ap->preparePayment(SupplierInvoice::findOrFail($result['invoice']['invoice_id']), 20000, 'acceptance:payment:001', $prep)->id]);
            $result['payment_approval'] = $steps->run('procurement.payment_approval.v1', ['proposal_id' => $result['payment_proposal']['proposal_id']], fn () => ['status' => $ap->approvePayment(SupplierPaymentProposal::findOrFail($result['payment_proposal']['proposal_id']), $approver)->status]);
            $result['settlement'] = $steps->run('procurement.sandbox_settlement.v1', ['proposal_id' => $result['payment_proposal']['proposal_id'], 'amount_minor' => 20000], fn () => ['payment_id' => $ap->settleSandbox(SupplierPaymentProposal::findOrFail($result['payment_proposal']['proposal_id']), 'SBX-ACCEPTANCE-AP-001', $settler)->id, 'adapter' => 'sandbox']);

            $result['bank_reconciliation'] = $steps->run('procurement.bank_reconciliation.v1', ['supplier_payment_id' => $result['settlement']['payment_id'], 'as_of' => $date], function () use ($banking, $result, $date, $approver): array {
                $payment = DB::connection('tenant')->table('procurement_supplier_payments')->where('id', $result['settlement']['payment_id'])->firstOrFail();
                $bank = BankAccount::where('code', 'BANK1')->firstOrFail();
                $statement = BankStatement::where('statement_number', 'ACCEPTANCE-AP-2026-001')->first();
                if (! $statement) {
                    $prior = (int) DB::connection('tenant')->table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('a.code', $bank->gl_account_code)->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $date)->where(fn ($q) => $q->where('e.source_type', '!=', 'supplier_payment')->orWhereNull('e.source_type')->orWhere('e.source_id', '!=', $payment->id))->selectRaw('COALESCE(SUM(l.debit_minor)-SUM(l.credit_minor),0) balance')->value('balance');
                    $closing = (int) $prior - (int) $payment->amount_minor;
                    $csv = "value_date,amount,reference,narrative\n{$date},-".number_format($payment->amount_minor / 100, 2, '.', '').',SBX-ACCEPTANCE-AP-001,Synthetic supplier sandbox settlement';
                    $statement = $banking->importCsv($bank, 'ACCEPTANCE-AP-2026-001', $date, (int) $prior, $closing, $csv, $approver);
                }
                $line = $statement->lines()->firstOrFail();
                if ($line->status === 'unmatched') {
                    $banking->confirmSupplierPaymentMatch($line, $payment->id, $approver);
                } elseif ($line->matched_supplier_payment_id !== $payment->id) {
                    throw new RuntimeException('Supplier bank statement line is matched to a different transaction.');
                }
                $reconciliation = BankReconciliation::where('bank_statement_id', $statement->id)->first() ?: $banking->reconcile($bank, $statement, $approver);

                return ['statement_id' => $statement->id, 'reconciliation_id' => $reconciliation->id, 'closing_minor' => (int) $statement->closing_balance_minor, 'cashbook_minor' => (int) $reconciliation->cashbook_balance_minor, 'variance_minor' => (int) $reconciliation->variance_minor];
            });

            $result['order_amendment'] = $steps->run('procurement.order_amendment.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'quantity_milli' => 1500, 'total_minor' => 165000], function () use ($sourcing, $result, $prep, $approver): array {
                $lineId = $result['purchase_order']['line_id'];
                $amendment = $sourcing->prepareOrderAmendment(
                    $result['purchase_order']['purchase_order_id'],
                    [$lineId => ['quantity_milli' => 1500]],
                    'Synthetic acceptance scope increase after initial partial delivery.',
                    now()->toDateString(),
                    $prep,
                );
                $approved = $sourcing->approveOrderAmendment($amendment->id, $approver);

                return ['amendment_id' => $approved->id, 'status' => $approved->status, 'from_version' => (int) $approved->from_version, 'to_version' => (int) $approved->to_version, 'before_minor' => (int) $approved->before_total_minor, 'after_minor' => (int) $approved->after_total_minor];
            });
            $result['order_partial_cancellation'] = $steps->run('procurement.order_partial_cancellation.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'line_id' => $result['purchase_order']['line_id'], 'quantity_milli' => 300], function () use ($sourcing, $result, $prep): array {
                $line = $sourcing->cancelOutstandingQuantity($result['purchase_order']['purchase_order_id'], $result['purchase_order']['line_id'], 300, 'Synthetic acceptance supplier cannot fulfil this outstanding portion.', $prep);

                return ['cancelled_quantity_milli' => (int) round((float) $line->cancelled_quantity * 1000), 'ordered_quantity_milli' => (int) round((float) $line->ordered_quantity * 1000)];
            });
            $result['receipt_remaining'] = $steps->run('procurement.receipt.remaining.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'quantity_milli' => 800], function () use ($sourcing, $result, $approver): array {
                $receipt = $sourcing->acceptReceipt($result['purchase_order']['purchase_order_id'], [$result['purchase_order']['line_id'] => 800], 'ACCEPT-RECEIPT-002', 'Remaining fulfillable 800 milli-units accepted after partial cancellation.', $approver);

                return ['receipt_id' => $receipt->id, 'status' => $receipt->status, 'accepted_quantity_milli' => 800];
            });
            $result['invoice_remaining'] = $steps->run('procurement.invoice.remaining.v1', ['supplier_id' => $supplier->id, 'purchase_order_id' => $result['purchase_order']['purchase_order_id'], 'quantity_milli' => 800, 'amount_minor' => 88000], function () use ($ap, $supplier, $result, $prep, $date): array {
                $invoice = $ap->prepareInvoice($supplier, 'ACCEPT-INV-002', $date, now()->parse($date)->addDays(30)->toDateString(), 88000, 'acceptance:invoice:002', $prep, $result['purchase_order']['purchase_order_id'], $result['purchase_order']['line_id'], 800);

                return ['invoice_id' => $invoice->id, 'match_status' => $invoice->status, 'amount_minor' => (int) $invoice->total_minor];
            });
            if ($result['invoice_remaining']['match_status'] === 'exception_pending') {
                $matchedInvoice = $ap->recheckInvoiceMatch(SupplierInvoice::findOrFail($result['invoice_remaining']['invoice_id']));
                $result['invoice_remaining']['match_status'] = $matchedInvoice->status;
            }
            $result['invoice_remaining_posting'] = $steps->run('procurement.invoice.remaining.approve.v1', ['invoice_id' => $result['invoice_remaining']['invoice_id']], fn () => ['status' => $ap->approveInvoice(SupplierInvoice::findOrFail($result['invoice_remaining']['invoice_id']), $approver)->status]);
            $result['order_closure'] = $steps->run('procurement.order_closure.v1', ['purchase_order_id' => $result['purchase_order']['purchase_order_id']], function () use ($sourcing, $result, $prep): array {
                $order = $sourcing->closePurchaseOrder($result['purchase_order']['purchase_order_id'], 'Synthetic order fulfilled for accepted quantity; outstanding supplier payable remains open.', $prep);

                return ['status' => $order->status, 'closed_at' => $order->closed_at, 'remaining_payable_allowed' => true];
            });
            $result['ap_reconciliation'] = $ap->reconciliation($date);
            $result['reports'] = $ap->reports($date);
            $result['trial_balance'] = $reports->trialBalance($date)['totals'];
            $commitmentId = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $result['purchase_order']['purchase_order_id'])->orderByDesc('created_at')->value('commitment_id');
            $budgetLine = BudgetLine::findOrFail($line->id);
            $result['budget_reconciliation'] = ['approved_minor' => (int) $budgetLine->approved_minor, 'committed_minor' => (int) $budgetLine->fresh()->committed_minor, 'actual_minor' => (int) $budgetLine->fresh()->actual_minor, 'available_minor' => (int) $budgetLine->fresh()->availableMinor(), 'commitment_reserved_minor' => (int) BudgetCommitment::findOrFail($commitmentId)->reserved_minor, 'commitment_converted_minor' => (int) BudgetCommitment::findOrFail($commitmentId)->converted_minor];
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
