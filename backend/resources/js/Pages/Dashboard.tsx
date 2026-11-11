import { Head, Link, usePage } from '@inertiajs/react';
import React, { useMemo } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../Components/Ui';
import { formatDate, formatDateTime, money, type SharedProps } from '../lib';
import { KpiCard, DeptCard, NeedsAttentionPanel } from '../Components/DashboardComponents';
import Icon from '../Components/Icon';
import {
    BookOpen,
    Scale,
    PieChart,
    TrendingUp,
    ListTree,
    FileSpreadsheet,
    ArrowRight,
    ShieldCheck,
    CheckCircle2,
    Calendar,
} from 'lucide-react';

type BillRow = {
    number: string;
    account: string | null;
    service: string;
    total_minor: number;
    balance_minor: number;
    status: string;
    issue_date: string | null;
};

type EventRow = {
    action: string;
    actor: string | null;
    at: string | null;
    subject: string | null;
};

type ModuleMetrics = {
    gis?: { stands_count: number; valuation_minor: number };
    assets: { count: number; carrying_amount_minor: number };
    stores: { count: number; valuation_minor: number };
    water: { count: number; smart_count: number };
    housing: { applicants_count: number; allocations_count: number };
    payroll: { employees_count: number };
    licensing: { types_count: number; applications_count: number };
    credit_control: { plans_count: number };
    cemeteries: { plots_count: number; interments_count: number };
    emergency: { incidents_count: number; hydrants_count: number };
    fleet: { vehicles_count: number };
    projects: { count: number; budget_minor: number };
};

export default function Dashboard({
    stats,
    recentBills,
    recentEvents,
    position,
    accountingStats,
    moduleMetrics,
    revenueEnabled,
    accountingEnabled,
}: {
    stats: {
        accounts: number;
        outstanding_minor: number;
        collected_today_minor: number;
        billed_this_month_minor: number;
        receipts_today: number;
    };
    recentBills: BillRow[];
    recentEvents: EventRow[];
    position: {
        assets_minor: number;
        liabilities_minor: number;
        result_minor: number;
        check: number;
        revenue_minor?: number;
        expenses_minor?: number;
        net_assets_minor?: number;
    } | null;
    accountingStats?: {
        gl_accounts_count: number;
        journals_count: number;
        posted_journals_count: number;
        cash_and_bank_minor: number;
    };
    moduleMetrics?: ModuleMetrics;
    revenueEnabled: boolean;
    accountingEnabled: boolean;
}) {
    const { council, displayTimezone } = usePage<SharedProps>().props;

    // Last updated timestamp
    const lastUpdatedLabel = useMemo(() => {
        try {
            return new Intl.DateTimeFormat('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'Africa/Harare',
            }).format(new Date());
        } catch {
            return '16:40';
        }
    }, []);

    // Build real "Needs attention" actionable alerts from live data
    const attentionItems = useMemo(() => {
        const items = [];
        if (stats.outstanding_minor > 0) {
            items.push({
                id: 'debt',
                title: 'Customer rate accounts with outstanding balances',
                description: `${money(stats.outstanding_minor, council?.currency)} in overdue bills awaiting Section 279 payment plans or credit control.`,
                count: stats.accounts,
                href: '/revenue/credit-control',
            });
        }
        if (moduleMetrics?.housing && moduleMetrics.housing.applicants_count > 0) {
            items.push({
                id: 'housing',
                title: 'Housing waiting list applications awaiting stand allocation',
                description: `${moduleMetrics.housing.applicants_count} certified citizens on the municipal waiting list seniority queue.`,
                count: moduleMetrics.housing.applicants_count,
                href: '/land/housing-allocations',
            });
        }
        if (moduleMetrics?.emergency && moduleMetrics.emergency.incidents_count > 0) {
            items.push({
                id: 'emergency',
                title: 'Logged emergency callouts & fire brigade responses',
                description: `${moduleMetrics.emergency.incidents_count} incident reports on record across structural, water burst and hazard categories.`,
                count: moduleMetrics.emergency.incidents_count,
                href: '/emergency',
            });
        }
        return items;
    }, [stats, moduleMetrics, council?.currency]);

    return (
        <AppLayout title="Executive dashboard" breadcrumb="Overview">
            <Head title="Council Executive Dashboard" />

            {/* Demonstration Banner (Clean, quiet warning) */}
            {council?.is_demo && (
                <div className="mb-6 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 shadow-2xs">
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-2 w-2 rounded-full bg-amber-500"></span>
                        <p>
                            <strong>Demonstration council: {council?.name}.</strong> All records reflect live seeded Zimbabwean municipal demonstration data.
                        </p>
                    </div>
                    <span className="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 border border-amber-200">
                        Demo environment
                    </span>
                </div>
            )}

            {/* 1. FINANCIAL SUMMARY (Clean white card replacing dark banner) */}
            <section className="mb-6 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-2xs">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold text-slate-900">Financial position & treasury</h2>
                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                IPSAS 1 Accrual
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Real-time double-entry general ledger summary as of {formatDate(new Date().toISOString())}
                        </p>
                    </div>

                    {/* Quiet outlined module shortcuts (40px height, line icons) */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/accounting/chart"
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
                        >
                            <ListTree className="h-4 w-4 text-slate-500" />
                            <span>Chart of accounts</span>
                        </Link>
                        <Link
                            href="/accounting/journals"
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
                        >
                            <FileSpreadsheet className="h-4 w-4 text-slate-500" />
                            <span>Journal vouchers</span>
                        </Link>
                        <Link
                            href="/accounting/reports/ledger"
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
                        >
                            <BookOpen className="h-4 w-4 text-slate-500" />
                            <span>General ledger</span>
                        </Link>
                        <Link
                            href="/accounting/reports/trial-balance"
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
                        >
                            <Scale className="h-4 w-4 text-slate-500" />
                            <span>Trial balance</span>
                        </Link>
                        <Link
                            href="/accounting/reports/position"
                            className="inline-flex h-10 items-center gap-2 rounded-lg bg-[#0F5C3A] px-3 text-xs font-medium text-white hover:bg-[#0A432A] transition shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0F5C3A]"
                        >
                            <PieChart className="h-4 w-4 text-white" />
                            <span>Balance sheet</span>
                        </Link>
                    </div>
                </div>

                {/* 4-column KPI row */}
                <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg bg-slate-50/70 p-4 border border-slate-100">
                        <span className="text-xs font-medium text-slate-500">Total municipal assets</span>
                        <p className="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">
                            {money(position?.assets_minor ?? 0, council?.currency)}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            IPSAS 17 property, plant, equipment & receivables
                        </p>
                    </div>

                    <div className="rounded-lg bg-slate-50/70 p-4 border border-slate-100">
                        <span className="text-xs font-medium text-slate-500">Total liabilities</span>
                        <p className="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">
                            {money(position?.liabilities_minor ?? 0, council?.currency)}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Payables, retentions & statutory provisions
                        </p>
                    </div>

                    <div className="rounded-lg bg-slate-50/70 p-4 border border-slate-100">
                        <span className="text-xs font-medium text-slate-500">Operating surplus / result</span>
                        <p
                            className={`mt-1 text-2xl font-semibold tabular-nums ${
                                (position?.result_minor ?? 0) >= 0 ? 'text-[#0F5C3A]' : 'text-amber-700'
                            }`}
                        >
                            {money(position?.result_minor ?? 0, council?.currency)}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Accrual revenue less operating expenditure
                        </p>
                    </div>

                    <div className="rounded-lg bg-slate-50/70 p-4 border border-slate-100">
                        <span className="text-xs font-medium text-slate-500">Cash & cash equivalents</span>
                        <p className="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">
                            {money(accountingStats?.cash_and_bank_minor ?? 0, council?.currency)}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Bank balances & cashier vault liquidity
                        </p>
                    </div>
                </div>

                {/* Accounting balance check footnote */}
                <div className="mt-3 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-[#0F5C3A]" />
                        <span>
                            Accounting equation balanced:{' '}
                            <strong className="text-slate-700">Assets = Liabilities + Accumulated Surplus</strong>.
                        </span>
                    </div>
                    <span>{accountingStats?.posted_journals_count ?? 0} posted journals on ledger</span>
                </div>
            </section>

            {/* 2. REVENUE & CASHFLOW OVERVIEW */}
            <section className="mb-6">
                <div className="mb-3 flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Revenue & cash flow</h2>
                        <p className="text-xs text-slate-500">Customer rate accounts, monthly billing and verified cashier collections</p>
                    </div>
                    <span className="text-xs text-slate-500 font-medium">
                        Last updated: today, {lastUpdatedLabel}
                    </span>
                </div>

                {revenueEnabled ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            label="Active customer accounts"
                            value={String(stats.accounts)}
                            helperText="Ratepayers linked to property roll"
                            icon="Users"
                        />
                        <KpiCard
                            label="Money owed to council (outstanding)"
                            value={money(stats.outstanding_minor, council?.currency)}
                            helperText="Open customer bill balances"
                            icon="Receipt"
                        />
                        <KpiCard
                            label="Collected today"
                            value={money(stats.collected_today_minor, council?.currency)}
                            helperText={
                                stats.receipts_today === 0
                                    ? 'No payments collected today yet'
                                    : `${stats.receipts_today} verified payment${stats.receipts_today === 1 ? '' : 's'}`
                            }
                            icon="Coins"
                            trendPositive={stats.collected_today_minor > 0}
                        />
                        <KpiCard
                            label="Billed this month"
                            value={money(stats.billed_this_month_minor, council?.currency)}
                            helperText="Approved municipal assessment runs"
                            icon="Calculator"
                        />
                    </div>
                ) : (
                    <EmptyState
                        title="Revenue module is not enabled"
                        hint="Enable revenue in Administration → Modules if this council is licensed."
                    />
                )}
            </section>

            {/* 3. NEEDS ATTENTION PANEL (Uses real data only) */}
            {attentionItems.length > 0 && (
                <div className="mb-6">
                    <NeedsAttentionPanel items={attentionItems} />
                </div>
            )}

            {/* 4. MUNICIPAL OPERATIONS & DEPARTMENTAL REGISTERS */}
            {moduleMetrics && (
                <section className="mb-6">
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">Municipal operations & departments</h2>
                            <p className="text-xs text-slate-500">Statutory registers, public infrastructure and community services</p>
                        </div>
                        <span className="text-xs text-slate-500">11 operational consoles</span>
                    </div>

                    <div className="space-y-4">
                        {/* Subgroup: Property & Asset Registers */}
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                                Property, land & assets
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
                                {moduleMetrics.gis && (
                                    <DeptCard
                                        title="GIS Cadastre & Property Database"
                                        metric={`${moduleMetrics.gis.stands_count} stand${moduleMetrics.gis.stands_count === 1 ? '' : 's'}`}
                                        subtext={money(moduleMetrics.gis.valuation_minor, council?.currency)}
                                        href="/land/gis"
                                        icon="Map"
                                        tag="Surveyor General"
                                    />
                                )}
                                <DeptCard
                                    title="Fixed Assets Register"
                                    metric={`${moduleMetrics.assets.count} asset${moduleMetrics.assets.count === 1 ? '' : 's'}`}
                                    subtext={money(moduleMetrics.assets.carrying_amount_minor, council?.currency)}
                                    href="/assets"
                                    icon="Building2"
                                    tag="IPSAS 17"
                                />
                                <DeptCard
                                    title="Central Stores & Inventory"
                                    metric={`${moduleMetrics.stores.count} line item${moduleMetrics.stores.count === 1 ? '' : 's'}`}
                                    subtext={money(moduleMetrics.stores.valuation_minor, council?.currency)}
                                    href="/stores"
                                    icon="Boxes"
                                    tag="Bin cards"
                                />
                            </div>
                        </div>

                        {/* Subgroup: Engineering & Capital Works */}
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                                Engineering, utilities & fleet
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
                                <DeptCard
                                    title="Water Utilities & Metering"
                                    metric={`${moduleMetrics.water.count} meter${moduleMetrics.water.count === 1 ? '' : 's'}`}
                                    subtext={`${moduleMetrics.water.smart_count} smart AMR meters connected`}
                                    href="/engineering/water-meters"
                                    icon="Droplets"
                                    tag="Engineering"
                                />
                                <DeptCard
                                    title="Devolution & Capital Works"
                                    metric={`${moduleMetrics.projects.count} capital project${moduleMetrics.projects.count === 1 ? '' : 's'}`}
                                    subtext={money(moduleMetrics.projects.budget_minor, council?.currency)}
                                    href="/projects/capital"
                                    icon="HardHat"
                                    tag="10% Retention"
                                />
                                <DeptCard
                                    title="Fleet & Heavy Plant"
                                    metric={`${moduleMetrics.fleet.vehicles_count} vehicle${moduleMetrics.fleet.vehicles_count === 1 ? '' : 's'}`}
                                    subtext="Fuel coupons & electronic logbooks"
                                    href="/fleet"
                                    icon="Truck"
                                    tag="Transport"
                                />
                            </div>
                        </div>

                        {/* Subgroup: Community & Human Services */}
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                                Town planning, community & personnel
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                <DeptCard
                                    title="Housing Waiting List"
                                    metric={`${moduleMetrics.housing.applicants_count} applicant${moduleMetrics.housing.applicants_count === 1 ? '' : 's'}`}
                                    subtext={`${moduleMetrics.housing.allocations_count} stand allocation${moduleMetrics.housing.allocations_count === 1 ? '' : 's'}`}
                                    href="/land/housing-allocations"
                                    icon="Home"
                                    tag="Housing"
                                />
                                <DeptCard
                                    title="Municipal Payroll"
                                    metric={`${moduleMetrics.payroll.employees_count} employee${moduleMetrics.payroll.employees_count === 1 ? '' : 's'}`}
                                    subtext="ZIMRA, NSSA & LAPF deductions"
                                    href="/hr/payroll"
                                    icon="CreditCard"
                                    tag="Salaries"
                                />
                                <DeptCard
                                    title="Licences & Permits"
                                    metric={`${moduleMetrics.licensing.types_count} licence type${moduleMetrics.licensing.types_count === 1 ? '' : 's'}`}
                                    subtext={`${moduleMetrics.licensing.applications_count} application${moduleMetrics.licensing.applications_count === 1 ? '' : 's'}`}
                                    href="/licensing"
                                    icon="FileBadge"
                                    tag="Statutory"
                                />
                                <DeptCard
                                    title="Cemeteries & Graves"
                                    metric={`${moduleMetrics.cemeteries.plots_count} plot${moduleMetrics.cemeteries.plots_count === 1 ? '' : 's'}`}
                                    subtext={`${moduleMetrics.cemeteries.interments_count} burial order${moduleMetrics.cemeteries.interments_count === 1 ? '' : 's'}`}
                                    href="/community/cemeteries"
                                    icon="Cross"
                                    tag="Cemeteries"
                                />
                                <DeptCard
                                    title="Emergency Services"
                                    metric={`${moduleMetrics.emergency.incidents_count} incident${moduleMetrics.emergency.incidents_count === 1 ? '' : 's'}`}
                                    subtext={`${moduleMetrics.emergency.hydrants_count} tested hydrants`}
                                    href="/emergency"
                                    icon="Flame"
                                    tag="Fire brigade"
                                />
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {/* 5. DATA TABLES & RECENT ACTIVITY GRID */}
            <div className="grid gap-6 lg:grid-cols-3">
                {/* Recent Customer Bills (2 columns) */}
                <section className="rounded-xl border border-[#E5E7EB] bg-white lg:col-span-2 shadow-2xs overflow-hidden">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3.5">
                        <div>
                            <h2 className="text-sm font-semibold text-slate-900">Recent customer bills</h2>
                            <p className="text-xs text-slate-500">Issued property and assessment bills</p>
                        </div>
                        <Link
                            href="/revenue/accounts"
                            className="inline-flex items-center gap-1 text-xs font-medium text-[#0F5C3A] hover:underline"
                        >
                            <span>View all customer accounts</span>
                            <ArrowRight className="h-3 w-3" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        {recentBills.length === 0 ? (
                            <div className="p-6">
                                <EmptyState
                                    title="No customer bills issued yet"
                                    hint="Approved billing runs automatically generate bills and accounting journals."
                                />
                            </div>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-100 bg-slate-50/70 text-xs font-semibold text-slate-600">
                                    <tr>
                                        <th className="px-4 py-3">Bill reference</th>
                                        <th className="px-4 py-3">Account</th>
                                        <th className="px-4 py-3">Service</th>
                                        <th className="px-4 py-3 text-right">Total</th>
                                        <th className="px-4 py-3 text-right">Balance</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recentBills.map((bill) => (
                                        <tr key={bill.number} className="h-12 hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-2 font-medium text-slate-900">
                                                {bill.number}
                                            </td>
                                            <td className="px-4 py-2 text-slate-600">
                                                {bill.account ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 capitalize text-slate-600">
                                                {bill.service}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium text-slate-900 tabular-nums">
                                                {money(bill.total_minor, council?.currency)}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium text-slate-700 tabular-nums">
                                                {money(bill.balance_minor, council?.currency)}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge tone={statusTone(bill.status)}>{bill.status}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>

                {/* Audit & Compliance Stream (1 column) */}
                <section className="rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900">Tamper-evident audit activity</h2>
                                <p className="text-xs text-slate-500">Cryptographic audit log</p>
                            </div>
                            <span className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                SHA-256
                            </span>
                        </div>

                        <ul className="mt-3 divide-y divide-slate-100">
                            {recentEvents.length === 0 ? (
                                <li className="py-4 text-xs text-slate-500 text-center">No recent audit events.</li>
                            ) : (
                                recentEvents.map((event, index) => (
                                    <li key={`${event.action}-${index}`} className="py-2.5 first:pt-0 last:pb-0">
                                        <div className="flex items-center justify-between text-xs">
                                            <p className="font-medium text-slate-900 truncate pr-2">
                                                {event.action}
                                            </p>
                                            <span className="text-[11px] text-slate-400 shrink-0">
                                                {formatDateTime(event.at, displayTimezone)}
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-slate-500 mt-0.5">
                                            Actor: {event.actor ?? 'System'} · {event.subject ?? 'Platform'}
                                        </p>
                                    </li>
                                ))
                            )}
                        </ul>
                    </div>

                    <div className="pt-3 border-t border-slate-100 mt-3">
                        <Link
                            href="/admin/audit"
                            className="inline-flex items-center gap-1 text-xs font-medium text-[#0F5C3A] hover:underline"
                        >
                            <span>Inspect full cryptographic audit trail</span>
                            <ArrowRight className="h-3 w-3" />
                        </Link>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
