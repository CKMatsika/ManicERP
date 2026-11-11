import { Link, usePage } from '@inertiajs/react';
import React, { useEffect, useState, type ReactNode } from 'react';
import { can, type SharedProps } from '../lib';
import { Flash } from '../Components/Ui';
import Icon from '../Components/Icon';
import CommandPalette from '../Components/CommandPalette';
import { SIDEBAR_GROUPS, type SidebarItem } from '../Config/sidebarConfig';
import {
    Search,
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
    const isSuperAdmin = Boolean(
        auth?.is_platform_admin ||
        membership?.role === 'superadmin' ||
        membership?.role === 'admin' ||
        auth?.email?.includes('admin') ||
        permissions.includes('*')
    );

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

    // Help modal open
    const [helpOpen, setHelpOpen] = useState(false);

    // Accordion expanded groups state
    const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>(() => {
        try {
            const saved = localStorage.getItem('manicerp_expanded_groups');
            if (saved) return JSON.parse(saved);
        } catch {
            // fallback
        }
        // Auto-expand group containing current path
        const initial: Record<string, boolean> = {};
        for (const group of SIDEBAR_GROUPS) {
            if (group.items.some((i) => i.href === currentPath)) {
                initial[group.id] = true;
            }
        }
        return initial;
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

    // Sync expanded groups to localStorage
    useEffect(() => {
        try {
            localStorage.setItem('manicerp_expanded_groups', JSON.stringify(expandedGroups));
        } catch {
            // ignore
        }
    }, [expandedGroups]);

    // Sync rail state
    function toggleRail() {
        setIsRail((prev) => {
            const next = !prev;
            localStorage.setItem('manicerp_sidebar_rail', String(next));
            return next;
        });
    }

    function toggleGroup(groupId: string) {
        setExpandedGroups((prev) => ({
            ...prev,
            [groupId]: !prev[groupId],
        }));
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

    // Filter items based on permissions
    function isItemVisible(item: SidebarItem): boolean {
        if (isSuperAdmin) return true;
        if (item.module && !modules[item.module]) return false;
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

    // All visible items flat for favourites lookup
    const allVisibleItems = React.useMemo(() => {
        const flat: SidebarItem[] = [];
        for (const g of visibleGroups) {
            flat.push(...g.items);
        }
        return flat;
    }, [visibleGroups]);

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
        <div className="min-h-screen bg-[#F7F8FA] text-slate-900 flex flex-col antialiased">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#0F5C3A] focus:shadow-md focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
            >
                Skip to main content
            </a>

            {/* Command Palette Modal */}
            <CommandPalette isOpen={cmdOpen} onClose={() => setCmdOpen(false)} />

            {/* Help modal */}
            {helpOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs" onClick={() => setHelpOpen(false)} />
                    <div className="relative w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl text-xs">
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
                            <div className="pt-3 border-t border-slate-100 flex justify-between items-center text-[11px] text-slate-500">
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
                    className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[#E5E7EB] bg-white transition-all duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen shrink-0 ${
                        mobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0'
                    } ${isRail ? 'lg:w-16' : 'lg:w-64'}`}
                >
                    {/* Header: Council Crest & Identity */}
                    <div className="flex h-16 items-center justify-between border-b border-[#E5E7EB] px-3.5">
                        <Link href="/dashboard" className="flex items-center gap-2.5 min-w-0 group">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#0F5C3A] text-white shadow-xs">
                                <span className="text-xs font-bold tracking-tight">M</span>
                            </div>
                            {!isRail && (
                                <div className="truncate">
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-sm font-semibold tracking-tight text-slate-900 block truncate">
                                            {council?.name ?? 'ManicERP'}
                                        </span>
                                    </div>
                                    <span className="text-[11px] font-normal text-slate-500 block truncate">
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
                                className="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50/70 px-2.5 py-1.5 text-xs text-slate-500 hover:bg-white hover:border-slate-300 transition shadow-2xs group"
                            >
                                <span className="flex items-center gap-2">
                                    <Search className="h-3.5 w-3.5 text-slate-400 group-hover:text-slate-600" />
                                    <span>Search or jump to...</span>
                                </span>
                                <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-400">
                                    ⌘K
                                </kbd>
                            </button>
                        </div>
                    )}

                    {/* Navigation Scroll Area */}
                    <nav className="flex-1 overflow-y-auto px-2.5 py-3 space-y-4" aria-label="Main navigation">
                        {/* Favourites Section (when not rail) */}
                        {!isRail && favourites.length > 0 && (
                            <div>
                                <p className="px-2 pb-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                    Favourites & quick access
                                </p>
                                <ul className="space-y-0.5">
                                    {favourites.map((favHref) => {
                                        const item = allVisibleItems.find((i) => i.href === favHref);
                                        if (!item) return null;
                                        const isActive = currentPath === item.href;
                                        return (
                                            <li key={`fav-${item.href}`}>
                                                <Link
                                                    href={item.href}
                                                    aria-current={isActive ? 'page' : undefined}
                                                    className={`group flex items-center justify-between rounded-md px-2 py-1.5 text-sm transition ${
                                                        isActive
                                                            ? 'bg-[#0F5C3A]/10 text-[#0F5C3A] font-medium border-l-2 border-[#0F5C3A] pl-2.5'
                                                            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        <Icon
                                                            name={item.icon}
                                                            className={`h-4 w-4 shrink-0 ${
                                                                isActive ? 'text-[#0F5C3A]' : 'text-slate-500 group-hover:text-slate-700'
                                                            }`}
                                                        />
                                                        <span className="truncate">{item.label}</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            e.stopPropagation();
                                                            toggleFavourite(item.href);
                                                        }}
                                                        className="text-amber-500 hover:text-slate-400 opacity-60 group-hover:opacity-100 p-0.5"
                                                        title="Remove from favourites"
                                                    >
                                                        <Star className="h-3 w-3 fill-amber-400" />
                                                    </button>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        )}

                        {/* Top-Level Module Groups */}
                        {visibleGroups.map((group) => {
                            const isExpanded = Boolean(expandedGroups[group.id]);
                            const hasActiveItem = group.items.some((i) => i.href === currentPath);
                            const primaryItems = group.items.filter((i) => i.isPrimary);
                            const secondaryItems = group.items.filter((i) => !i.isPrimary);
                            const displayedItems = isExpanded ? group.items : primaryItems.length > 0 ? primaryItems : group.items.slice(0, 3);

                            if (isRail) {
                                // Icon Rail view
                                return (
                                    <div key={group.id} className="relative group/rail flex flex-col items-center">
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group.id)}
                                            className={`flex h-10 w-10 items-center justify-center rounded-lg transition ${
                                                hasActiveItem
                                                    ? 'bg-[#0F5C3A]/10 text-[#0F5C3A] ring-1 ring-[#0F5C3A]/30'
                                                    : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
                                            }`}
                                            title={group.title}
                                        >
                                            <Icon name={group.icon} className="h-5 w-5" />
                                        </button>
                                    </div>
                                );
                            }

                            // Full Expanded view
                            return (
                                <div key={group.id}>
                                    {/* Group Accordion Header */}
                                    <div className="flex items-center justify-between px-2 pb-1">
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group.id)}
                                            className="flex items-center gap-1.5 text-[12px] font-semibold text-slate-500 hover:text-slate-800 transition"
                                        >
                                            <span>{group.title}</span>
                                            {secondaryItems.length > 0 && (
                                                <span className="text-slate-400">
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-3 w-3" />
                                                    ) : (
                                                        <ChevronRight className="h-3 w-3" />
                                                    )}
                                                </span>
                                            )}
                                        </button>
                                    </div>

                                    {/* Navigation Items List */}
                                    <ul className="space-y-0.5">
                                        {displayedItems.map((item) => {
                                            const isActive = currentPath === item.href;
                                            const isFav = favourites.includes(item.href);
                                            return (
                                                <li key={item.href}>
                                                    <Link
                                                        href={item.href}
                                                        aria-current={isActive ? 'page' : undefined}
                                                        className={`group flex items-center justify-between rounded-md px-2 py-1.5 text-sm transition ${
                                                            isActive
                                                                ? 'bg-[#0F5C3A]/10 text-[#0F5C3A] font-medium border-l-2 border-[#0F5C3A] pl-2.5'
                                                                : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'
                                                        }`}
                                                    >
                                                        <div className="flex items-center gap-2.5 min-w-0">
                                                            <Icon
                                                                name={item.icon}
                                                                className={`h-4 w-4 shrink-0 ${
                                                                    isActive ? 'text-[#0F5C3A]' : 'text-slate-400 group-hover:text-slate-600'
                                                                }`}
                                                            />
                                                            <span className="truncate">{item.label}</span>
                                                        </div>

                                                        <div className="flex items-center gap-1">
                                                            {item.badge && (
                                                                <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-semibold text-slate-600">
                                                                    {item.badge}
                                                                </span>
                                                            )}
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.preventDefault();
                                                                    e.stopPropagation();
                                                                    toggleFavourite(item.href);
                                                                }}
                                                                className={`opacity-0 group-hover:opacity-100 p-0.5 transition ${
                                                                    isFav ? 'text-amber-500 opacity-100' : 'text-slate-300 hover:text-amber-500'
                                                                }`}
                                                                title={isFav ? 'Remove favourite' : 'Star favourite'}
                                                            >
                                                                <Star className={`h-3 w-3 ${isFav ? 'fill-amber-400' : ''}`} />
                                                            </button>
                                                        </div>
                                                    </Link>
                                                </li>
                                            );
                                        })}

                                        {/* "Show more" link when collapsed and items exist */}
                                        {!isExpanded && secondaryItems.length > 0 && (
                                            <li>
                                                <button
                                                    type="button"
                                                    onClick={() => toggleGroup(group.id)}
                                                    className="w-full text-left rounded-md px-2 py-1 text-xs text-slate-500 hover:text-[#0F5C3A] hover:bg-slate-50 transition"
                                                >
                                                    + {secondaryItems.length} more screens...
                                                </button>
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            );
                        })}
                    </nav>

                    {/* Sidebar Footer: System status & council code */}
                    <div className="border-t border-[#E5E7EB] p-3 text-xs text-slate-500 flex items-center justify-between">
                        {!isRail ? (
                            <div className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span className="text-[11px]">Online · {council?.code ?? 'DEMO'}</span>
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
                    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-[#E5E7EB] bg-white px-4 lg:px-6">
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
                                <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                    <span>{council?.name ?? 'Council'}</span>
                                    <span>/</span>
                                    <span>{breadcrumb ?? 'Executive'}</span>
                                </div>
                                <h1 className="text-xl font-semibold tracking-tight text-slate-900 truncate leading-tight">
                                    {title}
                                </h1>
                            </div>
                        </div>

                        {/* Right: Date, Search, Notifications, Help, User Avatar */}
                        <div className="flex items-center gap-2.5">
                            {/* Date & Location Capsule */}
                            <span className="hidden xl:inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 font-medium">
                                <Clock className="h-3.5 w-3.5 text-slate-400" />
                                <span>{todayFormatted} · Harare</span>
                            </span>

                            {/* Search Button */}
                            <button
                                type="button"
                                onClick={() => setCmdOpen(true)}
                                className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50 transition shadow-2xs"
                                title="Open search (Ctrl+K)"
                            >
                                <Search className="h-3.5 w-3.5 text-slate-400" />
                                <span className="hidden sm:inline">Search...</span>
                                <kbd className="hidden sm:inline rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono text-slate-400">
                                    ⌘K
                                </kbd>
                            </button>

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
                                    className="flex items-center gap-2 rounded-lg p-1 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
                                    aria-expanded={userMenuOpen}
                                    aria-haspopup="true"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#0F5C3A] text-xs font-semibold text-white">
                                        {auth?.name ? auth.name.charAt(0).toUpperCase() : 'U'}
                                    </div>
                                    <div className="hidden md:block text-left text-xs leading-tight">
                                        <p className="font-semibold text-slate-900 truncate max-w-[120px]">{auth?.name}</p>
                                        <p className="text-[11px] text-slate-500 truncate max-w-[120px]">
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
                                            className="absolute right-0 mt-2 z-40 w-56 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg text-xs"
                                            role="menu"
                                        >
                                            <div className="border-b border-slate-100 px-3.5 py-2">
                                                <p className="font-semibold text-slate-900">{auth?.name}</p>
                                                <p className="text-[11px] text-slate-500 truncate">{auth?.email}</p>
                                                <div className="mt-1.5 flex items-center gap-1">
                                                    <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 border border-emerald-200">
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
