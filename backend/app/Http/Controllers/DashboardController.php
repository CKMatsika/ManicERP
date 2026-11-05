<?php

namespace App\Http\Controllers;

use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ModuleRegistry $modules,
    ) {}

    public function __invoke(ReportingService $reporting)
    {
        $council = $this->tenants->requireCouncil();
        $revenueEnabled = $this->modules->enabled($council, 'revenue');
        $accountingEnabled = $this->modules->enabled($council, 'accounting');

        $stats = [
            'accounts' => 0,
            'outstanding_minor' => 0,
            'collected_today_minor' => 0,
            'billed_this_month_minor' => 0,
            'receipts_today' => 0,
        ];
        $recentBills = [];
        $recentEvents = [];
        $position = null;
        $auditStatus = null;

        if ($revenueEnabled) {
            $stats['accounts'] = CustomerAccount::where('status', 'active')->count();
            $stats['outstanding_minor'] = (int) Bill::whereIn('status', ['issued', 'partially_paid'])->sum('balance_minor');
            $stats['collected_today_minor'] = (int) Payment::where('status', 'verified')->whereDate('received_at', today())->sum('amount_minor');
            $stats['billed_this_month_minor'] = (int) Bill::whereIn('status', ['issued', 'partially_paid', 'paid'])->whereYear('issue_date', now()->year)->whereMonth('issue_date', now()->month)->sum('total_minor');
            $stats['receipts_today'] = Payment::where('status', 'verified')->whereDate('received_at', today())->count();

            $recentBills = Bill::with('account')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(fn (Bill $bill) => [
                    'number' => $bill->bill_number,
                    'account' => $bill->account?->account_number,
                    'service' => $bill->service_type,
                    'total_minor' => $bill->total_minor,
                    'balance_minor' => $bill->balance_minor,
                    'status' => $bill->status,
                    'issue_date' => $bill->issue_date?->toDateString(),
                ])
                ->all();
        }

        if ($accountingEnabled) {
            $position = $reporting->financialPosition(now()->toDateString());
        }

        $recentEvents = AuditEvent::orderByDesc('chain_index')
            ->limit(6)
            ->get()
            ->map(fn (AuditEvent $event) => [
                'action' => $event->action,
                'actor' => $event->actor_name,
                'at' => $event->occurred_at?->toIso8601String(),
                'subject' => $event->subject_type,
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentBills' => $recentBills,
            'recentEvents' => $recentEvents,
            'position' => $position,
            'revenueEnabled' => $revenueEnabled,
            'accountingEnabled' => $accountingEnabled,
        ]);
    }
}
