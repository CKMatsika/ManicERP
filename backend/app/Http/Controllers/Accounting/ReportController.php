<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\ReportingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private readonly ReportingService $reporting) {}

    public function trialBalance(Request $request)
    {
        $asOf = $request->date('as_of')?->toDateString() ?? now()->toDateString();
        $report = $this->reporting->trialBalance($asOf);

        return Inertia::render('Accounting/TrialBalance', [
            'report' => $report,
            'asOf' => $asOf,
            'position' => $this->reporting->financialPosition($asOf),
        ]);
    }

    public function position(Request $request)
    {
        $asOf = $request->date('as_of')?->toDateString() ?? now()->toDateString();

        return Inertia::render('Accounting/Position', [
            'position' => $this->reporting->financialPosition($asOf),
            'asOf' => $asOf,
        ]);
    }

    public function performance(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        return Inertia::render('Accounting/Performance', [
            'report' => $this->reporting->financialPerformance($from, $to),
        ]);
    }

    public function ledger(Request $request)
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $account = $request->string('account')->toString();

        $accounts = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('gl_accounts')
            ->select('code', 'name')
            ->orderBy('code')
            ->get();

        if (empty($account)) {
            $account = $accounts->first()?->code ?? '1000';
        }

        $rows = $this->reporting->generalLedger($account, $from, $to);

        return Inertia::render('Accounting/Ledger', [
            'account' => $account,
            'accounts' => $accounts,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'balance_minor' => $rows->sum(fn ($row) => (int) $row->debit_minor - (int) $row->credit_minor),
        ]);
    }
}
