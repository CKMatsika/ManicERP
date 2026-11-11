import React from 'react';
import { Link } from '@inertiajs/react';
import Icon from './Icon';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface PageHeaderProps {
    title: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    primaryAction?: {
        label: string;
        href?: string;
        onClick?: () => void;
        icon?: string;
        disabled?: boolean;
    };
    secondaryActions?: Array<{
        label: string;
        href?: string;
        onClick?: () => void;
        icon?: string;
        disabled?: boolean;
    }>;
    badge?: React.ReactNode;
    children?: React.ReactNode;
}

export default function PageHeader({
    title,
    description,
    breadcrumbs,
    primaryAction,
    secondaryActions,
    badge,
    children,
}: PageHeaderProps) {
    return (
        <div className="mb-6">
            {/* Breadcrumb line */}
            {breadcrumbs && breadcrumbs.length > 0 && (
                <nav className="mb-2 flex items-center gap-1.5 text-xs text-slate-500" aria-label="Breadcrumb">
                    <Link href="/dashboard" className="transition-colors hover:text-slate-800">
                        Home
                    </Link>
                    {breadcrumbs.map((crumb, idx) => (
                        <React.Fragment key={idx}>
                            <span className="text-slate-400">/</span>
                            {crumb.href ? (
                                <Link href={crumb.href} className="transition-colors hover:text-slate-800">
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span className="font-medium text-slate-700">{crumb.label}</span>
                            )}
                        </React.Fragment>
                    ))}
                </nav>
            )}

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
                        {badge}
                    </div>
                    {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
                </div>

                {/* Action group */}
                <div className="flex flex-wrap items-center gap-2.5">
                    {secondaryActions?.map((act, i) =>
                        act.href ? (
                            <Link
                                key={i}
                                href={act.href}
                                className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700"
                            >
                                {act.icon && <Icon name={act.icon} className="h-4 w-4 text-slate-500" />}
                                <span>{act.label}</span>
                            </Link>
                        ) : (
                            <button
                                key={i}
                                type="button"
                                onClick={act.onClick}
                                disabled={act.disabled}
                                className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 disabled:opacity-50"
                            >
                                {act.icon && <Icon name={act.icon} className="h-4 w-4 text-slate-500" />}
                                <span>{act.label}</span>
                            </button>
                        ),
                    )}

                    {primaryAction &&
                        (primaryAction.href ? (
                            <Link
                                href={primaryAction.href}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white transition-colors hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2"
                            >
                                {primaryAction.icon && <Icon name={primaryAction.icon} className="h-4 w-4 text-emerald-100" />}
                                <span>{primaryAction.label}</span>
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={primaryAction.onClick}
                                disabled={primaryAction.disabled}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-medium text-white transition-colors hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:opacity-50"
                            >
                                {primaryAction.icon && <Icon name={primaryAction.icon} className="h-4 w-4 text-emerald-100" />}
                                <span>{primaryAction.label}</span>
                            </button>
                        ))}

                    {children}
                </div>
            </div>
        </div>
    );
}

