<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic reporting queries. Every figure here is derived from posted
 * journal lines, not stored aggregates, so it drills down to source.
 */
class ReportingService
{
    /** @return array{rows: array<int, array<string, mixed>>, totals: array<string, int>, as_of: string} */
    public function trialBalance(string $asOf): array
    {
        $asOf = CarbonImmutable::parse($asOf)->toDateString();

        $rows = DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_class', 'a.normal_balance')
            ->select([
                'a.id', 'a.code', 'a.name', 'a.account_class', 'a.normal_balance',
                DB::raw('SUM(l.debit_minor) as debit_minor'),
                DB::raw('SUM(l.credit_minor) as credit_minor'),
            ])
            ->orderBy('a.code')
            ->get()
            ->map(function ($row) {
                $debit = (int) $row->debit_minor;
                $credit = (int) $row->credit_minor;
                $balance = $debit - $credit;

                return [
                    'code' => $row->code,
                    'name' => $row->name,
                    'class' => $row->account_class,
                    'normal_balance' => $row->normal_balance,
                    'debit_minor' => $debit,
                    'credit_minor' => $credit,
                    'balance_minor' => $balance,
                ];
            })
            ->all();

        $totalDebit = array_sum(array_column($rows, 'debit_minor'));
        $totalCredit = array_sum(array_column($rows, 'credit_minor'));

        return [
            'rows' => $rows,
            'totals' => [
                'debit_minor' => $totalDebit,
                'credit_minor' => $totalCredit,
                'balanced' => $totalDebit === $totalCredit,
            ],
            'as_of' => $asOf,
        ];
    }

    /** @return Collection<int, object> */
    public function generalLedger(string $accountCode, string $from, string $to): Collection
    {
        return DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('a.code', $accountCode)
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->orderBy('e.entry_date')
            ->orderBy('e.entry_number')
            ->orderBy('l.line_no')
            ->select([
                'e.entry_number', 'e.entry_date', 'e.description', 'e.journal_type',
                'l.debit_minor', 'l.credit_minor', 'l.memo', 'l.dimensions',
            ])
            ->get();
    }

    /**
     * @return array{assets: array<string, mixed>, liabilities: array<string, mixed>, net_assets: array<string, mixed>, result: array<string, mixed>, check: int}
     */
    public function financialPosition(string $asOf): array
    {
        $asOf = Carbon::parse($asOf)->toDateString();
        $balances = $this->classBalances($asOf);

        $assets = $balances['asset'] ?? 0;
        $liabilities = -($balances['liability'] ?? 0);
        $netAssets = -($balances['net_asset'] ?? 0);
        $revenue = -($balances['revenue'] ?? 0);
        $expenses = $balances['expense'] ?? 0;
        $result = $revenue - $expenses;

        return [
            'as_of' => $asOf,
            'assets_minor' => $assets,
            'liabilities_minor' => $liabilities,
            'net_assets_minor' => $netAssets,
            'revenue_minor' => $revenue,
            'expenses_minor' => $expenses,
            'result_minor' => $result,
            'check' => $assets - ($liabilities + $netAssets + $result),
        ];
    }

    /** @return array{revenue: array<int, array<string, mixed>>, expenses: array<int, array<string, mixed>>, totals: array<string, int>} */
    public function financialPerformance(string $from, string $to): array
    {
        $rows = DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->whereIn('a.account_class', ['revenue', 'expense'])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_class')
            ->select([
                'a.code', 'a.name', 'a.account_class',
                DB::raw('SUM(l.debit_minor) as debit_minor'),
                DB::raw('SUM(l.credit_minor) as credit_minor'),
            ])
            ->orderBy('a.code')
            ->get();

        $revenue = [];
        $expenses = [];

        foreach ($rows as $row) {
            $amount = (int) $row->debit_minor - (int) $row->credit_minor;

            if ($row->account_class === 'revenue') {
                $revenue[] = ['code' => $row->code, 'name' => $row->name, 'amount_minor' => -$amount];
            } else {
                $expenses[] = ['code' => $row->code, 'name' => $row->name, 'amount_minor' => $amount];
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => [
                'revenue_minor' => array_sum(array_column($revenue, 'amount_minor')),
                'expenses_minor' => array_sum(array_column($expenses, 'amount_minor')),
            ],
        ];
    }

    /** @return array<string, int> */
    private function classBalances(string $asOf): array
    {
        return DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->groupBy('a.account_class')
            ->select(['a.account_class', DB::raw('SUM(l.debit_minor) - SUM(l.credit_minor) as balance')])
            ->pluck('balance', 'account_class')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /** @return Collection<int, GlAccount> */
    public function accounts(): Collection
    {
        return GlAccount::query()
            ->whereHas('chartVersion', fn ($query) => $query->where('status', 'active'))
            ->orderBy('code')
            ->get();
    }
}
