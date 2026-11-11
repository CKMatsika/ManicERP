<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Accounting\Models\MigrationBatch;
use App\Modules\Accounting\Services\OpeningBalanceService;
use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\AcceptanceStepRunner;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\BankReconciliation;
use App\Modules\Revenue\Models\BankStatement;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\BillingRun;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Services\BankReconciliationService;
use App\Modules\Revenue\Services\BillingService;
use App\Modules\Revenue\Services\CashierService;
use App\Modules\Revenue\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ResumeAcceptanceRevenueCommand extends Command
{
    protected $signature = 'manicerp:acceptance:revenue {--as-of= : Statement and report cutoff date (YYYY-MM-DD)}';

    protected $description = 'Resume the isolated acceptance revenue scenario without duplicating financial records';

    public function handle(TenantManager $tenants, AcceptanceStepRunner $steps, OpeningBalanceService $opening, BillingService $billing, PaymentService $payments, CashierService $cashier, BankReconciliationService $banking, ReportingService $reports): int
    {
        try {
            if (! app()->environment('local') || config('database.connections.pgsql.database') !== 'manicerp_acceptance_central') {
                throw new RuntimeException('Acceptance revenue requires APP_ENV=local and manicerp_acceptance_central.');
            }
            $council = Council::query()->where('code', 'ACCEPTANCE_DEMO')->firstOrFail();
            if (! $council->is_demo || $council->database_name !== 'manicerp_acceptance_demo' || ! $council->isActive()) {
                throw new RuntimeException('Acceptance council identity/database guard failed.');
            }
            $tenants->connect($council);
            if (DB::connection('tenant')->getDatabaseName() !== 'manicerp_acceptance_demo') {
                throw new RuntimeException('Tenant connection is outside the acceptance database.');
            }

            $admin = User::where('email', 'admin@acceptance.test')->firstOrFail();
            $cashierUser = User::where('email', 'cashier@acceptance.test')->firstOrFail();
            $financeApprover = User::where('email', 'finance-approver@acceptance.test')->firstOrFail();
            $date = $this->option('as-of') ?: now()->toDateString();
            $periodStart = now()->startOfMonth()->toDateString();
            $summary = [];

            $summary['opening_gl'] = $steps->run('revenue.opening_gl.v1', ['cutover_date' => $date, 'cash_account' => '1110', 'amount_minor' => 10000], function () use ($opening, $admin, $financeApprover, $date): array {
                $csv = "account_code,debit,credit,description,dimensions\n1110,100.00,0.00,Opening cash on hand (synthetic acceptance),\n3010,0.00,100.00,Opening accumulated surplus (synthetic acceptance),\n";
                $batch = $opening->import(OpeningBalanceService::TYPE_GL, $csv, 'acceptance-opening-gl.csv', $date, 'USD', $admin);
                if ($batch->error_count > 0) {
                    throw new RuntimeException('Synthetic opening GL batch contains validation errors.');
                }
                if (! in_array($batch->status, ['approved', 'posted'], true)) {
                    $opening->approve($batch, $financeApprover);
                }
                if ($batch->fresh()->status !== 'posted') {
                    $opening->post($batch->fresh(), $financeApprover);
                }
                $batch->refresh();

                return ['batch_id' => $batch->id, 'status' => $batch->status, 'debit_minor' => (int) $batch->totals['debit_minor'], 'credit_minor' => (int) $batch->totals['credit_minor'], 'balanced' => true];
            });

            $summary['opening_customer'] = $steps->run('revenue.opening_customer.v1', ['cutover_date' => $date, 'invoice_minor' => 50000, 'credit_minor' => 5000], function () use ($opening, $admin, $financeApprover, $date): array {
                $account = CustomerAccount::query()->orderBy('account_number')->firstOrFail();
                $csv = "account_number,type,reference,amount,due_date,description,service_type\n".$account->account_number.',invoice,ACCEPT-OB-INV-001,500.00,'.$date.",Synthetic opening rates receivable,rates\n".$account->account_number.",unapplied,ACCEPT-OB-UNP-001,50.00,,Synthetic opening unapplied credit,\n";
                $batch = $opening->import(OpeningBalanceService::TYPE_CUSTOMER, $csv, 'acceptance-opening-customer.csv', $date, 'USD', $admin);
                if ($batch->error_count > 0) {
                    throw new RuntimeException('Synthetic opening customer batch contains validation errors.');
                }
                if (! in_array($batch->status, ['approved', 'posted'], true)) {
                    $opening->approve($batch, $financeApprover);
                }
                if ($batch->fresh()->status !== 'posted') {
                    $opening->post($batch->fresh(), $financeApprover);
                }
                $batch->refresh();

                return ['batch_id' => $batch->id, 'status' => $batch->status, 'invoice_minor' => (int) $batch->control_totals['invoice_minor'], 'credit_minor' => (int) $batch->control_totals['credit_minor'], 'journal_minor' => (int) $batch->control_totals['journal_total_minor']];
            });

            foreach (['rates', 'water', 'refuse'] as $service) {
                $summary['billing'][$service] = $steps->run('revenue.billing.'.$service, ['period_start' => $periodStart, 'period_end' => $date], function () use ($service, $periodStart, $date, $admin, $financeApprover, $billing): array {
                    $run = BillingRun::query()->where('service_type', $service)->whereDate('period_start', $periodStart)->whereDate('period_end', $date)->first();
                    $run ??= $billing->preview($service, $periodStart, $date, [], $admin);
                    if ($run->status === 'previewed') {
                        $billing->approve($run, $financeApprover);
                    }
                    if ($run->status === 'approved') {
                        $billing->post($run, $financeApprover);
                    }
                    $run->refresh();
                    if ($run->status !== 'posted') {
                        throw new RuntimeException("{$service} billing run did not post.");
                    }

                    return ['run_id' => $run->id, 'status' => $run->status, 'bills' => (int) ($run->control_totals['posted_bills'] ?? 0), 'total_minor' => (int) ($run->control_totals['posted_total_minor'] ?? 0)];
                });
            }

            // Select by immutable bill history, not today's changing balance;
            // this keeps the scenario inputs identical on every rerun.
            $targetBills = Bill::query()->where('status', '!=', 'void')->where('origin', 'billing')->whereNull('licence_application_key')->orderBy('bill_number')->get();
            if ($targetBills->count() < 2) {
                throw new RuntimeException('At least two synthetic outstanding bills are required for the two payment paths.');
            }
            $onlineBill = $targetBills[0];
            $cashBill = $targetBills->first(fn (Bill $bill) => $bill->customer_account_id !== $onlineBill->customer_account_id);
            if (! $cashBill) {
                throw new RuntimeException('A second synthetic customer account is required for the cashier scenario.');
            }

            $summary['online_payment'] = $steps->run('revenue.payment.online.002', ['bill_id' => $onlineBill->id, 'amount_minor' => (int) $onlineBill->total_minor, 'reference' => 'SBX-ACCEPTANCE-ONLINE-001'], function () use ($onlineBill, $payments): array {
                $reference = 'SBX-ACCEPTANCE-ONLINE-001';
                $existing = Payment::where('external_reference', $reference)->first();
                if ($existing) {
                    if ($existing->customer_account_id !== $onlineBill->customer_account_id || (int) $existing->amount_minor !== (int) $onlineBill->total_minor) {
                        throw new RuntimeException('Existing sandbox payment has different acceptance inputs.');
                    }

                    return ['payment_id' => $existing->id, 'amount_minor' => (int) $existing->amount_minor, 'status' => $existing->status];
                }
                $intent = $payments->createIntent($onlineBill->account, (int) $onlineBill->total_minor, 'web', 'sandbox', 'acceptance-online-001', null, ['bill_id' => $onlineBill->id]);
                if ($intent->customer_account_id !== $onlineBill->customer_account_id || (int) $intent->amount_minor !== (int) $onlineBill->total_minor || ($intent->metadata['bill_id'] ?? null) !== $onlineBill->id) {
                    throw new RuntimeException('Existing online payment intent does not match the acceptance bill snapshot.');
                }
                $payment = $payments->settle($intent, 'succeeded', $reference, ['amount_minor' => (int) $intent->amount_minor]);
                if (! $payment) {
                    throw new RuntimeException('Sandbox payment settlement did not produce a verified payment.');
                }

                return ['payment_id' => $payment->id, 'receipt_id' => $payment->receipt?->id, 'amount_minor' => (int) $payment->amount_minor, 'status' => $payment->status];
            });

            $summary['cash_payment'] = $steps->run('revenue.payment.cash.002', ['bill_id' => $cashBill->id, 'amount_minor' => (int) $cashBill->total_minor, 'reference' => 'ACCEPTANCE-CASH-001'], function () use ($cashBill, $cashierUser, $cashier, $payments): array {
                $reference = 'ACCEPTANCE-CASH-001';
                $existing = Payment::where('external_reference', $reference)->first();
                if ($existing) {
                    if ($existing->customer_account_id !== $cashBill->customer_account_id || (int) $existing->amount_minor !== (int) $cashBill->total_minor) {
                        throw new RuntimeException('Existing cashier payment has different acceptance inputs.');
                    }

                    return ['payment_id' => $existing->id, 'amount_minor' => (int) $existing->amount_minor, 'status' => $existing->status];
                }
                $session = $cashier->open($cashierUser, 0);
                $payment = $payments->captureCash($cashBill->account, (int) $cashBill->total_minor, 'cash', $reference, $session, $cashierUser);
                $cashier->close($session, (int) $payment->amount_minor, $cashierUser);

                return ['payment_id' => $payment->id, 'receipt_id' => $payment->receipt?->id, 'amount_minor' => (int) $payment->amount_minor, 'status' => $payment->status];
            });

            $bank = BankAccount::query()->where('code', 'BANK1')->where('status', 'active')->firstOrFail();
            $onlinePayment = Payment::where('external_reference', 'SBX-ACCEPTANCE-ONLINE-001')->firstOrFail();
            $summary['bank_reconciliation'] = $steps->run('revenue.bank_reconciliation.001', ['bank_account' => $bank->code, 'payment_id' => $onlinePayment->id, 'as_of' => $date], function () use ($bank, $onlinePayment, $cashierUser, $banking): array {
                $statement = BankStatement::where('bank_account_id', $bank->id)->where('statement_number', 'ACCEPTANCE-2026-001')->first();
                if (! $statement) {
                    $priorCashbook = (int) DB::connection('tenant')->table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('a.code', $bank->gl_account_code)->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $onlinePayment->received_at->toDateString())->where(function ($q) use ($onlinePayment) {
                        $q->where('e.source_type', '!=', 'payment')->orWhereNull('e.source_type')->orWhere('e.source_id', '!=', $onlinePayment->id);
                    })->selectRaw('COALESCE(SUM(l.debit_minor) - SUM(l.credit_minor), 0) balance')->value('balance');
                    $priorCashbook = (int) $priorCashbook;
                    $opening = $priorCashbook;
                    $closing = $opening + (int) $onlinePayment->amount_minor;
                    $csv = "value_date,amount,reference,narrative\n".$onlinePayment->received_at->toDateString().','.number_format($onlinePayment->amount_minor / 100, 2, '.', '').',SBX-ACCEPTANCE-ONLINE-001,Synthetic verified sandbox settlement';
                    $statement = $banking->importCsv($bank, 'ACCEPTANCE-2026-001', $onlinePayment->received_at->toDateString(), $opening, $closing, $csv, $cashierUser);
                }
                $line = $statement->lines()->firstOrFail();
                if ($line->status === 'unmatched') {
                    $banking->confirmMatch($line, $onlinePayment, $cashierUser);
                } elseif ($line->matched_payment_id !== $onlinePayment->id) {
                    throw new RuntimeException('Acceptance bank statement line matched a different payment.');
                }
                $reconciliation = BankReconciliation::where('bank_statement_id', $statement->id)->first();
                $reconciliation ??= $banking->reconcile($bank, $statement, $cashierUser);
                if ((int) $reconciliation->variance_minor !== 0) {
                    throw new RuntimeException('Acceptance synthetic statement has a non-zero cashbook variance.');
                }

                return ['statement_id' => $statement->id, 'reconciliation_id' => $reconciliation->id, 'closing_minor' => (int) $statement->closing_balance_minor, 'cashbook_minor' => (int) $reconciliation->cashbook_balance_minor, 'variance_minor' => (int) $reconciliation->variance_minor];
            });

            $asOf = now()->parse($date)->toDateString();
            $trialBalance = $reports->trialBalance($asOf);
            $accountCodes = ['1110', '1120', '1310', '1320', '1330', '2050', '3010', '4010', '4020', '4030', '4040'];
            $gl = DB::connection('tenant')->table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $asOf)->whereIn('a.code', $accountCodes)->groupBy('a.code')->select('a.code')->selectRaw('SUM(l.debit_minor) debit_minor, SUM(l.credit_minor) credit_minor')->get()->keyBy('code');
            $subledger = [
                'bill_total_minor' => (int) Bill::sum('total_minor'),
                'bill_balance_minor' => (int) Bill::whereIn('status', ['issued', 'partially_paid'])->sum('balance_minor'),
                'verified_payment_minor' => (int) Payment::where('status', 'verified')->sum('amount_minor'),
                'receipt_minor' => (int) DB::connection('tenant')->table('receipts')->where('status', 'issued')->sum('amount_minor'),
                'allocation_minor' => (int) DB::connection('tenant')->table('payment_allocations')->whereNull('reversed_at')->sum('amount_minor'),
                'unapplied_credit_minor' => (int) Payment::where('status', 'verified')->sum(DB::raw('amount_minor')) - (int) DB::connection('tenant')->table('payment_allocations')->whereNull('reversed_at')->sum('amount_minor'),
                'opening_customer_credit_minor' => (int) AccountCredit::where('source', 'opening_balance')->sum('remaining_minor'),
                'net_debt_after_opening_credits_minor' => (int) Bill::whereIn('status', ['issued', 'partially_paid'])->sum('balance_minor') - (int) AccountCredit::where('status', 'open')->sum('remaining_minor'),
            ];
            $receivablesGl = (int) $gl->only(['1310', '1320', '1330'])->sum(fn ($row) => (int) $row->debit_minor - (int) $row->credit_minor);
            $cashbookBank = (int) ($gl->get('1120')->debit_minor ?? 0) - (int) ($gl->get('1120')->credit_minor ?? 0);
            $cashOnHand = (int) ($gl->get('1110')->debit_minor ?? 0) - (int) ($gl->get('1110')->credit_minor ?? 0);
            $latestBankStatement = BankStatement::query()
                ->where('bank_account_id', $bank->id)
                ->orderByDesc('statement_date')
                ->orderByDesc('created_at')
                ->first();
            $latestBankReconciliation = $latestBankStatement
                ? BankReconciliation::query()->where('bank_statement_id', $latestBankStatement->id)->latest('created_at')->first()
                : null;
            $currentBankVariance = $latestBankStatement ? (int) $latestBankStatement->closing_balance_minor - $cashbookBank : null;
            $recognizedRevenue = (int) $gl->only(['4010', '4020', '4030', '4040'])->sum(fn ($row) => (int) $row->credit_minor - (int) $row->debit_minor);
            $summary['independent_reconciliation'] = [
                'as_of' => $asOf,
                'subledger' => $subledger,
                'gl' => $gl->map(fn ($row) => ['debit_minor' => (int) $row->debit_minor, 'credit_minor' => (int) $row->credit_minor])->all(),
                'control_totals' => [
                    'customer_bill_debt_to_receivable_control_difference_minor' => (int) $subledger['bill_balance_minor'] - $receivablesGl,
                    'bank_cashbook_minor' => $cashbookBank,
                    'latest_bank_statement_minor' => $latestBankStatement ? (int) $latestBankStatement->closing_balance_minor : null,
                    'latest_bank_statement_reconciliation_minor' => $latestBankReconciliation ? (int) $latestBankReconciliation->variance_minor : null,
                    'current_bank_statement_to_cashbook_difference_minor' => $currentBankVariance,
                    'cash_on_hand_minor' => $cashOnHand,
                    'recognized_revenue_minor' => $recognizedRevenue,
                    'deferred_recognition_bill_count' => Bill::where('revenue_recognition_method', 'deferred')->count(),
                    'liability_2050_credit_balance_minor' => (int) ($gl->get('2050')->credit_minor ?? 0) - (int) ($gl->get('2050')->debit_minor ?? 0),
                    'posted_opening_batches' => MigrationBatch::where('status', 'posted')->whereDate('cutover_date', $date)->count(),
                ],
                'trial_balance' => $trialBalance['totals'],
                'bill_receipt_allocation_counts' => ['bills' => Bill::count(), 'payments' => Payment::count(), 'receipts' => DB::connection('tenant')->table('receipts')->count(), 'allocations' => DB::connection('tenant')->table('payment_allocations')->count()],
                'bank_variance_minor' => $currentBankVariance,
            ];
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
