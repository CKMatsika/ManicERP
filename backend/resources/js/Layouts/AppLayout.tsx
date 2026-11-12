import { Link, router, usePage } from '@inertiajs/react';
import React, { useEffect, useState, type ReactNode } from 'react';
import { can, type SharedProps } from '../lib';
import { Flash } from '../Components/Ui';
import Icon from '../Components/Icon';
import CommandPalette from '../Components/CommandPalette';
import { SIDEBAR_GROUPS, type SidebarItem } from '../Config/sidebarConfig';
import {
    Search,
    Plus,
    Bell,
    HelpCircle,
    ChevronDown,
    ChevronRight,
    Star,
    Menu,
    X,
    LogOut,
    Shield,
    PanelLeftClose,
    PanelLeft,
    Clock,
    LayoutGrid,
} from 'lucide-react';

export default function AppLayout({
    title,
    breadcrumb,
    children,
}: {
    title: string;
    breadcrumb?: string;
    children: ReactNode;
}) {
    const { auth, council, membership, permissions, modules } = usePage<SharedProps>().props;

    // Super administrator check
    const isSuperAdmin = Boolean(auth?.is_platform_admin || permissions.includes('*'));

    // Current path
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '/dashboard';

    // Collapsed rail state (desktop: 256px vs 64px)
    const [isRail, setIsRail] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem('manicerp_sidebar_rail') === 'true';
    });

    // Mobile drawer open
    const [mobileOpen, setMobileOpen] = useState(false);

    // Command palette open
    const [cmdOpen, setCmdOpen] = useState(false);

    // User avatar dropdown open
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const [newMenuOpen, setNewMenuOpen] = useState(false);

    // Help modal open
    const [helpOpen, setHelpOpen] = useState(false);
    const [launcherOpen, setLauncherOpen] = useState(false);

    // Accordion expanded groups state
    const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>(() => {
        try {
            const saved = localStorage.getItem('manicerp_expanded_groups');
            if (saved) return JSON.parse(saved);
        } catch {
            // fallback
        }
        return {};
    });

    // Favourites list stored in localStorage
    const [favourites, setFavourites] = useState<string[]>(() => {
        try {
            const saved = localStorage.getItem('manicerp_favourites');
            if (saved) return JSON.parse(saved);
        } catch {
            // fallback
        }
        return ['/dashboard', '/accounting/reports/ledger', '/revenue/billing', '/land/gis'];
    });

    // Sync rail state
    function toggleRail() {
        setIsRail((prev) => {
            const next = !prev;
            localStorage.setItem('manicerp_sidebar_rail', String(next));
            return next;
        });
    }

    function toggleGroup(groupId: string) {
        setExpandedGroups((prev) => {
            const next = { ...prev, [groupId]: !prev[groupId] };
            try {
                localStorage.setItem('manicerp_expanded_groups', JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    }

    function toggleFavourite(href: string) {
        setFavourites((prev) => {
            const next = prev.includes(href) ? prev.filter((h) => h !== href) : [...prev, href].slice(0, 5);
            try {
                localStorage.setItem('manicerp_favourites', JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    }

    // Filter items based on permissions and module activation
    function isItemVisible(item: SidebarItem): boolean {
        if (isSuperAdmin) return true;
        if (item.module && modules && modules[item.module] === false) return false;
        if (item.permission && !can(permissions, item.permission)) return false;
        if (item.anyPermissions && !item.anyPermissions.some((p) => can(permissions, p))) return false;
        return true;
    }

    // Filtered groups
    const visibleGroups = React.useMemo(() => {
        return SIDEBAR_GROUPS.map((g) => ({
            ...g,
            items: g.items.filter(isItemVisible),
        })).filter((g) => g.items.length > 0);
    }, [isSuperAdmin, modules, permissions]);

    // Active group containing current path
    const activeGroupId = React.useMemo(() => {
        const currentBase = currentPath.split('?')[0];
        const matching = visibleGroups.flatMap((group) => group.items.map((item) => ({ group, item })))
            .filter(({ item }) => {
                const itemBase = item.href.split('?')[0];
                return currentBase === itemBase || (itemBase !== '/' && itemBase !== '/dashboard' && currentBase.startsWith(`${itemBase}/`));
            })
            .sort((a, b) => b.item.href.length - a.item.href.length)[0];
        if (matching) return matching.group.id;
        try {
            const stored = localStorage.getItem('manicerp_active_module');
            if (stored && visibleGroups.some((group) => group.id === stored)) return stored;
        } catch { /* use first available */ }
        return visibleGroups[0]?.id ?? 'overview';
    }, [visibleGroups, currentPath]);

    const activeGroup = visibleGroups.find((group) => group.id === activeGroupId) ?? visibleGroups[0];
    const moduleItems = activeGroup?.items ?? [];
    const commandItems = React.useMemo(() => visibleGroups.flatMap((group) => group.items.map((item) => ({ ...item, groupTitle: group.title }))), [visibleGroups]);

    // Ensure active group is automatically expanded
    useEffect(() => {
        if (activeGroupId) {
            setExpandedGroups((prev) => {
                if (prev[activeGroupId]) return prev;
                const next = { ...prev, [activeGroupId]: true };
                try {
                    localStorage.setItem('manicerp_expanded_groups', JSON.stringify(next));
                } catch {
                    // ignore
                }
                return next;
            });
        }
    }, [activeGroupId]);

    function selectModule(group: (typeof SIDEBAR_GROUPS)[number]) {
        if (!visibleGroups.some((visible) => visible.id === group.id)) return;
        localStorage.setItem('manicerp_active_module', group.id);
        setExpandedGroups((prev) => ({ ...prev, [group.id]: true }));
        setLauncherOpen(false);
        setMobileOpen(false);
        const firstItem = group.items[0];
        if (firstItem) router.visit(firstItem.href);
    }

    const newActions = [
        { label: 'New customer', href: '/revenue/accounts', permissions: ['revenue.account.view', 'revenue.account.manage'], module: 'revenue' },
        { label: 'New bill', href: '/revenue/billing', permissions: ['revenue.tariff.view', 'revenue.billing.preview'], module: 'revenue' },
        { label: 'New receipt', href: '/revenue/cashier', permissions: ['revenue.cashier.open'], module: 'revenue' },
        { label: 'New journal', href: '/accounting/journals', permissions: ['accounting.journal.view', 'accounting.journal.create'], module: 'accounting' },
        { label: 'New purchase order', href: '/procurement', permissions: ['procurement.reports.view', 'procurement.requisition.manage'], module: 'procurement' },
        { label: 'New supplier', href: '/procurement', permissions: ['procurement.reports.view', 'procurement.supplier.manage'], module: 'procurement' },
    ].filter((action) => action.module ? modules[action.module] && action.permissions.every((permission) => can(permissions, permission)) : action.permissions.every((permission) => can(permissions, permission)));

    const hasNotifications = modules.licensing && can(permissions, 'licensing.notifications.view');

    useEffect(() => {
        function handleShellKeys(event: KeyboardEvent) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setCmdOpen((open) => !open);
            }
            if (event.key === 'Escape') {
                setNewMenuOpen(false);
                setUserMenuOpen(false);
            }
        }

        window.addEventListener('keydown', handleShellKeys);
        return () => window.removeEventListener('keydown', handleShellKeys);
    }, []);

    // Date formatted for header
    const todayFormatted = React.useMemo(() => {
        try {
            return new Intl.DateTimeFormat('en-GB', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                timeZone: 'Africa/Harare',
            }).format(new Date());
        } catch {
            return 'Wednesday, 11 November 2026';
        }
    }, []);

    return (
        <div className="min-h-screen bg-canvas text-ink flex flex-col antialiased">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-civic focus:shadow-md focus:outline-none focus:ring-2 focus:ring-civic"
            >
                Skip to main content
            </a>

            {/* Command Palette Modal */}
            <CommandPalette isOpen={cmdOpen} onClose={() => setCmdOpen(false)} items={commandItems} />

            {launcherOpen && <div className="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 p-4 pt-12 sm:pt-20" onMouseDown={(event) => { if (event.target === event.currentTarget) setLauncherOpen(false); }}>
                <section role="dialog" aria-modal="true" aria-labelledby="launcher-title" className="w-full max-w-3xl rounded-card border border-line bg-surface p-5 shadow-xl">
                    <div className="mb-4 flex items-start justify-between"><div><h2 id="launcher-title" className="text-lg font-semibold text-ink">Applications</h2><p className="mt-1 text-sm text-muted">Choose a council workspace.</p></div><button type="button" onClick={() => setLauncherOpen(false)} aria-label="Close app launcher" className="rounded-md p-1.5 text-muted hover:bg-canvas"><X className="h-4 w-4" /></button></div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{visibleGroups.map((group) => <button key={group.id} type="button" onClick={() => selectModule(group)} className={`rounded-card border p-4 text-left transition hover:border-civic hover:bg-civic-soft focus:outline-none focus:ring-2 focus:ring-civic ${activeModule?.id === group.id ? 'border-civic bg-civic-soft' : 'border-line bg-white'}`}><span className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-canvas text-civic"><Icon name={group.icon} className="h-5 w-5" /></span><span className="block text-sm font-semibold text-ink">{group.title}</span><span className="mt-1 block text-xs text-muted">{group.description}</span><span className="mt-3 block text-xs text-subtle">{group.items.length} {group.items.length === 1 ? 'screen' : 'screens'}</span></button>)}</div>
                </section>
            </div>}

            {/* Help modal */}
            {helpOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs" onClick={() => setHelpOpen(false)} />
                    <div className="relative w-full max-w-lg rounded-card border border-line bg-surface p-6 shadow-xl text-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-base font-semibold text-slate-900">ManicERP System Guide & Legal Sources</h3>
                            <button onClick={() => setHelpOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <div className="mt-4 space-y-3 text-slate-600 leading-relaxed">
                            <p>
                                <strong>ManicERP Local Authority Operating System</strong> is grounded in statutory compliance under the Laws of Zimbabwe:
                            </p>
                            <ul className="list-disc pl-5 space-y-1.5">
                                <li><strong>Urban Councils Act [Cap 29:15]</strong> — Property database, Section 279 debt relief, stand allocations, and ratepayer rolls.</li>
                                <li><strong>Public Finance Management Act [Cap 22:19]</strong> — Double-entry accrual accounting and internal control safeguards.</li>
                                <li><strong>IPSAS Standards</strong> — IPSAS 1 (Position), IPSAS 2 (Cash Flows), IPSAS 9 & 23 (Revenue), and IPSAS 17 (Property, Plant & Equipment).</li>
                                <li><strong>Public Procurement and Disposal of Public Assets Act [Cap 22:23] (PRAZ)</strong> — 3-way matching and 10% contractor retention money.</li>
                            </ul>
                            <div className="pt-3 border-t border-line flex justify-between items-center text-xs text-subtle">
                                <span>Version 2026.3 · Accrual IPSAS Edition</span>
                                <button onClick={() => setHelpOpen(false)} className="rounded-md bg-slate-100 px-3 py-1 font-semibold text-slate-700 hover:bg-slate-200">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="flex flex-1 min-h-screen">
                {/* Mobile Drawer Backdrop */}
                {mobileOpen && (
                    <div
                        className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
                        onClick={() => setMobileOpen(false)}
                        aria-hidden="true"
                    />
                )}

                {/* Sidebar Component */}
                <aside
                    className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-line bg-surface transition-all duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen shrink-0 ${
                        mobileOpen ? 'translate-x-0 w-60' : '-translate-x-full lg:translate-x-0'
                    } ${isRail ? 'lg:w-16' : 'lg:w-60'}`}
                >
                    {/* Header: Council Crest & Identity */}
                    <div className="flex h-16 items-center justify-between border-b border-line px-3.5">
                        <Link href="/dashboard" className="flex items-center gap-2.5 min-w-0 group">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-civic text-white shadow-xs">
                                <span className="text-xs font-bold tracking-tight">M</span>
                            </div>
                            {!isRail && (
                                <div className="truncate">
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-sm font-semibold tracking-tight text-slate-900 block truncate">
                                            {council?.name ?? 'ManicERP'}
                                        </span>
                                    </div>
                                    <span className="text-xs font-normal text-subtle block truncate">
                                        {council?.is_demo ? 'Demonstration council' : 'Municipal ERP'}
                                    </span>
                                </div>
                            )}
                        </Link>

                        {/* Rail collapse button (desktop) */}
                        <button
                            type="button"
                            onClick={toggleRail}
                            className="hidden lg:flex p-1.5 text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100 focus:outline-none"
                            title={isRail ? 'Expand sidebar' : 'Collapse to rail'}
                            aria-label={isRail ? 'Expand sidebar' : 'Collapse to rail'}
                        >
                            {isRail ? <PanelLeft className="h-4 w-4" /> : <PanelLeftClose className="h-4 w-4" />}
                        </button>

                        {/* Mobile close button */}
                        <button
                            type="button"
                            onClick={() => setMobileOpen(false)}
                            className="lg:hidden p-1.5 text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100"
                            aria-label="Close navigation"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Search / Jump to bar */}
                    {!isRail && (
                        <div className="p-3 border-b border-slate-100">
                            <button
                                type="button"
                                onClick={() => setCmdOpen(true)}
                                className="flex w-full items-center justify-between rounded-card border border-line bg-canvas px-2.5 py-1.5 text-xs text-muted hover:bg-surface hover:border-slate-300 transition shadow-2xs group"
                            >
                                <span className="flex items-center gap-2">
                                    <Search className="h-3.5 w-3.5 text-slate-400 group-hover:text-slate-600" />
                                    <span>Search or jump to...</span>
                                </span>
                                <kbd className="rounded border border-line bg-surface px-1.5 py-0.5 text-xs text-subtle">
                                    Ctrl K
                                </kbd>
                            </button>
                        </div>
                    )}

                    {/* Navigation: all municipal departments as structured collapsible accordions */}
                    <nav className="flex-1 overflow-y-auto px-2.5 py-3 space-y-3" aria-label="Main navigation">
                        {/* Quick Workspace Switcher Button */}
                        {!isRail && (
                            <button
                                type="button"
                                onClick={() => setLauncherOpen(true)}
                                className="flex w-full items-center justify-between rounded-card border border-line bg-canvas p-2.5 text-left transition hover:bg-surface hover:border-slate-300 group"
                                aria-label="Open council workspace switcher"
                            >
                                <div className="flex items-center gap-2.5 min-w-0">
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-civic text-white">
                                        <Icon name={activeGroup?.icon ?? 'LayoutDashboard'} className="h-4 w-4" />
                                    </span>
                                    <div className="min-w-0 truncate">
                                        <span className="block truncate text-xs font-semibold text-slate-900 group-hover:text-civic transition">
                                            {activeGroup?.title ?? 'Workspace'}
                                        </span>
                                        <span className="block text-[11px] text-muted">
                                            Switch workspace
                                        </span>
                                    </div>
                                </div>
                                <LayoutGrid className="h-4 w-4 shrink-0 text-slate-400 group-hover:text-civic" />
                            </button>
                        )}

                        {/* Rail Mode Launcher */}
                        {isRail && (
                            <button
                                type="button"
                                onClick={() => setLauncherOpen(true)}
                                className="mx-auto flex h-10 w-10 items-center justify-center rounded-lg text-civic hover:bg-canvas"
                                aria-label="Open council workspace switcher"
                                title="Switch workspace"
                            >
                                <LayoutGrid className="h-5 w-5" />
                            </button>
                        )}

                        {/* Favourites Section */}
                        {!isRail && favourites.length > 0 && (
                            <div className="pb-2 border-b border-line/60">
                                <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-subtle">
                                    Favourites
                                </p>
                                <ul className="space-y-0.5">
                                    {favourites.map((href) => {
                                        const found = visibleGroups.flatMap((g) => g.items).find((entry) => entry.href === href);
                                        if (!found) return null;
                                        const currentBase = currentPath.split('?')[0];
                                        const active = currentBase === found.href.split('?')[0];
                                        return (
                                            <li key={`fav-${href}`}>
                                                <Link
                                                    href={found.href}
                                                    aria-current={active ? 'page' : undefined}
                                                    className={`flex items-center gap-2 rounded-md px-2 py-1.5 text-xs transition ${
                                                        active
                                                            ? 'bg-civic-soft font-medium text-civic'
                                                            : 'text-slate-700 hover:bg-canvas hover:text-slate-900'
                                                    }`}
                                                >
                                                    <Icon name={found.icon} className="h-3.5 w-3.5 shrink-0 text-slate-500" />
                                                    <span className="truncate">{found.label}</span>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}

                        {/* Full Department Accordion Groups */}
                        {!isRail && (
                            <div className="space-y-1">
                                {visibleGroups.map((group) => {
                                    const isExpanded = Boolean(expandedGroups[group.id]);
                                    const isCurrentGroup = group.id === activeGroupId;
                                    const currentBase = currentPath.split('?')[0];

                                    return (
                                        <section key={group.id} className="rounded-lg overflow-hidden">
                                            {/* Department Header */}
                                            <button
                                                type="button"
                                                onClick={() => toggleGroup(group.id)}
                                                aria-expanded={isExpanded}
                                                className={`flex w-full items-center justify-between px-2 py-1.5 rounded-md text-xs font-semibold transition ${
                                                    isCurrentGroup
                                                        ? 'bg-slate-100/90 text-slate-900 font-bold'
                                                        : 'text-slate-600 hover:bg-canvas hover:text-slate-900'
                                                }`}
                                            >
                                                <span className="flex items-center gap-2 min-w-0">
                                                    <Icon
                                                        name={group.icon}
                                                        className={`h-4 w-4 shrink-0 ${
                                                            isCurrentGroup ? 'text-civic' : 'text-slate-400'
                                                        }`}
                                                    />
                                                    <span className="truncate">{group.title}</span>
                                                </span>
                                                <span className="flex items-center gap-1 shrink-0 text-slate-400">
                                                    <span className="text-[10px] tabular-nums font-normal px-1 py-0.2 rounded bg-slate-100">
                                                        {group.items.length}
                                                    </span>
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-3.5 w-3.5" />
                                                    ) : (
                                                        <ChevronRight className="h-3.5 w-3.5" />
                                                    )}
                                                </span>
                                            </button>

                                            {/* Department Items */}
                                            {isExpanded && (
                                                <ul className="mt-0.5 space-y-0.5 pl-2 border-l border-slate-200 ml-4 py-0.5">
                                                    {group.items.map((item) => {
                                                        const itemBase = item.href.split('?')[0];
                                                        const isExact = item.href.includes('?') 
                                                            ? (window.location.pathname + window.location.search) === item.href
                                                            : currentBase === itemBase;
                                                        const isParent = !isExact && itemBase !== '/' && itemBase !== '/dashboard' && currentBase.startsWith(`${itemBase}/`);
                                                        const active = isExact || isParent;
                                                        const fav = favourites.includes(item.href);

                                                        return (
                                                            <li key={item.href}>
                                                                <Link
                                                                    href={item.href}
                                                                    aria-current={active ? 'page' : undefined}
                                                                    className={`group flex items-center justify-between rounded-md px-2 py-1.5 text-xs transition ${
                                                                        active
                                                                            ? 'bg-civic-soft font-medium text-civic border-l-2 border-civic -ml-2 pl-2'
                                                                            : 'text-slate-600 hover:bg-canvas hover:text-slate-900'
                                                                    }`}
                                                                >
                                                                    <span className="flex min-w-0 items-center gap-2">
                                                                        <Icon
                                                                            name={item.icon}
                                                                            className={`h-3.5 w-3.5 shrink-0 ${
                                                                                active ? 'text-civic' : 'text-slate-400 group-hover:text-slate-600'
                                                                            }`}
                                                                        />
                                                                        <span className="truncate">{item.label}</span>
                                                                    </span>
                                                                    <span className="flex items-center gap-1 shrink-0">
                                                                        {item.badge && (
                                                                            <span className="rounded bg-canvas px-1 py-0.5 text-[10px] text-muted">
                                                                                {item.badge}
                                                                            </span>
                                                                        )}
                                                                        <button
                                                                            type="button"
                                                                            onClick={(event) => {
                                                                                event.preventDefault();
                                                                                event.stopPropagation();
                                                                                toggleFavourite(item.href);
                                                                            }}
                                                                            aria-label={fav ? 'Remove favourite' : 'Add favourite'}
                                                                            title={fav ? 'Remove favourite' : 'Add favourite'}
                                                                            className={`p-0.5 ${
                                                                                fav
                                                                                    ? 'text-amber-500'
                                                                                    : 'text-slate-300 opacity-0 group-hover:opacity-100'
                                                                            }`}
                                                                        >
                                                                            <Star className={`h-3 w-3 ${fav ? 'fill-amber-400' : ''}`} />
                                                                        </button>
                                                                    </span>
                                                                </Link>
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            )}
                                        </section>
                                    );
                                })}
                            </div>
                        )}

                        {/* Rail Mode Navigation */}
                        {isRail && (
                            <div className="space-y-2 pt-2">
                                {visibleGroups.map((group) => {
                                    const isCurrentGroup = group.id === activeGroupId;
                                    return (
                                        <button
                                            key={group.id}
                                            type="button"
                                            onClick={() => selectModule(group)}
                                            className={`mx-auto flex h-10 w-10 items-center justify-center rounded-lg transition ${
                                                isCurrentGroup
                                                    ? 'bg-civic-soft text-civic ring-1 ring-civic/30 font-bold'
                                                    : 'text-slate-500 hover:bg-canvas hover:text-slate-900'
                                            }`}
                                            title={group.title}
                                            aria-label={group.title}
                                        >
                                            <Icon name={group.icon} className="h-5 w-5" />
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </nav>                    {/* Sidebar Footer: System status & council code */}
                    <div className="border-t border-line p-3 text-xs text-muted flex items-center justify-between">
                        {!isRail ? (
                            <div className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span>Online · {council?.code ?? 'DEMO'}</span>
                            </div>
                        ) : (
                            <span className="h-2 w-2 rounded-full bg-emerald-500 mx-auto" title="Online"></span>
                        )}
                        {!isRail && (
                            <button
                                type="button"
                                onClick={() => setHelpOpen(true)}
                                className="text-slate-400 hover:text-slate-600"
                                title="Help & documentation"
                            >
                                <HelpCircle className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </aside>

                {/* Main Content Column */}
                <div className="flex min-w-0 flex-1 flex-col">
                    {/* Top Bar Shell */}
                    <header className="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 border-b border-line bg-surface px-4 lg:px-6">
                        {/* Left: Mobile hamburger + Breadcrumb & Title */}
                        <div className="flex items-center gap-3 min-w-0">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="lg:hidden p-1.5 text-slate-600 hover:bg-slate-100 rounded-md"
                                aria-label="Open sidebar"
                            >
                                <Menu className="h-5 w-5" />
                            </button>

                            <div className="min-w-0">
                                <div className="flex items-center gap-1.5 text-xs text-muted">
                                    <span>{council?.name ?? 'Council'}</span>
                                    <span>/</span>
                                    <span className="font-medium text-slate-700">{activeGroup?.title ?? 'Executive'}</span>
                                    {breadcrumb && breadcrumb !== activeGroup?.title && (
                                        <>
                                            <span>/</span>
                                            <span>{breadcrumb}</span>
                                        </>
                                    )}
                                </div>
                                <h1 className="text-xl font-semibold tracking-tight text-ink truncate leading-tight">
                                    {title}
                                </h1>
                            </div>
                        </div>

                        {/* Middle: Quick Workspace Switcher Capsule */}
                        <div className="hidden lg:flex items-center">
                            <button
                                type="button"
                                onClick={() => setLauncherOpen(true)}
                                className="flex items-center gap-2 rounded-full border border-line bg-canvas px-3 py-1 text-xs text-slate-700 hover:bg-surface hover:border-slate-300 transition group shadow-2xs"
                                title="Switch council workspace"
                            >
                                <span className="flex h-4 w-4 items-center justify-center rounded text-civic">
                                    <Icon name={activeGroup?.icon ?? 'LayoutDashboard'} className="h-3.5 w-3.5" />
                                </span>
                                <span className="font-semibold text-slate-800">{activeGroup?.title ?? 'Executive overview'}</span>
                                <ChevronDown className="h-3 w-3 text-slate-400 group-hover:text-slate-600" />
                            </button>
                        </div>

                        {/* Right: Date, Search, Notifications, Help, User Avatar */}
                        <div className="flex items-center gap-2.5">
                            {/* Date & Location Capsule */}
                            <span className="hidden xl:inline-flex items-center gap-1.5 rounded-full bg-canvas px-3 py-1 text-xs text-muted font-medium">
                                <Clock className="h-3.5 w-3.5 text-slate-400" />
                                <span>{todayFormatted} · Harare</span>
                            </span>

                            {/* Search Button */}
                            <button
                                type="button"
                                onClick={() => setCmdOpen(true)}
                                className="flex items-center gap-2 rounded-card border border-line bg-surface px-3 py-2 text-xs text-muted hover:bg-canvas transition"
                                title="Open search (Ctrl+K)"
                            >
                                <Search className="h-3.5 w-3.5 text-slate-400" />
                                <span className="hidden sm:inline">Search...</span>
                                <kbd className="hidden sm:inline rounded bg-canvas px-1.5 py-0.5 text-xs text-subtle">
                                    Ctrl K
                                </kbd>
                            </button>

                            {newActions.length > 0 && <div className="relative">
                                <button type="button" onClick={() => setNewMenuOpen((open) => !open)} aria-expanded={newMenuOpen} aria-haspopup="menu" className="inline-flex items-center gap-2 rounded-card bg-civic px-3 py-2 text-xs font-semibold text-white hover:bg-civic-hover focus:outline-none focus:ring-2 focus:ring-civic focus:ring-offset-2">
                                    <Plus className="h-4 w-4" aria-hidden="true" /><span>New</span><ChevronDown className="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                {newMenuOpen && <>
                                    <button type="button" className="fixed inset-0 z-30 cursor-default" aria-label="Close new action menu" onClick={() => setNewMenuOpen(false)} />
                                    <div role="menu" aria-label="Create a new record" className="absolute right-0 z-40 mt-2 w-56 rounded-card border border-line bg-surface p-1.5 shadow-lg">
                                        {newActions.map((action) => <Link key={action.label} role="menuitem" href={action.href} onClick={() => setNewMenuOpen(false)} className="block rounded-md px-3 py-2 text-sm text-ink hover:bg-canvas focus:bg-canvas focus:outline-none">{action.label}</Link>)}
                                    </div>
                                </>}
                            </div>}

                            {hasNotifications && <Link href="/licensing/notifications" aria-label="Notifications" title="Notifications" className="relative rounded-card p-2 text-muted hover:bg-canvas focus:outline-none focus:ring-2 focus:ring-civic">
                                <Bell className="h-4 w-4" aria-hidden="true" />
                            </Link>}

                            {/* Help button */}
                            <button
                                type="button"
                                onClick={() => setHelpOpen(true)}
                                className="p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                                title="System guide & statutory acts"
                                aria-label="System help"
                            >
                                <HelpCircle className="h-4 w-4" />
                            </button>

                            {/* User Avatar Menu Dropdown */}
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => setUserMenuOpen((prev) => !prev)}
                                    className="flex items-center gap-2 rounded-card p-1 hover:bg-canvas transition focus:outline-none focus:ring-2 focus:ring-civic"
                                    aria-expanded={userMenuOpen}
                                    aria-haspopup="true"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-civic text-xs font-semibold text-white">
                                        {auth?.name ? auth.name.charAt(0).toUpperCase() : 'U'}
                                    </div>
                                    <div className="hidden md:block text-left text-xs leading-tight">
                                        <p className="font-semibold text-slate-900 truncate max-w-[120px]">{auth?.name}</p>
                                        <p className="text-xs text-muted truncate max-w-[120px]">
                                            {membership?.role_name ?? membership?.role ?? 'Council Officer'}
                                        </p>
                                    </div>
                                    <ChevronDown className="hidden md:block h-3.5 w-3.5 text-slate-400" />
                                </button>

                                {/* Dropdown Menu */}
                                {userMenuOpen && (
                                    <>
                                        <div
                                            className="fixed inset-0 z-30"
                                            onClick={() => setUserMenuOpen(false)}
                                            aria-hidden="true"
                                        />
                                        <div
                                            className="absolute right-0 mt-2 z-40 w-56 rounded-card border border-line bg-surface py-1.5 shadow-lg text-xs"
                                            role="menu"
                                        >
                                            <div className="border-b border-slate-100 px-3.5 py-2">
                                                <p className="font-semibold text-slate-900">{auth?.name}</p>
                                                <p className="text-xs text-muted truncate">{auth?.email}</p>
                                                <div className="mt-1.5 flex items-center gap-1">
                                                    <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                                        {membership?.role_name ?? 'Super Administrator'}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="py-1">
                                                <Link
                                                    href="/security/mfa/setup"
                                                    onClick={() => setUserMenuOpen(false)}
                                                    className="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50"
                                                >
                                                    <Shield className="h-3.5 w-3.5 text-slate-400" />
                                                    <span>Two-Factor Authentication (MFA)</span>
                                                </Link>
                                                <Link
                                                    href="/admin/audit"
                                                    onClick={() => setUserMenuOpen(false)}
                                                    className="flex items-center gap-2 px-3.5 py-2 text-slate-700 hover:bg-slate-50"
                                                >
                                                    <Clock className="h-3.5 w-3.5 text-slate-400" />
                                                    <span>Security & Audit Trail</span>
                                                </Link>
                                            </div>

                                            <div className="border-t border-slate-100 pt-1">
                                                <form method="post" action="/logout">
                                                    <button
                                                        type="submit"
                                                        className="flex w-full items-center gap-2 px-3.5 py-2 text-rose-600 hover:bg-rose-50 font-medium text-left"
                                                    >
                                                        <LogOut className="h-3.5 w-3.5" />
                                                        <span>Sign out</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </header>

                    {/* Main Content Area */}
                    <main id="main-content" className="flex-1 px-4 py-6 sm:px-6 lg:px-8 max-w-[1440px] w-full mx-auto">
                        <Flash />
                        {children}
                    </main>

                    {/* Footer note */}
                    <footer className="border-t border-slate-200 bg-white py-3 px-6 text-center text-xs text-slate-500">
                        <span>ManicERP Municipal Enterprise Resource Planning · Urban Councils Act [Cap 29:15] Compliance · Republic of Zimbabwe</span>
                    </footer>
                </div>
            </div>
        </div>
    );
}
