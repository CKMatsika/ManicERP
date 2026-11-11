<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Foundational disclosure helpers. These are derived from posted GL data only
 * and are explicitly partial: they are not a complete IPSAS disclosure set.
 */
class DisclosureService
{
    /** @return array{opening_net_assets_minor: int, result_minor: int, closing_net_assets_minor: int, movements: array<int, array<string, mixed>>} */
    public function changesInNetAssets(string $from, string $to): array
    {
        $asOfBefore = date('Y-m-d', strtotime($from.' -1 day'));
        $position = app(ReportingService::class)->financialPosition($asOfBefore);
        $perf = app(ReportingService::class)->financialPerformance($from, $to);
        $closing = app(ReportingService::class)->financialPosition($to);

        return [
            'from' => $from,
            'to' => $to,
            'opening_net_assets_minor' => $position['net_assets_minor'] + $position['result_minor'],
            'result_minor' => $perf['totals']['revenue_minor'] - $perf['totals']['expenses_minor'],
            'closing_net_assets_minor' => $closing['net_assets_minor'] + $closing['result_minor'],
            'movements' => [
                ['label' => 'Revenue', 'amount_minor' => $perf['totals']['revenue_minor']],
                ['label' => 'Expenses', 'amount_minor' => -$perf['totals']['expenses_minor']],
            ],
            'disclosure_status' => 'partial',
        ];
    }

    /** @return array{operating_minor: int, investing_minor: int, financing_minor: int, total_minor: int, status: string} */
    public function cashFlowSummary(string $from, string $to): array
    {
        $rows = DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->whereIn('a.account_class', ['asset', 'liability', 'equity'])
            ->groupBy('a.account_class')
            ->select(['a.account_class', DB::raw('SUM(l.debit_minor) - SUM(l.credit_minor) as movement')])
            ->pluck('movement', 'account_class')
            ->map(fn ($v) => (int) $v)
            ->all();

        $operating = (int) ($rows['asset'] ?? 0);

        // Heuristic: no full cash-flow classification yet.
        return [
            'from' => $from,
            'to' => $to,
            'operating_minor' => $operating,
            'investing_minor' => 0,
            'financing_minor' => 0,
            'total_minor' => $operating,
            'status' => 'heuristic_partial',
        ];
    }
}
