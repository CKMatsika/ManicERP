<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Procurement\Models\BudgetCommitment;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Models\ProcurementAward;
use App\Modules\Procurement\Models\ProcurementReceipt;
use App\Modules\Procurement\Models\Requisition;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Requisition, quotation, award, order and partial receiving business rules. */
class SourcingService
{
    public function __construct(private readonly SupplierBudgetService $budgets, private readonly PostingService $posting, private readonly AuditLogger $audit) {}

    public function prepareRequisition(BudgetLine $line, string $title, string $justification, int $estimateMinor, User $requester): object
    {
        if ($line->budget->status !== 'approved' || $estimateMinor <= 0 || trim($justification) === '') {
            throw new \DomainException('An approved budget, positive estimate and justification are required.');
        }

        return $this->insertRequisition($line, $title, $justification, $estimateMinor, $requester);
    }

    private function insertRequisition(BudgetLine $line, string $title, string $justification, int $estimateMinor, User $requester): object
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_requisitions')->insert([
            'id' => $id, 'reference' => 'REQ-'.strtoupper((string) Str::ulid()), 'title' => $title, 'justification' => $justification,
            'budget_line_id' => $line->id, 'estimated_minor' => $estimateMinor, 'currency_code' => 'USD', 'status' => 'draft',
            'prepared_by' => $requester->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->first();
    }

    public function submitRequisition(string $id, User $requester, ?User $overrideApprover = null, ?string $overrideReason = null): object
    {
        return DB::connection('tenant')->transaction(function () use ($id, $requester, $overrideApprover, $overrideReason) {
            $req = DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($req->prepared_by !== $requester->getKey() || $req->status !== 'draft') {
                throw new \DomainException('Only the requester may submit a draft requisition.');
            }
            $line = BudgetLine::findOrFail($req->budget_line_id);
            $commitment = $this->budgets->reserve($line, 'requisition', $req->id, (int) $req->estimated_minor, 'procurement:req:'.$req->id, $requester, $overrideApprover, $overrideReason);
            DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->update(['status' => 'submitted', 'commitment_id' => $commitment->id, 'updated_at' => now()]);
            $this->audit->record(action: 'procurement.requisition.submitted', subject: Requisition::findOrFail($id), after: ['reference' => $req->reference, 'commitment_id' => $commitment->id], module: 'procurement');

            return DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->first();
        });
    }

    public function approveRequisition(string $id, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($id, $approver) {
            $req = DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($req->status !== 'submitted' || $req->prepared_by === $approver->getKey()) {
                throw new \DomainException('Requisition approval requires a submitted request and independent approver.');
            }
            DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit->record(action: 'procurement.requisition.approved', subject: Requisition::findOrFail($id), after: ['reference' => $req->reference], module: 'procurement');

            return DB::connection('tenant')->table('procurement_requisitions')->where('id', $id)->first();
        });
    }

    public function submitQuotation(string $requisitionId, Supplier $supplier, string $reference, int $amountMinor, string $evidence, User $user): object
    {
        $req = DB::connection('tenant')->table('procurement_requisitions')->where('id', $requisitionId)->firstOrFail();
        if ($req->status !== 'approved' || $supplier->status !== 'approved' || $amountMinor <= 0 || trim($evidence) === '') {
            throw new \DomainException('Approved requisition and supplier, positive quotation and evaluation evidence are required.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_quotations')->insertOrIgnore([
            'id' => $id, 'requisition_id' => $req->id, 'supplier_id' => $supplier->id, 'quote_reference' => $reference,
            'currency_code' => 'USD', 'quoted_minor' => $amountMinor, 'evaluation_evidence' => $evidence, 'status' => 'received',
            'submitted_by' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::connection('tenant')->table('procurement_quotations')->where(['requisition_id' => $req->id, 'supplier_id' => $supplier->id, 'quote_reference' => $reference])->firstOrFail();
    }

    public function prepareAward(string $quotationId, string $evaluation, User $preparer, ?User $overrideApprover = null, ?string $overrideReason = null): object
    {
        if (trim($evaluation) === '') {
            throw new \DomainException('An award evaluation summary is required.');
        }
        $quote = DB::connection('tenant')->table('procurement_quotations')->where('id', $quotationId)->firstOrFail();
        $req = DB::connection('tenant')->table('procurement_requisitions')->where('id', $quote->requisition_id)->firstOrFail();
        if ($req->status !== 'approved' || (int) $quote->quoted_minor > (int) $req->estimated_minor) {
            throw new \DomainException('Award quote must be within the approved requisition estimate. Amend the budget and requisition first.');
        }

        $threshold = (int) config('procurement.approval_thresholds_minor.quotation_count', 1);
        $receivedQuotes = (int) DB::connection('tenant')->table('procurement_quotations')
            ->where('requisition_id', $req->id)
            ->where('status', 'received')
            ->count();
        if ($threshold > 0 && $receivedQuotes < $threshold) {
            $hasOverride = $overrideApprover !== null
                && trim((string) $overrideReason) !== ''
                && $overrideApprover->getKey() !== $preparer->getKey();
            if (! $hasOverride) {
                throw new \DomainException("At least {$threshold} received quotations are required before award; {$receivedQuotes} received.");
            }
        }

        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_awards')->insertOrIgnore(['id' => $id, 'requisition_id' => $req->id, 'quotation_id' => $quote->id, 'status' => 'pending_approval', 'evaluation_summary' => $evaluation, 'prepared_by' => $preparer->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        if ($threshold > 0 && $receivedQuotes < $threshold && $overrideApprover !== null) {
            $this->audit->record(action: 'procurement.award.quote_threshold_overridden', subject: Requisition::findOrFail($req->id), after: ['requisition_id' => $req->id, 'threshold' => $threshold, 'received' => $receivedQuotes, 'reason' => trim((string) $overrideReason), 'override_approver' => $overrideApprover->getKey()], module: 'procurement');
        }

        return DB::connection('tenant')->table('procurement_awards')->where('requisition_id', $req->id)->firstOrFail();
    }

    public function approveAward(string $awardId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($awardId, $approver) {
            $award = DB::connection('tenant')->table('procurement_awards')->where('id', $awardId)->lockForUpdate()->firstOrFail();
            if ($award->status !== 'pending_approval' || $award->prepared_by === $approver->getKey()) {
                throw new \DomainException('Award approval requires independent approval.');
            }
            DB::connection('tenant')->table('procurement_awards')->where('id', $awardId)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $award = DB::connection('tenant')->table('procurement_awards')->where('id', $awardId)->firstOrFail();
            $quote = DB::connection('tenant')->table('procurement_quotations')->where('id', $award->quotation_id)->firstOrFail();
            $req = DB::connection('tenant')->table('procurement_requisitions')->where('id', $award->requisition_id)->firstOrFail();
            $line = BudgetLine::findOrFail($req->budget_line_id);
            $poId = (string) Str::ulid();
            $poRef = 'PO-'.strtoupper((string) Str::ulid());
            DB::connection('tenant')->table('procurement_purchase_orders')->insert(['id' => $poId, 'reference' => $poRef, 'requisition_id' => $req->id, 'award_id' => $awardId, 'supplier_id' => $quote->supplier_id, 'currency_code' => $quote->currency_code, 'total_minor' => $quote->quoted_minor, 'status' => 'approved', 'issued_by' => $approver->getKey(), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $poLineId = (string) Str::ulid();
            DB::connection('tenant')->table('procurement_purchase_order_lines')->insert(['id' => $poLineId, 'purchase_order_id' => $poId, 'description' => $req->title, 'account_code' => $line->account_code, 'dimensions' => json_encode($line->dimensions ?: []), 'ordered_quantity' => 1, 'received_quantity' => 0, 'invoiced_quantity' => 0, 'cancelled_quantity' => 0, 'unit_price_minor' => $quote->quoted_minor, 'line_total_minor' => $quote->quoted_minor, 'created_at' => now(), 'updated_at' => now()]);
            if ($req->commitment_id) {
                $this->budgets->reduceReservationTo(BudgetCommitment::findOrFail($req->commitment_id), (int) $quote->quoted_minor);
                DB::connection('tenant')->table('procurement_order_commitments')->insert(['id' => (string) Str::ulid(), 'purchase_order_id' => $poId, 'commitment_id' => $req->commitment_id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->writeOrderVersion($poId, 1, (int) $quote->quoted_minor, null, now()->toDateString(), $approver->getKey());
            $this->audit->record(action: 'procurement.award.approved', subject: ProcurementAward::findOrFail($awardId), after: ['purchase_order_id' => $poId, 'purchase_order_reference' => $poRef], module: 'procurement');

            return DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $poId)->firstOrFail();
        });
    }

    /** Prepare a reasoned amendment against the current approved order version. */
    public function prepareOrderAmendment(string $purchaseOrderId, array $lineChanges, string $reason, string $effectiveDate, User $preparer): object
    {
        if ($lineChanges === [] || trim($reason) === '' || $effectiveDate < now()->toDateString()) {
            throw new \DomainException('An amendment needs changed lines, a reason and a current or future effective date.');
        }

        return DB::connection('tenant')->transaction(function () use ($purchaseOrderId, $lineChanges, $reason, $effectiveDate, $preparer) {
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $po->issued_by === $preparer->getKey()) {
                throw new \DomainException('Only an active approved order may be amended by a preparer other than its issuer.');
            }
            $lines = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $afterTotal = 0;
            $proposed = [];
            foreach ($lines as $line) {
                $change = $lineChanges[$line->id] ?? [];
                $quantityMilli = (int) round((float) ($line->ordered_quantity) * 1000);
                $quantityMilli = (int) ($change['quantity_milli'] ?? $quantityMilli);
                $unitPrice = (int) ($change['unit_price_minor'] ?? $line->unit_price_minor);
                $minimumMilli = (int) round((max((float) $line->received_quantity, (float) $line->invoiced_quantity) + (float) $line->cancelled_quantity) * 1000);
                if ($quantityMilli <= 0 || $quantityMilli < $minimumMilli || $unitPrice <= 0) {
                    throw new \DomainException('Amendment quantities cannot fall below received, invoiced or cancelled amounts; price and quantity must remain positive.');
                }
                $lineTotal = (int) round(($quantityMilli / 1000) * $unitPrice);
                $afterTotal += $lineTotal;
                $proposed[$line->id] = ['line' => $line, 'quantity_milli' => $quantityMilli, 'unit_price_minor' => $unitPrice, 'line_total_minor' => $lineTotal];
            }
            foreach ($lineChanges as $lineId => $_change) {
                if (! isset($proposed[$lineId])) {
                    throw new \DomainException('Amendment includes a line outside this purchase order.');
                }
            }
            $amendmentId = (string) Str::ulid();
            DB::connection('tenant')->table('procurement_order_amendments')->insert([
                'id' => $amendmentId, 'reference' => 'POA-'.strtoupper((string) Str::ulid()), 'purchase_order_id' => $po->id,
                'from_version' => (int) $po->current_version, 'to_version' => (int) $po->current_version + 1,
                'status' => 'pending_approval', 'reason' => trim($reason), 'effective_date' => $effectiveDate,
                'before_total_minor' => (int) $po->total_minor, 'after_total_minor' => $afterTotal,
                'prepared_by' => $preparer->getKey(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lineChanges as $lineId => $_change) {
                $item = $proposed[$lineId];
                $line = $item['line'];
                DB::connection('tenant')->table('procurement_order_amendment_lines')->insert([
                    'id' => (string) Str::ulid(), 'amendment_id' => $amendmentId, 'purchase_order_line_id' => $lineId,
                    'before_quantity' => $line->ordered_quantity, 'after_quantity' => $item['quantity_milli'] / 1000,
                    'before_unit_price_minor' => $line->unit_price_minor, 'after_unit_price_minor' => $item['unit_price_minor'],
                    'before_total_minor' => $line->line_total_minor, 'after_total_minor' => $item['line_total_minor'],
                ]);
            }
            $this->audit->record(action: 'procurement.purchase_order.amendment_prepared', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'amendment_id' => $amendmentId, 'from_version' => (int) $po->current_version, 'to_version' => (int) $po->current_version + 1, 'before_total_minor' => (int) $po->total_minor, 'after_total_minor' => $afterTotal, 'reason' => trim($reason)], module: 'procurement');

            return DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->firstOrFail();
        });
    }

    public function approveOrderAmendment(string $amendmentId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($amendmentId, $approver) {
            $amendment = DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->firstOrFail();
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $amendment->purchase_order_id)->lockForUpdate()->firstOrFail();
            $amendment = DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $amendment->status !== 'pending_approval' || $amendment->prepared_by === $approver->getKey() || (int) $po->current_version !== (int) $amendment->from_version) {
                throw new \DomainException('Amendment approval requires an independent approver and the current active order version.');
            }
            $changes = DB::connection('tenant')->table('procurement_order_amendment_lines')->where('amendment_id', $amendmentId)->orderBy('purchase_order_line_id')->get();
            $lineIds = $changes->pluck('purchase_order_line_id')->all();
            $lines = DB::connection('tenant')->table('procurement_purchase_order_lines')->whereIn('id', $lineIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($changes as $change) {
                $line = $lines->get($change->purchase_order_line_id);
                if (! $line || (float) $line->ordered_quantity !== (float) $change->before_quantity || (int) $line->unit_price_minor !== (int) $change->before_unit_price_minor) {
                    throw new \DomainException('Amendment no longer matches the current line values. Prepare a new version.');
                }
                if ((float) $change->after_quantity < max((float) $line->received_quantity, (float) $line->invoiced_quantity) + (float) $line->cancelled_quantity) {
                    throw new \DomainException('Amendment cannot reduce the order below received, invoiced or cancelled quantities.');
                }
            }
            foreach ($changes as $change) {
                DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $change->purchase_order_line_id)->update([
                    'ordered_quantity' => $change->after_quantity, 'unit_price_minor' => $change->after_unit_price_minor,
                    'line_total_minor' => $change->after_total_minor, 'updated_at' => now(),
                ]);
            }
            $linesNow = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->orderBy('id')->get();
            $canceledValue = (int) $linesNow->sum(fn ($line) => (int) round((float) $line->cancelled_quantity * (int) $line->unit_price_minor));
            $reserveTarget = (int) $amendment->after_total_minor - $canceledValue;
            $link = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $po->id)->where('status', 'active')->first();
            if ($link) {
                $this->budgets->adjustReservationTo(BudgetCommitment::findOrFail($link->commitment_id), $reserveTarget);
            } else {
                $requisition = DB::connection('tenant')->table('procurement_requisitions')->where('id', $po->requisition_id)->firstOrFail();
                $budgetLine = BudgetLine::findOrFail($requisition->budget_line_id);
                $actualToDate = (int) DB::connection('tenant')->table('procurement_supplier_invoices')->where('purchase_order_id', $po->id)->whereIn('status', ['approved', 'part_paid', 'paid'])->sum(DB::raw('total_minor - credited_minor'));
                $newReserve = $reserveTarget - $actualToDate;
                if ($newReserve > 0) {
                    $commitment = $this->budgets->reserve($budgetLine, 'purchase_order', $po->id, $newReserve, 'procurement:po-amendment:'.$amendmentId, $approver);
                    DB::connection('tenant')->table('procurement_order_commitments')->insert(['id' => (string) Str::ulid(), 'purchase_order_id' => $po->id, 'commitment_id' => $commitment->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $this->writeOrderVersion($po->id, (int) $amendment->to_version, (int) $amendment->after_total_minor, $amendment->id, $amendment->effective_date, $approver->getKey(), $amendment->reason);
            DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->update(['total_minor' => $amendment->after_total_minor, 'current_version' => $amendment->to_version, 'updated_at' => now()]);
            DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $this->recordOrderEvent($po->id, 'amended', 'approved', $amendment->reason, ['amendment_id' => $amendmentId, 'from_version' => (int) $amendment->from_version, 'to_version' => (int) $amendment->to_version, 'before_total_minor' => (int) $amendment->before_total_minor, 'after_total_minor' => (int) $amendment->after_total_minor], $amendment->prepared_by, $approver->getKey());
            $this->audit->record(action: 'procurement.purchase_order.amendment_approved', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'amendment_id' => $amendmentId, 'version' => (int) $amendment->to_version, 'total_minor' => (int) $amendment->after_total_minor], module: 'procurement');

            return DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->firstOrFail();
        });
    }

    public function rejectOrderAmendment(string $amendmentId, string $reason, User $approver): object
    {
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required to reject an order amendment.');
        }

        return DB::connection('tenant')->transaction(function () use ($amendmentId, $reason, $approver) {
            $amendment = DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->lockForUpdate()->firstOrFail();
            if ($amendment->status !== 'pending_approval' || $amendment->prepared_by === $approver->getKey()) {
                throw new \DomainException('Only an independent approver may reject a pending amendment.');
            }
            DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->update(['status' => 'rejected', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $this->recordOrderEvent($amendment->purchase_order_id, 'amendment_rejected', 'rejected', trim($reason), ['amendment_id' => $amendmentId], $amendment->prepared_by, $approver->getKey());
            $this->audit->record(action: 'procurement.purchase_order.amendment_rejected', after: ['purchase_order_id' => $amendment->purchase_order_id, 'amendment_id' => $amendmentId, 'reason' => trim($reason)], module: 'procurement');

            return DB::connection('tenant')->table('procurement_order_amendments')->where('id', $amendmentId)->firstOrFail();
        });
    }

    /** @param array<string,int> $acceptedMilli keyed by PO-line ID */
    public function acceptReceipt(string $purchaseOrderId, array $acceptedMilli, string $idempotencyKey, string $note, User $receiver): object
    {
        return DB::connection('tenant')->transaction(function () use ($purchaseOrderId, $acceptedMilli, $idempotencyKey, $note, $receiver) {
            $existing = DB::connection('tenant')->table('procurement_receipts')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->purchase_order_id !== $purchaseOrderId) {
                    throw new \DomainException('Receipt idempotency key was reused for a different purchase order.');
                }
                $stored = DB::connection('tenant')->table('procurement_receipt_lines')->where('receipt_id', $existing->id)->get()->mapWithKeys(fn ($row) => [$row->purchase_order_line_id => (int) round((float) $row->accepted_quantity * 1000)])->all();
                ksort($stored);
                $requested = $acceptedMilli;
                ksort($requested);
                if ($stored !== $requested) {
                    throw new \DomainException('Receipt idempotency key was reused with different quantities.');
                }

                return $existing;
            }
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $acceptedMilli === []) {
                throw new \DomainException('An active approved order and accepted quantities are required.');
            }
            foreach ($acceptedMilli as $lineId => $quantityMilli) {
                $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->where('purchase_order_id', $po->id)->lockForUpdate()->firstOrFail();
                $orderedMilli = (int) round((float) $line->ordered_quantity * 1000);
                $receivedMilli = (int) round((float) $line->received_quantity * 1000);
                if ($quantityMilli <= 0 || $quantityMilli > $orderedMilli - $receivedMilli) {
                    throw new \DomainException('Receipt quantity exceeds the outstanding purchase order quantity.');
                }
            }
            $receiptId = (string) Str::ulid();
            $reference = 'GRN-'.strtoupper((string) Str::ulid());
            DB::connection('tenant')->table('procurement_receipts')->insert(['id' => $receiptId, 'reference' => $reference, 'purchase_order_id' => $po->id, 'received_on' => now()->toDateString(), 'status' => 'accepted', 'acceptance_note' => $note, 'idempotency_key' => $idempotencyKey, 'accepted_by' => $receiver->getKey(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($acceptedMilli as $lineId => $quantityMilli) {
                $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->lockForUpdate()->firstOrFail();
                DB::connection('tenant')->table('procurement_receipt_lines')->insert(['id' => (string) Str::ulid(), 'receipt_id' => $receiptId, 'purchase_order_line_id' => $lineId, 'accepted_quantity' => $quantityMilli / 1000, 'finding' => $note, 'created_at' => now(), 'updated_at' => now()]);
                DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->update(['received_quantity' => (float) $line->received_quantity + $quantityMilli / 1000, 'updated_at' => now()]);
            }
            $this->audit->record(action: 'procurement.receipt.accepted', subject: ProcurementReceipt::findOrFail($receiptId), after: ['purchase_order_id' => $po->id, 'quantities_milli' => $acceptedMilli], module: 'procurement');

            return DB::connection('tenant')->table('procurement_receipts')->where('id', $receiptId)->firstOrFail();
        });
    }

    /** Cancel a portion of quantity that has not been received or invoiced. */
    public function cancelOutstandingQuantity(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($purchaseOrderId, $lineId, $quantityMilli, $reason, $approver) {
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->firstOrFail();
            $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->where('purchase_order_id', $po->id)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $po->issued_by === $approver->getKey() || $quantityMilli <= 0 || trim($reason) === '') {
                throw new \DomainException('Partial cancellation requires an active order, reasoned independent approver and positive quantity.');
            }
            // Invoiced quantity is a subset of received quantity and must not
            // be subtracted twice when computing what the supplier still owes.
            $outstanding = (int) round(((float) $line->ordered_quantity - (float) $line->received_quantity - (float) $line->cancelled_quantity) * 1000);
            if ($quantityMilli > $outstanding) {
                throw new \DomainException('Cancellation exceeds the genuinely outstanding unreceived and uninvoiced order quantity.');
            }
            $cancelValue = (int) round(($quantityMilli / 1000) * (int) $line->unit_price_minor);
            DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $line->id)->update(['cancelled_quantity' => (float) $line->cancelled_quantity + $quantityMilli / 1000, 'updated_at' => now()]);
            $link = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $po->id)->where('status', 'active')->first();
            if ($link) {
                $commitment = BudgetCommitment::findOrFail($link->commitment_id);
                $target = (int) $commitment->reserved_minor - $cancelValue;
                if ($target > (int) $commitment->converted_minor) {
                    $this->budgets->adjustReservationTo($commitment, $target);
                } elseif ($target === (int) $commitment->converted_minor && $target > 0) {
                    $this->budgets->adjustReservationTo($commitment, $target);
                    DB::connection('tenant')->table('procurement_order_commitments')->where('id', $link->id)->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
                } elseif ($target === 0 && (int) $commitment->converted_minor === 0) {
                    $this->budgets->release($commitment);
                    DB::connection('tenant')->table('procurement_order_commitments')->where('id', $link->id)->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
                } else {
                    throw new \DomainException('Cancellation would release budget already consumed by posted invoice actuals.');
                }
            }
            $this->recordOrderEvent($po->id, 'quantity_cancelled', 'approved', trim($reason), ['line_id' => $line->id, 'quantity_milli' => $quantityMilli, 'released_minor' => $cancelValue], $approver->getKey(), $approver->getKey());
            $this->audit->record(action: 'procurement.purchase_order.quantity_cancelled', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'line_id' => $line->id, 'quantity_milli' => $quantityMilli, 'released_minor' => $cancelValue, 'reason' => trim($reason)], module: 'procurement');

            return DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $line->id)->firstOrFail();
        });
    }

    /** Close a fulfilled or fully accounted-for order; outstanding AP need not be paid. */
    public function closePurchaseOrder(string $purchaseOrderId, string $reason, User $approver, bool $administrative = false): object
    {
        return DB::connection('tenant')->transaction(function () use ($purchaseOrderId, $reason, $approver, $administrative) {
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $po->issued_by === $approver->getKey() || trim($reason) === '') {
                throw new \DomainException('Order closure requires an active order, reason and independent approver.');
            }
            if (DB::connection('tenant')->table('procurement_order_amendments')->where('purchase_order_id', $po->id)->where('status', 'pending_approval')->exists() || DB::connection('tenant')->table('procurement_order_reopen_requests')->where('purchase_order_id', $po->id)->where('status', 'pending_approval')->exists()) {
                throw new \DomainException('Order cannot close while an amendment or reopening request is awaiting a decision.');
            }
            $lines = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get();
            $hasCancelled = false;
            $hasMovement = false;
            foreach ($lines as $line) {
                $ordered = (float) $line->ordered_quantity;
                $received = (float) $line->received_quantity;
                $invoiced = (float) $line->invoiced_quantity;
                $cancelled = (float) $line->cancelled_quantity;
                if ($received + $cancelled + 0.0001 < $ordered || $received > $invoiced + 0.0001) {
                    throw new \DomainException('Order cannot close while quantities or accepted-but-uninvoiced receipts remain unresolved.');
                }
                $hasCancelled = $hasCancelled || $cancelled > 0;
                $hasMovement = $hasMovement || $received > 0 || $invoiced > 0;
            }
            $openInvoice = DB::connection('tenant')->table('procurement_supplier_invoices')->where('purchase_order_id', $po->id)->whereIn('status', ['draft', 'matched', 'exception_pending', 'exception_approved'])->exists();
            if ($openInvoice) {
                throw new \DomainException('Order cannot close with a draft invoice, unresolved match or matching exception.');
            }
            if ($administrative && $hasMovement) {
                throw new \DomainException('Administrative closure is limited to orders with no receipt or invoice movements.');
            }
            $links = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $po->id)->where('status', 'active')->get();
            foreach ($links as $link) {
                $this->budgets->release(BudgetCommitment::findOrFail($link->commitment_id));
                DB::connection('tenant')->table('procurement_order_commitments')->where('id', $link->id)->update(['status' => 'released', 'released_at' => now(), 'updated_at' => now()]);
            }
            $status = $administrative ? 'administratively_closed' : ($hasCancelled ? 'partially_cancelled' : 'fulfilled');
            DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->update(['status' => $status, 'closed_at' => now(), 'closed_by' => $approver->getKey(), 'closure_reason' => trim($reason), 'updated_at' => now()]);
            $this->recordOrderEvent($po->id, 'closed', $status, trim($reason), ['has_movement' => $hasMovement], $approver->getKey(), $approver->getKey());
            $this->audit->record(action: 'procurement.purchase_order.closed', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'status' => $status, 'reason' => trim($reason)], module: 'procurement');

            return DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->firstOrFail();
        });
    }

    public function cancelPurchaseOrder(string $purchaseOrderId, string $reason, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($purchaseOrderId, $reason, $approver) {
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved' || $po->issued_by === $approver->getKey() || trim($reason) === '') {
                throw new \DomainException('Administrative order closure requires a reasoned independent approver.');
            }
            $moved = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->where(fn ($query) => $query->where('received_quantity', '>', 0)->orWhere('invoiced_quantity', '>', 0))->exists();
            if ($moved) {
                throw new \DomainException('An order with receipts or invoices must cancel outstanding quantities and use controlled closure.');
            }
            foreach (DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->orderBy('id')->lockForUpdate()->get() as $line) {
                $outstandingMilli = (int) round(((float) $line->ordered_quantity - (float) $line->cancelled_quantity) * 1000);
                if ($outstandingMilli > 0) {
                    $this->cancelOutstandingQuantity($po->id, $line->id, $outstandingMilli, $reason, $approver);
                }
            }

            return $this->closePurchaseOrder($po->id, $reason, $approver, administrative: true);
        });
    }

    public function requestReopen(string $purchaseOrderId, string $reason, User $requester): object
    {
        $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->firstOrFail();
        if (! in_array($po->status, ['fulfilled', 'partially_cancelled', 'administratively_closed'], true) || trim($reason) === '') {
            throw new \DomainException('Only a closed order can be reopened with a reason.');
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_order_reopen_requests')->insert(['id' => $id, 'purchase_order_id' => $po->id, 'reason' => trim($reason), 'status' => 'pending_approval', 'prepared_by' => $requester->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::connection('tenant')->table('procurement_order_reopen_requests')->where('id', $id)->firstOrFail();
    }

    public function approveReopen(string $requestId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($requestId, $approver) {
            $request = DB::connection('tenant')->table('procurement_order_reopen_requests')->where('id', $requestId)->firstOrFail();
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $request->purchase_order_id)->lockForUpdate()->firstOrFail();
            $request = DB::connection('tenant')->table('procurement_order_reopen_requests')->where('id', $requestId)->lockForUpdate()->firstOrFail();
            if (! in_array($po->status, ['fulfilled', 'partially_cancelled', 'administratively_closed'], true) || $request->status !== 'pending_approval' || $request->prepared_by === $approver->getKey()) {
                throw new \DomainException('Order reopening requires an independently approved pending request.');
            }
            DB::connection('tenant')->table('procurement_order_reopen_requests')->where('id', $requestId)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->update(['status' => 'approved', 'closed_at' => null, 'closed_by' => null, 'closure_reason' => null, 'updated_at' => now()]);
            $this->recordOrderEvent($po->id, 'reopened', 'approved', $request->reason, ['request_id' => $requestId, 'prior_status' => $po->status], $request->prepared_by, $approver->getKey());
            $this->audit->record(action: 'procurement.purchase_order.reopened', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'request_id' => $requestId, 'reason' => $request->reason], module: 'procurement');

            return DB::connection('tenant')->table('procurement_order_reopen_requests')->where('id', $requestId)->firstOrFail();
        });
    }

    /** Prepare a supplier return/correction against a received (and possibly invoiced) PO line. */
    public function prepareSupplierReturn(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function prepareReturn(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        if ($quantityMilli <= 0 || trim($reason) === '') {
            throw new \DomainException('A positive return quantity and reason are required.');
        }
        if ($idempotencyKey !== null) {
            $existing = DB::connection('tenant')->table('procurement_supplier_returns')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->purchase_order_id !== 0 && $existing->purchase_order_id !== $purchaseOrderId) {
                    throw new \DomainException('Return idempotency key was reused for a different purchase order.');
                }

                return $existing;
            }
        }
        $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $purchaseOrderId)->firstOrFail();
        $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $lineId)->where('purchase_order_id', $po->id)->firstOrFail();
        $availableMilli = (int) round((float) $line->received_quantity * 1000);
        if ($quantityMilli > $availableMilli) {
            throw new \DomainException('Return quantity exceeds the received quantity.');
        }
        $id = (string) Str::ulid();
        $reference = 'SR-'.strtoupper((string) Str::ulid());
        DB::connection('tenant')->table('procurement_supplier_returns')->insert([
            'id' => $id, 'reference' => $reference, 'purchase_order_id' => $po->id, 'purchase_order_line_id' => $line->id,
            'quantity' => $quantityMilli / 1000, 'credit_amount_minor' => 0, 'status' => 'pending_approval', 'reason' => trim($reason),
            'idempotency_key' => $idempotencyKey, 'prepared_by' => $preparer->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record(action: 'procurement.supplier_return.prepared', subject: Requisition::findOrFail($po->requisition_id), after: ['purchase_order_id' => $po->id, 'line_id' => $line->id, 'quantity_milli' => $quantityMilli, 'reason' => trim($reason)], module: 'procurement');

        return DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $id)->firstOrFail();
    }

    public function approveSupplierReturn(string $returnId, User $approver): object
    {
        return $this->approveReturn($returnId, $approver);
    }

    public function approveReturn(string $returnId, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($returnId, $approver) {
            $ret = DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->lockForUpdate()->firstOrFail();
            if ($ret->status !== 'pending_approval' || $ret->prepared_by === $approver->getKey()) {
                throw new \DomainException('Supplier return requires an independent approver.');
            }
            $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $ret->purchase_order_id)->lockForUpdate()->firstOrFail();
            $line = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $ret->purchase_order_line_id)->where('purchase_order_id', $po->id)->lockForUpdate()->firstOrFail();
            $quantityMilli = (int) round((float) $ret->quantity * 1000);
            $receivedMilli = (int) round((float) $line->received_quantity * 1000);
            if ($quantityMilli <= 0 || $quantityMilli > $receivedMilli) {
                throw new \DomainException('Return quantity exceeds the received quantity.');
            }
            $invoicedMilli = (int) round((float) $line->invoiced_quantity * 1000);
            $invoicedPortionMilli = min($quantityMilli, $invoicedMilli);
            $creditAmount = (int) round(($invoicedPortionMilli / 1000) * (int) $line->unit_price_minor);
            $supplier = DB::connection('tenant')->table('procurement_suppliers')->where('id', $po->supplier_id)->firstOrFail();
            $partyId = $supplier->party_id;

            // Reduce received (and invoiced where applicable) quantities.
            $newReceivedMilli = $receivedMilli - $quantityMilli;
            $newInvoicedMilli = $invoicedMilli - $invoicedPortionMilli;
            DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $line->id)->update([
                'received_quantity' => $newReceivedMilli / 1000,
                'invoiced_quantity' => $newInvoicedMilli / 1000,
                'updated_at' => now(),
            ]);

            $journalId = null;
            if ($creditAmount > 0) {
                $journal = $this->posting->post([
                    'journal_type' => 'supplier_credit_note', 'entry_date' => now()->toDateString(),
                    'description' => "Supplier return credit {$ret->reference}", 'currency_code' => $po->currency_code,
                    'lines' => [
                        ['account_code' => config('procurement.accounting.ap_control', '2100'), 'debit_minor' => $creditAmount, 'party_id' => $partyId, 'memo' => "Supplier return {$ret->reference}"],
                        ['account_code' => $line->account_code, 'credit_minor' => $creditAmount, 'memo' => "Supplier return {$ret->reference}"],
                    ],
                    'source_type' => 'supplier_return', 'source_id' => $ret->id,
                    'idempotency_key' => 'procurement:return:'.$ret->id, 'created_by' => $approver->getKey(),
                ]);
                $journalId = $journal->id;

                // Reverse posted actuals for the invoiced portion if a commitment exists.
                $link = DB::connection('tenant')->table('procurement_order_commitments')->where('purchase_order_id', $po->id)->where('status', 'active')->first();
                if ($link) {
                    $commitment = BudgetCommitment::findOrFail($link->commitment_id);
                    $target = (int) $commitment->actual_minor - $creditAmount;
                    if ($target >= (int) $commitment->converted_minor) {
                        app(SupplierBudgetService::class)->reverseActual($commitment, $creditAmount);
                    }
                }
            }

            DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->update([
                'status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(),
                'credit_amount_minor' => $creditAmount, 'journal_entry_id' => $journalId, 'updated_at' => now(),
            ]);
            $this->audit->record(action: 'procurement.supplier_return.approved', subject: Requisition::findOrFail($po->requisition_id), after: ['return_id' => $ret->id, 'purchase_order_id' => $po->id, 'quantity_milli' => $quantityMilli, 'credit_amount_minor' => $creditAmount, 'journal_id' => $journalId], module: 'procurement');

            return DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->firstOrFail();
        });
    }

    public function rejectSupplierReturn(string $returnId, string $reason, User $approver): object
    {
        return $this->rejectReturn($returnId, $reason, $approver);
    }

    public function rejectReturn(string $returnId, string $reason, User $approver): object
    {
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required to reject a supplier return.');
        }
        $ret = DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->lockForUpdate()->firstOrFail();
        if ($ret->status !== 'pending_approval' || $ret->prepared_by === $approver->getKey()) {
            throw new \DomainException('Supplier return rejection requires an independent approver.');
        }
        DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->update(['status' => 'rejected', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
        $po = DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $ret->purchase_order_id)->firstOrFail();
        $this->audit->record(action: 'procurement.supplier_return.rejected', after: ['return_id' => $ret->id, 'purchase_order_id' => $po->id, 'reason' => trim($reason)], module: 'procurement');

        return DB::connection('tenant')->table('procurement_supplier_returns')->where('id', $returnId)->firstOrFail();
    }

    // Aliases for common naming variants.
    public function recordSupplierReturn(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareSupplierReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function submitSupplierReturn(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareSupplierReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function createSupplierReturn(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareSupplierReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function prepareCorrection(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareSupplierReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function prepareQuantityCorrection(string $purchaseOrderId, string $lineId, int $quantityMilli, string $reason, User $preparer, ?string $idempotencyKey = null): object
    {
        return $this->prepareSupplierReturn($purchaseOrderId, $lineId, $quantityMilli, $reason, $preparer, $idempotencyKey);
    }

    public function approveCorrection(string $returnId, User $approver): object
    {
        return $this->approveSupplierReturn($returnId, $approver);
    }

    public function approveQuantityCorrection(string $returnId, User $approver): object
    {
        return $this->approveSupplierReturn($returnId, $approver);
    }

    public function rejectCorrection(string $returnId, string $reason, User $approver): object
    {
        return $this->rejectSupplierReturn($returnId, $reason, $approver);
    }

    private function writeOrderVersion(string $purchaseOrderId, int $version, int $totalMinor, ?string $amendmentId, string $effectiveDate, string $userId, ?string $reason = null): void
    {
        $versionId = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_order_versions')->insert(['id' => $versionId, 'purchase_order_id' => $purchaseOrderId, 'version_number' => $version, 'total_minor' => $totalMinor, 'reason' => $reason, 'effective_date' => $effectiveDate, 'amendment_id' => $amendmentId, 'created_by' => $userId, 'created_at' => now()]);
        foreach (DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $purchaseOrderId)->orderBy('id')->get() as $line) {
            DB::connection('tenant')->table('procurement_order_version_lines')->insert(['id' => (string) Str::ulid(), 'order_version_id' => $versionId, 'purchase_order_line_id' => $line->id, 'description' => $line->description, 'account_code' => $line->account_code, 'dimensions' => is_string($line->dimensions) ? $line->dimensions : json_encode($line->dimensions), 'ordered_quantity' => $line->ordered_quantity, 'unit_price_minor' => $line->unit_price_minor, 'line_total_minor' => $line->line_total_minor]);
        }
    }

    private function recordOrderEvent(string $purchaseOrderId, string $eventType, string $status, ?string $reason, array $details, ?string $preparedBy, ?string $approvedBy): void
    {
        DB::connection('tenant')->table('procurement_order_events')->insert(['id' => (string) Str::ulid(), 'purchase_order_id' => $purchaseOrderId, 'event_type' => $eventType, 'status' => $status, 'reason' => $reason, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'prepared_by' => $preparedBy, 'approved_by' => $approvedBy, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
