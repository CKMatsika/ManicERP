<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Services\CouncilProvisioner;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Models\SupplierInvoice;
use App\Modules\Procurement\Models\SupplierPaymentProposal;
use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ProcurementConcurrencyTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    public function test_concurrent_budget_payment_and_order_operations_serialize_on_postgresql(): void
    {
        $suffix = strtolower(Str::random(12));
        $database = 'manicerp_test_proc_race_'.$suffix;
        $provisioner = app(CouncilProvisioner::class);
        $council = null;
        $users = [];
        $processes = [];
        $created = false;
        DB::connection('pgsql')->commit();

        try {
            $provisioner->createDatabase($database);
            $created = true;
            $council = $this->makeCouncil(['code' => 'PR'.strtoupper($suffix), 'database_name' => $database]);
            $provisioner->migrateTenant($council);
            $role = $this->makeRole($council, 'procurement_race_staff', ['*']);
            foreach (['preparer', 'approver_a', 'approver_b', 'receiver'] as $name) {
                $users[$name] = $this->makeUser($name.$suffix.'@proc-race.test');
                $this->makeMembership($users[$name], $council, $role);
            }
            $this->useCouncil($council);
            $this->seedAccounting($council, $users['preparer']);
            $controls = app(SupplierBudgetService::class);
            $sourcing = app(SourcingService::class);
            $ap = app(AccountsPayableService::class);

            // Two requests for 80% of the same line start concurrently. One
            // reserves; the waiter must re-read the committed available balance.
            $budget = $controls->createBudget('RACE-'.$suffix, 'Concurrency reservation fixture', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 10000]], $users['preparer']);
            $controls->approveBudget($budget, $users['approver_a']);
            $line = $budget->lines->first();
            $reqA = $sourcing->prepareRequisition($line, 'Race request A', 'Concurrency fixture', 8000, $users['preparer']);
            $reqB = $sourcing->prepareRequisition($line, 'Race request B', 'Concurrency fixture', 8000, $users['preparer']);
            $this->commitSetup();
            [$first, $second] = $this->contend('submit_requisition', $line->id, $reqA->id, $reqB->id, $users['preparer'], $users['preparer'], $council, $suffix.'-budget', $processes);
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->useCouncil($council);
            $line = BudgetLine::findOrFail($line->id);
            $this->assertSame(8000, (int) $line->committed_minor);
            $this->assertSame(2000, $line->availableMinor());
            $this->assertSame(1, DB::connection('tenant')->table('procurement_requisitions')->whereIn('status', ['submitted', 'approved'])->count());
            $this->assertSame(1, DB::connection('tenant')->table('procurement_commitments')->count());

            $supplier = $this->supplier($controls, $council, $users['preparer'], $users['approver_a'], 'RACE-SUP-'.$suffix);
            $invoice = $this->approvedNonPoInvoice($ap, $supplier, $users['preparer'], $users['approver_a'], $users['approver_b'], 'APPROVAL-'.$suffix, 10000);
            $proposalA = $ap->preparePayment($invoice, 7000, 'race-proposal-a-'.$suffix, $users['preparer']);
            $proposalB = $ap->preparePayment($invoice, 7000, 'race-proposal-b-'.$suffix, $users['preparer']);
            $journalCount = DB::connection('tenant')->table('journal_entries')->where('source_type', 'supplier_payment')->count();
            $this->commitSetup();
            [$first, $second] = $this->contend('approve_payment', $invoice->id, $proposalA->id, $proposalB->id, $users['approver_a'], $users['approver_b'], $council, $suffix.'-approval', $processes);
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->useCouncil($council);
            $approvedTotal = (int) SupplierPaymentProposal::where('invoice_id', $invoice->id)->where('status', 'approved')->sum('amount_minor');
            $this->assertSame(7000, $approvedTotal);
            $this->assertSame(10000, (int) SupplierInvoice::findOrFail($invoice->id)->total_minor);
            $this->assertSame(0, (int) SupplierInvoice::findOrFail($invoice->id)->paid_minor);
            $this->assertSame($journalCount, DB::connection('tenant')->table('journal_entries')->where('source_type', 'supplier_payment')->count());

            // A second invoice reserves its exact amount in two independent
            // proposals. Their sandbox settlements overlap and total exactly
            // the payable; retrying one callback must not add a journal.
            $settlementInvoice = $this->approvedNonPoInvoice($ap, $supplier, $users['preparer'], $users['approver_a'], $users['approver_b'], 'SETTLE-'.$suffix, 10000);
            $payA = $ap->approvePayment($ap->preparePayment($settlementInvoice, 5000, 'race-settle-a-'.$suffix, $users['preparer']), $users['approver_a']);
            $payB = $ap->approvePayment($ap->preparePayment($settlementInvoice, 5000, 'race-settle-b-'.$suffix, $users['preparer']), $users['approver_b']);
            $this->commitSetup();
            [$first, $second] = $this->contend('settle_payment', $settlementInvoice->id, $payA->id, $payB->id, $users['approver_a'], $users['approver_b'], $council, $suffix.'-settlement', $processes);
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());
            $this->useCouncil($council);
            $this->assertSame(10000, (int) SupplierInvoice::findOrFail($settlementInvoice->id)->paid_minor);
            $payments = DB::connection('tenant')->table('procurement_supplier_payments')->where('invoice_id', $settlementInvoice->id)->get();
            $this->assertCount(2, $payments);
            $this->assertSame(10000, (int) $payments->sum('amount_minor'));
            $paymentJournalCount = DB::connection('tenant')->table('journal_entries')->where('source_type', 'supplier_payment')->count();
            $same = $ap->settleSandbox($payA, 'RACE-SETTLE-'.$payA->id, $users['approver_a']);
            $this->assertSame($payments->firstWhere('proposal_id', $payA->id)->id, $same->id);
            $this->assertSame($paymentJournalCount, DB::connection('tenant')->table('journal_entries')->where('source_type', 'supplier_payment')->count());

            $retryInvoice = $this->approvedNonPoInvoice($ap, $supplier, $users['preparer'], $users['approver_a'], $users['approver_b'], 'RETRY-'.$suffix, 5000);
            $retryProposal = $ap->approvePayment($ap->preparePayment($retryInvoice, 5000, 'race-retry-'.$suffix, $users['preparer']), $users['approver_a']);
            $this->commitSetup();
            [$retryA, $retryB] = $this->contend('settle_payment', $retryInvoice->id, $retryProposal->id, $retryProposal->id, $users['approver_a'], $users['approver_b'], $council, $suffix.'-duplicate-callback', $processes);
            $this->assertSame(0, $retryA->wait(), $retryA->getErrorOutput());
            $this->assertSame(0, $retryB->wait(), $retryB->getErrorOutput());
            $this->useCouncil($council);
            $this->assertSame(1, DB::connection('tenant')->table('procurement_supplier_payments')->where('proposal_id', $retryProposal->id)->count());
            $this->assertSame(1, DB::connection('tenant')->table('journal_entries')->where('idempotency_key', 'procurement:settlement:'.$retryProposal->id)->count());

            // Cancellation and invoice posting share the PO lock. A received
            // order cannot be cancelled; the matched invoice still posts once.
            $largeBudget = $controls->createBudget('RACE-PO-'.$suffix, 'Order race budget', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 100000]], $users['preparer']);
            $controls->approveBudget($largeBudget, $users['approver_a']);
            $req = $sourcing->prepareRequisition($largeBudget->lines->first(), 'Order race request', 'Cancellation posting race', 10000, $users['preparer']);
            $req = $sourcing->approveRequisition($sourcing->submitRequisition($req->id, $users['preparer'])->id, $users['approver_a']);
            $quote = $sourcing->submitQuotation($req->id, $supplier, 'RACE-Q-'.$suffix, 10000, 'Synthetic evaluation evidence', $users['preparer']);
            config(['procurement.approval_thresholds_minor.quotation_count' => 1]);
            $award = $sourcing->prepareAward($quote->id, 'Approved test comparison', $users['preparer']);
            $po = $sourcing->approveAward($award->id, $users['approver_a']);
            $poLineId = DB::connection('tenant')->table('procurement_purchase_order_lines')->where('purchase_order_id', $po->id)->value('id');
            $amendmentA = $sourcing->prepareOrderAmendment($po->id, [$poLineId => ['quantity_milli' => 1200]], 'Increase service quantity for concurrency test', now()->toDateString(), $users['preparer']);
            $amendmentB = $sourcing->prepareOrderAmendment($po->id, [$poLineId => ['quantity_milli' => 1200]], 'Competing increase from same version', now()->toDateString(), $users['preparer']);
            $this->commitSetup();
            [$amendA, $amendB] = $this->contend('approve_amendment', $po->id, $amendmentA->id, $amendmentB->id, $users['approver_a'], $users['approver_b'], $council, $suffix.'-amendment', $processes);
            $this->assertSame(0, $amendA->wait(), $amendA->getErrorOutput());
            $this->assertSame(0, $amendB->wait(), $amendB->getErrorOutput());
            $this->useCouncil($council);
            $this->assertSame(2, (int) DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->value('current_version'));
            $this->assertSame(12000, (int) DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->value('total_minor'));
            $this->assertSame(2, DB::connection('tenant')->table('procurement_order_versions')->where('purchase_order_id', $po->id)->count());
            $this->assertSame(1, DB::connection('tenant')->table('procurement_order_amendments')->where('purchase_order_id', $po->id)->where('status', 'approved')->count());
            $rejectedAmendment = DB::connection('tenant')->table('procurement_order_amendments')->where('purchase_order_id', $po->id)->where('status', 'pending_approval')->firstOrFail();
            $sourcing->rejectOrderAmendment($rejectedAmendment->id, 'Superseded by the approved version.', $users['approver_b']);
            $this->assertSame('1.200', DB::connection('tenant')->table('procurement_purchase_order_lines')->where('id', $poLineId)->value('ordered_quantity'));
            $sourcing->acceptReceipt($po->id, [$poLineId => 1000], 'race-po-receipt-'.$suffix, 'Full synthetic receipt', $users['receiver']);
            $orderInvoice = $ap->prepareInvoice($supplier, 'RACE-PO-INV-'.$suffix, '2026-11-05', '2026-12-05', 10000, 'race-po-invoice-'.$suffix, $users['preparer'], $po->id, $poLineId, 1000);
            $this->commitSetup();
            [$cancel, $post] = $this->contend('cancel_order', $po->id, $po->id, $orderInvoice->id, $users['approver_b'], $users['approver_a'], $council, $suffix.'-cancel-post', $processes, secondAction: 'approve_invoice');
            $this->assertSame(0, $cancel->wait(), $cancel->getErrorOutput());
            $this->assertSame(0, $post->wait(), $post->getErrorOutput());
            $this->useCouncil($council);
            $this->assertSame('approved', DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->value('status'));
            $this->assertSame('approved', SupplierInvoice::findOrFail($orderInvoice->id)->status);
            $this->assertSame(1, DB::connection('tenant')->table('journal_entries')->where('source_type', 'supplier_invoice')->where('source_id', $orderInvoice->id)->count());
            try {
                $sourcing->prepareOrderAmendment($po->id, [$poLineId => ['quantity_milli' => 900]], 'Attempt to reduce below accepted receipt quantity.', now()->toDateString(), $users['preparer']);
                $this->fail('A purchase order cannot be amended below received or invoiced quantity.');
            } catch (\DomainException $exception) {
                $this->assertStringContainsString('cannot fall below received', $exception->getMessage());
            }
            $this->assertSame(2, (int) DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $po->id)->value('current_version'));
            $remaining = $sourcing->cancelOutstandingQuantity($po->id, $poLineId, 200, 'Cancel unneeded unreceived service portion.', $users['approver_b']);
            $this->assertSame('0.200', $remaining->cancelled_quantity);
            $closed = $sourcing->closePurchaseOrder($po->id, 'Receipt and accepted invoice complete; payment remains outstanding.', $users['approver_b']);
            $this->assertSame('partially_cancelled', $closed->status);
            $this->assertSame(10000, (int) SupplierInvoice::findOrFail($orderInvoice->id)->total_minor - (int) SupplierInvoice::findOrFail($orderInvoice->id)->paid_minor);
            $this->assertSame(0, (int) DB::connection('tenant')->table('procurement_budget_lines')->where('id', $largeBudget->lines->first()->id)->value('committed_minor'));
            $this->assertSame(10000, (int) DB::connection('tenant')->table('procurement_budget_lines')->where('id', $largeBudget->lines->first()->id)->value('actual_minor'));
            $reopen = $sourcing->requestReopen($po->id, 'Synthetic correction requires a new separately approved amendment.', $users['preparer']);
            try {
                $sourcing->approveReopen($reopen->id, $users['preparer']);
                $this->fail('A requester must not approve their own reopening request.');
            } catch (\DomainException $exception) {
                $this->assertStringContainsString('independently approved', $exception->getMessage());
            }
            $approvedReopen = $sourcing->approveReopen($reopen->id, $users['approver_a']);
            $this->assertSame('approved', $approvedReopen->status);
            $this->assertSame(10000, (int) DB::connection('tenant')->table('procurement_budget_lines')->where('id', $largeBudget->lines->first()->id)->value('actual_minor'));
            $closedAgain = $sourcing->closePurchaseOrder($po->id, 'Reopened record reviewed and closed again.', $users['approver_b']);
            $this->assertSame('partially_cancelled', $closedAgain->status);
            $this->assertSame(2, DB::connection('tenant')->table('procurement_order_events')->where('purchase_order_id', $po->id)->where('event_type', 'closed')->count());
            $this->assertSame(1, DB::connection('tenant')->table('procurement_order_events')->where('purchase_order_id', $po->id)->where('event_type', 'reopened')->count());
            $oldVersionLine = DB::connection('tenant')->table('procurement_order_version_lines as l')->join('procurement_order_versions as v', 'v.id', '=', 'l.order_version_id')->where('v.purchase_order_id', $po->id)->where('v.version_number', 1)->first();
            $this->assertSame('1.000', $oldVersionLine->ordered_quantity);
            $apControl = $ap->reconciliation('2026-12-31');
            $this->assertSame(0, $apControl['difference_minor']);
            $this->assertTrue($apControl['reconciled']);
            $this->assertTrue((bool) app(ReportingService::class)->trialBalance('2026-12-31')['totals']['balanced']);
            $this->assertTrue((bool) app(ReportingService::class)->trialBalance('2026-12-31')['totals']['balanced']);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            app(TenantManager::class)->clear();
            if ($council) {
                DB::connection('pgsql')->table('council_memberships')->where('council_id', $council->id)->delete();
                DB::connection('pgsql')->table('councils')->where('id', $council->id)->delete();
            }
            foreach ($users as $user) {
                DB::connection('pgsql')->table('users')->where('id', $user->id)->delete();
            }
            if ($created && preg_match('/^manicerp_test_proc_race_[a-z0-9]{12}$/', $database)) {
                $provisioner->dropDatabase($database);
            }
        }
    }

    private function supplier(SupplierBudgetService $controls, $council, $preparer, $approver, string $code)
    {
        $this->useCouncil($council);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Synthetic race supplier '.$code, 'status' => 'active', 'code' => $code]);

        return $controls->approveSupplier($controls->onboard($party, $code, $preparer), $approver);
    }

    private function approvedNonPoInvoice(AccountsPayableService $ap, $supplier, $preparer, $exceptionApprover, $poster, string $code, int $amount): SupplierInvoice
    {
        $invoice = $ap->prepareInvoice($supplier, 'INV-'.$code, '2026-11-01', '2026-12-01', $amount, 'invoice-'.$code, $preparer, nonPoExpenseCode: '5020');
        $invoice = $ap->approveMatchException($invoice, 'Synthetic non-PO exception for concurrency test', $exceptionApprover);

        return $ap->approveInvoice($invoice, $poster);
    }

    private function contend(string $action, string $lockId, string $firstOperationId, string $secondOperationId, $firstUser, $secondUser, $council, string $suffix, array &$processes, ?string $secondAction = null): array
    {
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => 'manicerp_test_central', 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '',
            'TENANT_DB_HOST' => config('database.connections.tenant.host'), 'TENANT_DB_PORT' => (string) config('database.connections.tenant.port'),
            'TENANT_DB_USERNAME' => config('database.connections.tenant.username'), 'TENANT_DB_PASSWORD' => config('database.connections.tenant.password') ?? '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $input = new InputStream;
        $marker = 'proc-race-'.preg_replace('/[^a-z0-9]/', '', $suffix);
        $first = new Process([PHP_BINARY, base_path('tests/Support/procurement_race.php'), $council->id, $firstUser->id, $action, $lockId, $firstOperationId, 'hold', $marker.'-a'], base_path(), $environment, $input, 45);
        $processes[] = $first;
        $first->start();
        $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'locked')), $first->getErrorOutput());
        $second = new Process([PHP_BINARY, base_path('tests/Support/procurement_race.php'), $council->id, $secondUser->id, $secondAction ?? $action, $lockId, $secondOperationId, 'compete', $marker.'-b'], base_path(), $environment, null, 45);
        $processes[] = $second;
        $second->start();
        $this->assertTrue($second->waitUntil(fn ($type, $output) => str_contains($output, 'attempting')), $second->getErrorOutput());
        $blocked = false;
        for ($attempt = 0; $attempt < 150; $attempt++) {
            $blocked = (bool) DB::connection('pgsql')->selectOne("SELECT 1 AS blocked FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$marker.'-b']);
            if ($blocked) {
                break;
            }
            usleep(20000);
        }
        $this->assertTrue($blocked, "Second [{$action}] operation did not block on the expected PostgreSQL row lock.");
        $input->write("go\n");
        $input->close();

        return [$first, $second];
    }

    private function commitSetup(): void
    {
        if (DB::connection('pgsql')->transactionLevel() > 0) {
            DB::connection('pgsql')->commit();
        }
    }
}
