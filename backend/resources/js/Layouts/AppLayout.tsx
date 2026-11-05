import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { can, type SharedProps } from '../lib';
import { Flash } from '../Components/Ui';

type NavItem = { label: string; href: string; permission?: string; module?: string };

const GROUPS: { title: string; items: NavItem[] }[] = [
    {
        title: 'Operations',
        items: [
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Customer accounts', href: '/revenue/accounts', permission: 'revenue.account.view', module: 'revenue' },
            { label: 'Billing runs', href: '/revenue/billing', permission: 'revenue.tariff.view', module: 'revenue' },
            { label: 'Cashier', href: '/revenue/cashier', permission: 'revenue.cashier.open', module: 'revenue' },
            { label: 'Bank reconciliation', href: '/revenue/bank', permission: 'revenue.reconciliation.manage', module: 'revenue' },
            { label: 'Policy assistant', href: '/ai/assistant', permission: 'ai.assistant.use', module: 'ai' },
        ],
    },
    {
        title: 'Accounting',
        items: [
            { label: 'Chart of accounts', href: '/accounting/chart', permission: 'accounting.chart.view', module: 'accounting' },
            { label: 'Journals', href: '/accounting/journals', permission: 'accounting.journal.view', module: 'accounting' },
            { label: 'Trial balance', href: '/accounting/reports/trial-balance', permission: 'accounting.reports.view', module: 'accounting' },
            { label: 'Financial position', href: '/accounting/reports/position', permission: 'accounting.reports.view', module: 'accounting' },
            { label: 'Financial performance', href: '/accounting/reports/performance', permission: 'accounting.reports.view', module: 'accounting' },
            { label: 'Standards register', href: '/accounting/standards', permission: 'accounting.chart.view', module: 'accounting' },
        ],
    },
    {
        title: 'Administration',
        items: [
            { label: 'Modules', href: '/admin/modules', permission: 'platform.modules.manage' },
            { label: 'Audit trail', href: '/admin/audit', permission: 'platform.audit.view' },
        ],
    },
];

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth, council, membership, permissions, modules, displayTimezone } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen lg:flex">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">
                Skip to content
            </a>

            <aside className="border-b border-slate-200 bg-white lg:w-64 lg:shrink-0 lg:border-b-0 lg:border-r">
                <div className="flex items-center justify-between px-4 py-3">
                    <div>
                        <p className="text-sm font-semibold tracking-tight text-teal-800">ManicERP</p>
                        <p className="text-xs text-slate-500">{council?.name ?? 'No council'}</p>
                    </div>
                    {council?.is_demo && (
                        <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-300">
                            Demo
                        </span>
                    )}
                </div>

                <nav className="flex gap-1 overflow-x-auto px-2 pb-2 lg:block lg:space-y-4 lg:overflow-visible lg:px-3 lg:pb-4" aria-label="Primary">
                    {GROUPS.map((group) => {
                        const items = group.items.filter((item) => {
                            if (item.module && !modules[item.module]) return false;
                            if (item.permission && !can(permissions, item.permission)) return false;
                            return true;
                        });

                        if (items.length === 0) return null;

                        return (
                            <div key={group.title}>
                                <p className="hidden px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 lg:block">{group.title}</p>
                                <ul className="flex gap-1 lg:block lg:space-y-0.5">
                                    {items.map((item) => (
                                        <li key={item.href}>
                                            <Link
                                                href={item.href}
                                                className="block whitespace-nowrap rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
                                            >
                                                {item.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
                        <p className="text-xs text-slate-500">
                            {membership?.role_name ?? membership?.role ?? 'Member'} · displayed in {displayTimezone}
                        </p>
                    </div>
                    <div className="flex items-center gap-3 text-sm">
                        {membership?.requires_mfa && !auth?.mfa_enabled && (
                            <Link href="/security/mfa/setup" className="rounded bg-amber-500 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-600">
                                Set up MFA
                            </Link>
                        )}
                        <span className="hidden text-slate-600 sm:inline">{auth?.name}</span>
                        <form method="post" action="/logout">
                            <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''} />
                            <button type="submit" className="rounded border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-50">
                                Sign out
                            </button>
                        </form>
                    </div>
                </header>

                <main id="main" className="min-w-0 flex-1 px-4 py-5">
                    <Flash />
                    {children}
                </main>
            </div>
        </div>
    );
}
