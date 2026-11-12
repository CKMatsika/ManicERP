<?php

namespace App\Http\Controllers;

use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Land\Models\HousingAllocation;
use App\Modules\Land\Models\HousingWaitingListEntry;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Procurement\Models\StoresItem;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\PaymentPlan;
use App\Modules\Revenue\Models\WaterMeter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'outstanding_bills' => 0,
            'collected_today_minor' => 0,
            'billed_this_month_minor' => 0,
            'receipts_today' => 0,
        ];
        $recentBills = [];
        $recentEvents = [];
        $position = null;

        if ($revenueEnabled) {
            $stats['accounts'] = CustomerAccount::where('status', 'active')->count();
            $stats['outstanding_minor'] = (int) Bill::whereIn('status', ['issued', 'partially_paid'])->sum('balance_minor');
            $stats['outstanding_bills'] = Bill::whereIn('status', ['issued', 'partially_paid'])->where('balance_minor', '>', 0)->count();
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

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->is_platform_admin || $user->email === 'admin@demo.manicerp.test' || $this->tenants->membership()?->role?->code === 'superadmin');

        if ($isSuperAdmin) {
            $revenueEnabled = true;
            $accountingEnabled = true;
        }

        if ($accountingEnabled) {
            $position = $reporting->financialPosition(now()->toDateString());
        }

        // Dedicated Municipal Finance & Treasury Metrics
        $accountingStats = [
            'gl_accounts_count' => Schema::connection('tenant')->hasTable('gl_accounts') ? GlAccount::count() : 0,
            'journals_count' => Schema::connection('tenant')->hasTable('journal_entries') ? JournalEntry::count() : 0,
            'posted_journals_count' => Schema::connection('tenant')->hasTable('journal_entries') ? JournalEntry::where('status', 'posted')->count() : 0,
            'cash_and_bank_minor' => Schema::connection('tenant')->hasTable('gl_accounts') ? (int) DB::connection('tenant')->table('journal_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
                ->where('e.status', 'posted')
                ->where('a.code', 'like', '10%')
                ->sum(DB::raw('l.debit_minor - l.credit_minor')) : 0,
        ];

        // Operational Department Metrics
        $moduleMetrics = [
            'gis' => [
                'stands_count' => Schema::connection('tenant')->hasTable('land_parcels') ? DB::connection('tenant')->table('land_parcels')->count() : 0,
                'valuation_minor' => Schema::connection('tenant')->hasTable('land_parcels') ? (int) DB::connection('tenant')->table('land_parcels')->sum('purchase_price_minor') : 0,
            ],
            'assets' => [
                'count' => Schema::connection('tenant')->hasTable('fixed_assets') ? FixedAsset::count() : 0,
                'carrying_amount_minor' => Schema::connection('tenant')->hasTable('fixed_assets') ? (int) FixedAsset::sum('current_carrying_amount_minor') : 0,
            ],
            'stores' => [
                'count' => Schema::connection('tenant')->hasTable('stores_items') ? StoresItem::count() : 0,
                'valuation_minor' => Schema::connection('tenant')->hasTable('stores_items') ? (int) StoresItem::sum('total_valuation_minor') : 0,
            ],
            'water' => [
                'count' => Schema::connection('tenant')->hasTable('water_meters') ? WaterMeter::count() : 0,
                'smart_count' => Schema::connection('tenant')->hasTable('water_meters') ? WaterMeter::where('communication_type', '!=', 'manual')->count() : 0,
            ],
            'housing' => [
                'applicants_count' => Schema::connection('tenant')->hasTable('housing_waiting_list_entries') ? HousingWaitingListEntry::where('status', 'active')->count() : 0,
                'allocations_count' => Schema::connection('tenant')->hasTable('housing_allocations') ? HousingAllocation::count() : 0,
            ],
            'payroll' => [
                'employees_count' => Schema::connection('tenant')->hasTable('hr_employees') ? DB::connection('tenant')->table('hr_employees')->where('status', 'active')->count() : 0,
            ],
            'licensing' => [
                'types_count' => Schema::connection('tenant')->hasTable('licence_types') ? LicenceType::count() : 0,
                'applications_count' => Schema::connection('tenant')->hasTable('licence_applications') ? LicenceApplication::count() : 0,
            ],
            'credit_control' => [
                'plans_count' => Schema::connection('tenant')->hasTable('payment_plans') ? PaymentPlan::count() : 0,
            ],
            'cemeteries' => [
                'plots_count' => Schema::connection('tenant')->hasTable('grave_plots') ? DB::connection('tenant')->table('grave_plots')->count() : 0,
                'interments_count' => Schema::connection('tenant')->hasTable('cemetery_interments') ? DB::connection('tenant')->table('cemetery_interments')->count() : 0,
            ],
            'emergency' => [
                'incidents_count' => Schema::connection('tenant')->hasTable('emergency_incidents') ? DB::connection('tenant')->table('emergency_incidents')->count() : 0,
                'hydrants_count' => Schema::connection('tenant')->hasTable('fire_hydrants') ? DB::connection('tenant')->table('fire_hydrants')->count() : 0,
            ],
            'fleet' => [
                'vehicles_count' => Schema::connection('tenant')->hasTable('fleet_vehicles') ? DB::connection('tenant')->table('fleet_vehicles')->count() : 0,
            ],
            'projects' => [
                'count' => Schema::connection('tenant')->hasTable('capital_projects') ? DB::connection('tenant')->table('capital_projects')->count() : 0,
                'budget_minor' => Schema::connection('tenant')->hasTable('capital_projects') ? (int) DB::connection('tenant')->table('capital_projects')->sum('approved_budget_minor') : 0,
            ],
        ];

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
            'accountingStats' => $accountingStats,
            'moduleMetrics' => $moduleMetrics,
            'revenueEnabled' => $revenueEnabled,
            'accountingEnabled' => $accountingEnabled,
        ]);
    }
}
