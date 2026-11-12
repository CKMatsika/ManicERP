import { Head, Link, usePage } from '@inertiajs/react';
import React, { useMemo } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../Components/Ui';
import { can, formatDateTime, money, type SharedProps } from '../lib';
import { KpiCard, DeptCard, NeedsAttentionPanel } from '../Components/DashboardComponents';
import Icon from '../Components/Icon';
import {
    ArrowRight,
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
        outstanding_bills: number;
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
    const { council, displayTimezone, permissions, auth } = usePage<SharedProps>().props;
    const allowed = (permission: string) => Boolean(auth?.is_platform_admin) || can(permissions, permission);

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
                title: 'Customer bills with an outstanding balance',
                description: `${money(stats.outstanding_minor, council?.currency)} remains across open bills for review and follow-up.`,
                count: stats.outstanding_bills,
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
        return items;
    }, [stats, moduleMetrics, council?.currency]);

    return (
        <AppLayout title="Executive dashboard" breadcrumb="Overview">
            <Head title="Council Executive Dashboard" />

            {council?.is_demo && <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-card border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><p><strong>Demonstration council: {council.name}.</strong> Figures use seeded municipal demonstration records.</p><span className="rounded-md bg-amber-100 px-2 py-1 text-xs font-semibold">Demo environment</span></div>}

            <section aria-labelledby="dashboard-summary-title" className="mb-6">
                <div className="mb-3 flex flex-wrap items-end justify-between gap-2"><div><h2 id="dashboard-summary-title" className="text-base font-semibold text-ink">Council at a glance</h2><p className="mt-1 text-sm text-muted">Key revenue and cash-flow figures · Updated {lastUpdatedLabel} Harare time</p></div></div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {revenueEnabled ? <>
                        <KpiCard label="Active customer accounts" value={String(stats.accounts)} helperText="Active accounts on the municipal roll" icon="Users" />
                        <KpiCard label="Outstanding customer bills" value={money(stats.outstanding_minor, council?.currency)} helperText={`${stats.outstanding_bills} bills with a balance`} icon="Receipt" />
                        <KpiCard label="Collected today" value={money(stats.collected_today_minor, council?.currency)} helperText={`${stats.receipts_today} verified receipt${stats.receipts_today === 1 ? '' : 's'} today`} icon="Coins" trendPositive={stats.collected_today_minor > 0} />
                        <KpiCard label="Billed this month" value={money(stats.billed_this_month_minor, council?.currency)} helperText="Bills issued this calendar month" icon="Calculator" />
                    </> : accountingEnabled ? <>
                        <KpiCard label="General ledger accounts" value={String(accountingStats?.gl_accounts_count ?? 0)} helperText="Configured chart of accounts" icon="ListTree" />
                        <KpiCard label="Municipal assets" value={money(position?.assets_minor, council?.currency)} helperText="Reported assets at current period" icon="Building2" />
                        <KpiCard label="Municipal liabilities" value={money(position?.liabilities_minor, council?.currency)} helperText="Reported obligations at current period" icon="Scale" />
                        <KpiCard label="Cash and bank" value={money(accountingStats?.cash_and_bank_minor, council?.currency)} helperText={`${accountingStats?.posted_journals_count ?? 0} posted journals`} icon="Landmark" />
                    </> : <div className="sm:col-span-2 xl:col-span-4 rounded-card border border-line bg-white p-5 text-sm text-muted">Enable a council module to show its operational summary here.</div>}
                </div>
            </section>

            <div className="mb-6 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
                <NeedsAttentionPanel items={attentionItems} />
                <section aria-labelledby="quick-actions-title" className="rounded-card border border-line bg-white p-4">
                    <div className="border-b border-line pb-3"><h2 id="quick-actions-title" className="text-sm font-semibold text-ink">Quick actions</h2><p className="mt-1 text-xs text-muted">Go directly to common council tasks.</p></div>
                    <div className="mt-2 divide-y divide-slate-100">
                        {[
                            ...(revenueEnabled && allowed('revenue.account.view') ? [{ label: 'Customer accounts', href: '/revenue/accounts', icon: 'Users' }] : []),
                            ...(revenueEnabled && allowed('revenue.tariff.view') ? [{ label: 'Billing runs', href: '/revenue/billing', icon: 'Calculator' }] : []),
                            ...(revenueEnabled && allowed('revenue.cashier.open') ? [{ label: 'Cashier and receipts', href: '/revenue/cashier', icon: 'Coins' }] : []),
                            ...(accountingEnabled && allowed('accounting.journal.view') ? [{ label: 'Journal vouchers', href: '/accounting/journals', icon: 'FileSpreadsheet' }] : []),
                            ...(accountingEnabled && allowed('accounting.reports.view') ? [{ label: 'General ledger', href: '/accounting/reports/ledger', icon: 'BookOpen' }] : []),
                            ...(allowed('procurement.reports.view') ? [{ label: 'Procurement', href: '/procurement', icon: 'ShoppingBag' }] : []),
                        ].map((action) => <Link key={action.href} href={action.href} className="group flex items-center justify-between py-2.5 text-sm text-slate-700 hover:text-civic"><span className="flex items-center gap-2.5"><Icon name={action.icon} className="h-4 w-4 text-slate-500 group-hover:text-civic" />{action.label}</span><ArrowRight className="h-4 w-4 text-slate-400 group-hover:text-civic" /></Link>)}
                        {!(revenueEnabled && (allowed('revenue.account.view') || allowed('revenue.tariff.view') || allowed('revenue.cashier.open')) || accountingEnabled && (allowed('accounting.journal.view') || allowed('accounting.reports.view')) || allowed('procurement.reports.view')) && <p className="py-3 text-sm text-muted">No quick actions are available for your role.</p>}
                    </div>
                </section>
            </div>
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
