<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Procurement\Models\BudgetCommitment;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierInvoice;
use App\Modules\Procurement\Models\SupplierPaymentProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Supplier invoice matching, AP posting and explicitly sandbox-only payment settlement. */
class AccountsPayableService
{
    public function __construct(private readonly PostingService $posting, private readonly AuditLogger $audit) {}

    public function prepareInvoice(Supplier $supplier, string $invoiceNumber, string $invoiceDate, string $dueDate, int $amountMinor, string $idempotencyKey, User $preparer, ?string $purchaseOrderId = null, ?string $poLineId = null, ?int $quantityMilli = null, ?string $nonPoExpenseCode = null): SupplierInvoice
    {
        if ($supplier->status !== 'approved' || $amountMinor <= 0 || trim($invoiceNumber) === '' || $dueDate < $invoiceDate) {
            throw new \DomainException('Invoice requires an approved supplier, positive amount, reference and valid dates.');
        }
        $existing = SupplierInvoice::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->supplier_id !== $supplier->id || (int) $existing->total_minor !== $amountMinor || $existing->supplier_invoice_number !== $invoiceNumber || $existing->purchase_order_id !== $purchaseOrderId || $existing->purchase_order_line_id !== $poLineId || (int) round((float) $existing->quantity * 1000) !== (int) ($quantityMilli ?? 0) || $existing->invoice_date->toDateString() !== $invoiceDate || $existing->due_date->toDateString() !== $dueDate) {
                throw new \DomainException('Invoice idempotency key was reused with different inputs.');
            }

            return $existing;
        }
        if (SupplierInvoice::where('supplier_id', $supplier->id)->where('supplier_invoice_number', $invoiceNumber)->exists()) {
            throw new \DomainException('Duplicate supplier invoice number.');
        }

        $accountCode = $nonPoExpenseCode;
        $status = 'exception_pending';
        $exception = null;
        if ($purchaseOrderId !== null && $poLineId !== null && $quantityMilli !== null) {
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->firstOrFail();
            $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $poLineId)->where('purchase_order_id', $po->id)->firstOrFail();
            if ($po->supplier_id !== $supplier->id || $po->status !== 'approved') {
                throw new \DomainException('Invoice supplier must match an active approved purchase order.');
            }
            $quantity = $quantityMilli / 1000;
            $receivedOpenMilli = (int) round((float) $line->received_quantity * 1000) - (int) round((float) $line->invoiced_quantity * 1000);
            $expected = (int) round($quantityMilli * (int) $line->unit_price_minor / 1000);
            if ($quantityMilli <= 0 || $quantityMilli > $receivedOpenMilli || $amountMinor !== $expected) {
                $exception = 'Invoice quantity or value is outside accepted and uninvoiced PO receipt tolerance.';
            } else {
                $status = 'matched';
            }
            $accountCode = $line->account_code;
        } elseif ($purchaseOrderId === null && $poLineId === null && $quantityMilli === null && $nonPoExpenseCode) {
            $exception = 'Non-PO invoice requires independent exception approval.';
        } else {
            throw new \DomainException('Provide a complete PO match or an explicitly coded non-PO exception.');
        }

        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_supplier_invoices')->insert([
            'id' => $id, 'reference' => 'APINV-'.strtoupper((string) Str::ulid()), 'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrderId, 'purchase_order_line_id' => $poLineId, 'expense_account_code' => $accountCode,
            'supplier_invoice_number' => $invoiceNumber, 'invoice_date' => $invoiceDate, 'due_date' => $dueDate,
            'currency_code' => 'USD', 'total_minor' => $amountMinor, 'quantity' => $quantityMilli === null ? null : $quantityMilli / 1000,
            'status' => $status, 'match_exception_reason' => $exception, 'prepared_by' => $preparer->getKey(),
            'idempotency_key' => $idempotencyKey, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice = SupplierInvoice::findOrFail($id);
        $this->audit->record(action: 'procurement.invoice.prepared', subject: $invoice, after: ['invoice_number' => $invoiceNumber, 'amount_minor' => $amountMinor, 'match_status' => $status], module: 'procurement');

        return $invoice;
    }

    /** Re-evaluate an unposted PO match after receiving or correcting match data. */
    public function recheckInvoiceMatch(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice) {
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'exception_pending' || ! $locked->purchase_order_id || ! $locked->purchase_order_line_id) {
                return $locked;
            }
            $order = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $locked->purchase_order_id)->lockForUpdate()->firstOrFail();
            $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $locked->purchase_order_line_id)->where('purchase_order_id', $order->id)->lockForUpdate()->firstOrFail();
            $quantityMilli = (int) round((float) $locked->quantity * 1000);
            $receivedOpenMilli = (int) round((float) $line->received_quantity * 1000) - (int) round((float) $line->invoiced_quantity * 1000);
            $expectedMinor = (int) round($quantityMilli * (int) $line->unit_price_minor / 1000);
            if ($order->status !== 'approved' || $order->supplier_id !== $locked->supplier_id || $quantityMilli <= 0 || $quantityMilli > $receivedOpenMilli || (int) $locked->total_minor !== $expectedMinor) {
                return $locked;
            }
            $locked->forceFill(['status' => 'matched', 'match_exception_reason' => null])->save();
            $this->audit->record(action: 'procurement.invoice.match_rechecked', subject: $locked, after: ['quantity_milli' => $quantityMilli, 'received_open_milli' => $receivedOpenMilli, 'amount_minor' => (int) $locked->total_minor, 'status' => 'matched'], module: 'procurement');

            return $locked->refresh();
        });
    }

    public function approveMatchException(SupplierInvoice $invoice, string $reason, User $approver): SupplierInvoice
    {
        if ($invoice->status !== 'exception_pending' || $invoice->prepared_by === $approver->getKey() || trim($reason) === '') {
            throw new \DomainException('A matching exception requires a reasoned independent approval.');
        }
        $invoice->forceFill(['status' => 'exception_approved', 'match_exception_reason' => trim($invoice->match_exception_reason.' Approved: '.$reason), 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
        $this->audit->record(action: 'procurement.invoice.match_exception_approved', subject: $invoice, after: ['invoice_id' => $invoice->id, 'reason' => $reason], module: 'procurement');

        return $invoice->refresh();
    }

    public function approveInvoice(SupplierInvoice $invoice, User $approver): SupplierInvoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice, $approver) {
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['matched', 'exception_approved'], true) || $locked->prepared_by === $approver->getKey()) {
                throw new \DomainException('Invoice approval requires a matched or approved-exception invoice and independent approver.');
            }
            $order = null;
            if ($locked->purchase_order_id) {
                $order = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $locked->purchase_order_id)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'approved') {
                    throw new \DomainException('An invoice cannot post against a cancelled or closed purchase order.');
                }
            }
            if (SupplierInvoice::where('supplier_id', $locked->supplier_id)->where('supplier_invoice_number', $locked->supplier_invoice_number)->where('id', '!=', $locked->id)->exists()) {
                throw new \DomainException('Duplicate supplier invoice number.');
            }
            $journal = $this->posting->post([
                'journal_type' => 'supplier_invoice', 'entry_date' => $locked->invoice_date,
                'description' => "Supplier invoice {$locked->supplier_invoice_number}", 'currency_code' => $locked->currency_code,
                'lines' => [
                    ['account_code' => $locked->expense_account_code, 'debit_minor' => (int) $locked->total_minor, 'memo' => "Invoice {$locked->supplier_invoice_number}"],
                    ['account_code' => config('procurement.accounting.ap_control', '2100'), 'credit_minor' => (int) $locked->total_minor, 'party_id' => $locked->supplier->party_id, 'memo' => "Supplier payable {$locked->supplier_invoice_number}"],
                ],
                'source_type' => 'supplier_invoice', 'source_id' => $locked->id,
                'idempotency_key' => 'procurement:invoice:'.$locked->id, 'created_by' => $approver->getKey(),
            ]);
            $locked->forceFill(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
            if ($locked->purchase_order_line_id) {
                $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $locked->purchase_order_line_id)->lockForUpdate()->firstOrFail();
                DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $line->id)->update(['invoiced_quantity' => (float) $line->invoiced_quantity + (float) $locked->quantity, 'updated_at' => now()]);
                $orderCommitment = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $locked->purchase_order_id)->where('status', 'active')->first();
                if ($orderCommitment?->commitment_id) {
                    $commitment = BudgetCommitment::findOrFail($orderCommitment->commitment_id);
                    app(SupplierBudgetService::class)->convert($commitment, (int) $locked->total_minor);
                }
            }
            $this->audit->record(action: 'procurement.invoice.approved_and_posted', subject: $locked, after: ['journal_id' => $journal->id, 'amount_minor' => (int) $locked->total_minor], module: 'procurement');

            return $locked->refresh();
        });
    }

    public function prepareCreditNote(SupplierInvoice $invoice, int $amountMinor, string $reason, string $key, User $preparer): object
    {
        if ($invoice->status === 'draft' || $amountMinor <= 0 || $amountMinor > (int) $invoice->total_minor - (int) $invoice->credited_minor || trim($reason) === '') {
            throw new \DomainException('A valid approved invoice, positive credit and reason are required.');
        }
        $existing = DB::connection('tenant')->table('procurement_invoice_credit_notes')->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_invoice_credit_notes')->insert(['id' => $id, 'reference' => 'APCN-'.strtoupper((string) Str::ulid()), 'invoice_id' => $invoice->id, 'amount_minor' => $amountMinor, 'reason' => $reason, 'status' => 'pending_approval', 'idempotency_key' => $key, 'prepared_by' => $preparer->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::connection('tenant')->table('procurement_invoice_credit_notes')->where('id', $id)->firstOrFail();
    }

    public function approveCreditNote(string $creditId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($creditId, $approver) {
            $credit = DB::connection('tenant')->table('procurement_invoice_credit_notes')->where('id', $creditId)->lockForUpdate()->firstOrFail();
            if ($credit->status !== 'pending_approval' || $credit->prepared_by === $approver->getKey()) {
                throw new \DomainException('Credit note requires independent approval.');
            }
            $invoice = SupplierInvoice::query()->whereKey($credit->invoice_id)->lockForUpdate()->firstOrFail();
            if ((int) $credit->amount_minor > (int) $invoice->total_minor - (int) $invoice->credited_minor) {
                throw new \DomainException('Credit exceeds the uncredited invoice amount.');
            }
            $journal = $this->posting->post([
                'journal_type' => 'supplier_credit_note', 'entry_date' => now()->toDateString(), 'description' => "Supplier credit {$credit->reference}", 'currency_code' => $invoice->currency_code,
                'lines' => [
                    ['account_code' => config('procurement.accounting.ap_control', '2100'), 'debit_minor' => (int) $credit->amount_minor, 'party_id' => $invoice->supplier->party_id, 'memo' => $credit->reason],
                    ['account_code' => $invoice->expense_account_code, 'credit_minor' => (int) $credit->amount_minor, 'memo' => $credit->reason],
                ],
                'source_type' => 'supplier_credit_note', 'source_id' => $credit->id, 'idempotency_key' => 'procurement:credit:'.$credit->id, 'created_by' => $approver->getKey(),
            ]);
            $invoice->increment('credited_minor', (int) $credit->amount_minor);
            $due = (int) $invoice->total_minor - (int) $invoice->fresh()->credited_minor - (int) $invoice->paid_minor;
            if ($invoice->purchase_order_line_id) {
                $orderCommitment = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $invoice->purchase_order_id)->orderByDesc('created_at')->first();
                if ($orderCommitment?->commitment_id) {
                    app(SupplierBudgetService::class)->reverseActual(BudgetCommitment::findOrFail($orderCommitment->commitment_id), (int) $credit->amount_minor);
                }
            }
            DB::connection('tenant')->table('procurement_invoice_credit_notes')->where('id', $creditId)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'journal_entry_id' => $journal->id, 'updated_at' => now()]);
            $invoice->forceFill(['status' => $due === 0 ? 'paid' : ($invoice->paid_minor > 0 ? 'part_paid' : 'approved')])->save();
            $this->audit->record(action: 'procurement.credit_note.approved_and_posted', subject: $invoice, after: ['credit_note_id' => $creditId, 'journal_id' => $journal->id, 'amount_minor' => (int) $credit->amount_minor], module: 'procurement');

            return DB::connection('tenant')->table('procurement_invoice_credit_notes')->where('id', $creditId)->firstOrFail();
        });
    }

    public function preparePayment(SupplierInvoice $invoice, int $amountMinor, string $key, User $preparer): SupplierPaymentProposal
    {
        if ($invoice->status !== 'approved' && $invoice->status !== 'part_paid') {
            throw new \DomainException('Only approved supplier invoices can be proposed for payment.');
        }
        if ($amountMinor <= 0 || $amountMinor > (int) $invoice->total_minor - (int) $invoice->credited_minor - (int) $invoice->paid_minor) {
            throw new \DomainException('Proposed payment exceeds the outstanding supplier payable.');
        }
        $existing = SupplierPaymentProposal::where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->invoice_id !== $invoice->id || (int) $existing->amount_minor !== $amountMinor) {
                throw new \DomainException('Payment proposal key was reused with different inputs.');
            }

            return $existing;
        }
        $proposal = SupplierPaymentProposal::create(['reference' => 'APPAY-'.strtoupper((string) Str::ulid()), 'invoice_id' => $invoice->id, 'amount_minor' => $amountMinor, 'status' => 'pending_approval', 'idempotency_key' => $key, 'prepared_by' => $preparer->getKey()]);
        $this->audit->record(action: 'procurement.payment_proposal.prepared', subject: $proposal, after: ['invoice_id' => $invoice->id, 'amount_minor' => $amountMinor], module: 'procurement');

        return $proposal;
    }

    public function approvePayment(SupplierPaymentProposal $proposal, User $approver): SupplierPaymentProposal
    {
        return DB::connection('tenant')->transaction(function () use ($proposal, $approver) {
            $locked = SupplierPaymentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval' || $locked->prepared_by === $approver->getKey()) {
                throw new \DomainException('Payment proposal approval requires an independent approver.');
            }
            // Invoice is the serialization point shared by approvals, credits,
            // invoice posting and settlements. Reserve approved proposals here
            // so two individually valid payments cannot over-approve one AP row.
            $invoice = SupplierInvoice::query()->whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $outstanding = (int) $invoice->total_minor - (int) $invoice->credited_minor - (int) $invoice->paid_minor;
            $approved = (int) SupplierPaymentProposal::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', 'approved')
                ->where('id', '!=', $locked->id)
                ->sum('amount_minor');
            if ((int) $locked->amount_minor <= 0 || $approved + (int) $locked->amount_minor > $outstanding) {
                throw new \DomainException('Approved payment proposals cannot exceed the unpaid invoice balance.');
            }
            $locked->forceFill(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
            $this->audit->record(action: 'procurement.payment_proposal.approved', subject: $locked, after: ['amount_minor' => (int) $locked->amount_minor], module: 'procurement');

            return $locked->refresh();
        });
    }

    /** Record the explicitly synthetic settlement; approval alone never settles money. */
    public function settleSandbox(SupplierPaymentProposal $proposal, string $settlementReference, User $settler): object
    {
        return DB::connection('tenant')->transaction(function () use ($proposal, $settlementReference, $settler) {
            $locked = SupplierPaymentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            $already = DB::connection('tenant')->table('procurement_supplier_payments')->where('proposal_id', $locked->id)->first();
            if ($already) {
                if ($already->settlement_reference !== $settlementReference || (int) $already->amount_minor !== (int) $locked->amount_minor) {
                    throw new \DomainException('This proposal was already settled under a different reference or amount.');
                }

                return $already;
            }
            if ($locked->status !== 'approved') {
                throw new \DomainException('Only approved payment proposals can be settled.');
            }
            $invoice = SupplierInvoice::query()->whereKey($locked->invoice_id)->lockForUpdate()->firstOrFail();
            $outstanding = (int) $invoice->total_minor - (int) $invoice->credited_minor - (int) $invoice->paid_minor;
            if ((int) $locked->amount_minor <= 0 || (int) $locked->amount_minor > $outstanding) {
                throw new \DomainException('Settlement exceeds current supplier invoice balance.');
            }
            $paymentId = (string) Str::ulid();
            $journal = $this->posting->post([
                'journal_type' => 'supplier_payment', 'entry_date' => now()->toDateString(), 'description' => "Synthetic sandbox supplier settlement {$settlementReference}",
                'currency_code' => $invoice->currency_code,
                'lines' => [
                    ['account_code' => config('procurement.accounting.ap_control', '2100'), 'debit_minor' => (int) $locked->amount_minor, 'party_id' => $invoice->supplier->party_id, 'memo' => "Supplier settlement {$settlementReference}"],
                    ['account_code' => config('procurement.accounting.bank_account', '1120'), 'credit_minor' => (int) $locked->amount_minor, 'memo' => 'Sandbox only; no external money movement'],
                ],
                'source_type' => 'supplier_payment', 'source_id' => $paymentId,
                'idempotency_key' => 'procurement:settlement:'.$locked->id, 'created_by' => $settler->getKey(),
            ]);
            DB::connection('tenant')->table('procurement_supplier_payments')->insert(['id' => $paymentId, 'reference' => 'SPS-'.strtoupper((string) Str::ulid()), 'proposal_id' => $locked->id, 'invoice_id' => $invoice->id, 'amount_minor' => $locked->amount_minor, 'currency_code' => $invoice->currency_code, 'status' => 'settled', 'settlement_adapter' => 'sandbox', 'settlement_reference' => $settlementReference, 'journal_entry_id' => $journal->id, 'settled_by' => $settler->getKey(), 'settled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $invoice->increment('paid_minor', (int) $locked->amount_minor);
            $invoice->forceFill(['status' => (int) $invoice->fresh()->paid_minor + (int) $invoice->fresh()->credited_minor >= (int) $invoice->total_minor ? 'paid' : 'part_paid'])->save();
            $locked->forceFill(['status' => 'settled'])->save();
            $this->audit->record(action: 'procurement.supplier_payment.sandbox_settled', subject: $locked, after: ['payment_id' => $paymentId, 'journal_id' => $journal->id, 'amount_minor' => (int) $locked->amount_minor, 'adapter' => 'sandbox'], module: 'procurement');

            return DB::connection('tenant')->table('procurement_supplier_payments')->where('id', $paymentId)->firstOrFail();
        });
    }

    /** Independently recomputed supplier subledger versus posted AP-control balance. */
    public function reconciliation(string $asOf): array
    {
        $subledger = (int) DB::connection('tenant')->table('procurement_supplier_invoices')->whereIn('status', ['approved', 'part_paid', 'paid'])->whereDate('invoice_date', '<=', $asOf)->sum(DB::raw('total_minor - credited_minor - paid_minor'));
        $control = (int) DB::connection('tenant')->table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $asOf)->where('a.code', config('procurement.accounting.ap_control', '2100'))->selectRaw('COALESCE(SUM(l.credit_minor) - SUM(l.debit_minor), 0) balance')->value('balance');

        return ['as_of' => $asOf, 'supplier_payables_minor' => $subledger, 'ap_control_credit_balance_minor' => $control, 'difference_minor' => $control - $subledger, 'reconciled' => $control === $subledger];
    }

    public function reports(string $asOf): array
    {
        $invoices = DB::connection('tenant')->table('procurement_supplier_invoices as i')->join('procurement_suppliers as s', 's.id', '=', 'i.supplier_id')->whereIn('i.status', ['approved', 'part_paid'])->select('s.supplier_number', 'i.supplier_invoice_number', 'i.invoice_date', 'i.due_date', 'i.total_minor', 'i.credited_minor', 'i.paid_minor')->orderBy('i.due_date')->get();
        $aged = $invoices->map(function ($invoice) use ($asOf) {
            $remaining = (int) $invoice->total_minor - (int) $invoice->credited_minor - (int) $invoice->paid_minor;
            $days = now()->parse($asOf)->startOfDay()->diffInDays(now()->parse($invoice->due_date)->startOfDay(), false) * -1;

            return ['supplier_number' => $invoice->supplier_number, 'invoice_number' => $invoice->supplier_invoice_number, 'balance_minor' => $remaining, 'days_overdue' => max(0, $days)];
        })->all();

        return [
            'supplier_ledger' => $aged,
            'creditor_ageing' => ['current_minor' => array_sum(array_column(array_filter($aged, fn ($row) => $row['days_overdue'] === 0), 'balance_minor')), '1_30_minor' => array_sum(array_column(array_filter($aged, fn ($row) => $row['days_overdue'] >= 1 && $row['days_overdue'] <= 30), 'balance_minor')), 'over_30_minor' => array_sum(array_column(array_filter($aged, fn ($row) => $row['days_overdue'] > 30), 'balance_minor'))],
            'matching_exceptions' => DB::connection('tenant')->table('procurement_supplier_invoices')->whereIn('status', ['exception_pending', 'exception_approved'])->count(),
            'payment_status' => DB::connection('tenant')->table('procurement_payment_proposals')->select('status', DB::raw('COUNT(*) count'), DB::raw('SUM(amount_minor) amount_minor'))->groupBy('status')->get()->all(),
            'outstanding_orders' => DB::connection('tenant')->table('procurement_purchase_orders')->where('status', 'approved')->get()->all(),
            'budget_lines' => DB::connection('tenant')->table('procurement_budget_lines as l')->join('procurement_budgets as b', 'b.id', '=', 'l.budget_id')->where('b.status', 'approved')->select('b.code', 'l.account_code', 'l.dimensions', 'l.approved_minor', 'l.transferred_in_minor', 'l.transferred_out_minor', 'l.committed_minor', 'l.actual_minor')->get()->map(fn ($line) => [...(array) $line, 'available_minor' => (int) $line->approved_minor + (int) $line->transferred_in_minor - (int) $line->transferred_out_minor - (int) $line->committed_minor - (int) $line->actual_minor])->all(),
            'ap_reconciliation' => $this->reconciliation($asOf),
        ];
    }
}
