import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight, ArrowUpRight, AlertCircle, ArrowDownRight, CheckCircle2 } from 'lucide-react';
import Icon from './Icon';

export function KpiCard({
    label,
    value,
    helperText,
    icon,
    trend,
    trendPositive,
    badge,
}: {
    label: string;
    value: string;
    helperText: string;
    icon?: string;
    trend?: string;
    trendPositive?: boolean;
    badge?: string;
}) {
    return (
        <div className="rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-2xs hover:border-slate-300 transition duration-150 flex flex-col justify-between">
            <div className="flex items-start justify-between gap-2">
                <span className="text-xs font-medium text-slate-500 leading-snug">{label}</span>
                {icon && (
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-600 border border-slate-100">
                        <Icon name={icon} className="h-4 w-4 text-slate-600" />
                    </div>
                )}
                {badge && (
                    <span className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                        {badge}
                    </span>
                )}
            </div>

            <div className="mt-2">
                <p className="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">
                    {value}
                </p>
                <div className="mt-1 flex items-center justify-between text-xs text-slate-500">
                    <span className="truncate">{helperText}</span>
                    {trend && (
                        <span
                            className={`inline-flex items-center gap-0.5 text-xs font-medium shrink-0 ml-2 ${
                                trendPositive ? 'text-emerald-700' : 'text-slate-600'
                            }`}
                        >
                            {trendPositive ? (
                                <ArrowUpRight className="h-3.5 w-3.5" />
                            ) : (
                                <ArrowDownRight className="h-3.5 w-3.5" />
                            )}
                            {trend}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

export function DeptCard({
    title,
    metric,
    subtext,
    href,
    icon,
    tag,
}: {
    title: string;
    metric: string;
    subtext: string;
    href: string;
    icon: string;
    tag?: string;
}) {
    return (
        <Link
            href={href}
            className="group flex flex-col justify-between rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-2xs transition duration-150 hover:border-slate-300 hover:shadow-xs focus:outline-none focus:ring-2 focus:ring-[#0F5C3A]"
        >
            <div>
                {/* Header: 36px Icon + Dept name + Chevron */}
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5 min-w-0">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#0F5C3A]/8 text-[#0F5C3A] border border-[#0F5C3A]/15 group-hover:bg-[#0F5C3A]/12 transition">
                            <Icon name={icon} className="h-4 w-4 text-[#0F5C3A]" />
                        </div>
                        <span className="text-sm font-medium text-slate-900 truncate group-hover:text-[#0F5C3A] transition">
                            {title}
                        </span>
                    </div>
                    <ChevronRight className="h-4 w-4 text-slate-400 group-hover:text-[#0F5C3A] group-hover:translate-x-0.5 transition shrink-0" />
                </div>

                {/* Big Metric Value */}
                <p className="mt-3 text-xl font-semibold tracking-tight text-slate-900 tabular-nums">
                    {metric}
                </p>
            </div>

            {/* Subtext and optional statutory tag */}
            <div className="mt-2 flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-50">
                <span className="truncate">{subtext}</span>
                {tag && (
                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 shrink-0 ml-1">
                        {tag}
                    </span>
                )}
            </div>
        </Link>
    );
}

export function NeedsAttentionPanel({
    items,
}: {
    items: {
        id: string;
        title: string;
        description: string;
        count: number;
        href: string;
        severity?: 'warning' | 'info';
    }[];
}) {
    return (
        <section className="rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-2xs">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2">
                    {items.length > 0 ? <AlertCircle className="h-4 w-4 text-amber-600 shrink-0" /> : <CheckCircle2 className="h-4 w-4 text-civic shrink-0" />}
                    <h2 className="text-sm font-semibold text-slate-900">Needs attention</h2>
                </div>
                <span className="text-xs text-slate-500">From current council records</span>
            </div>

            {items.length === 0 ? <p className="py-5 text-sm text-slate-600">No items currently need follow-up.</p> : <div className="divide-y divide-slate-100">
                {items.map((item) => (
                    <Link
                        key={item.id}
                        href={item.href}
                        className="group flex items-center justify-between py-3 hover:bg-slate-50/70 px-2 -mx-2 rounded-lg transition"
                    >
                        <div className="min-w-0 pr-3">
                            <p className="text-sm font-medium text-slate-900 group-hover:text-[#0F5C3A] transition">
                                {item.title}
                            </p>
                            <p className="text-xs text-slate-500 mt-0.5">{item.description}</p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                            <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200">
                                {item.count}
                            </span>
                            <ChevronRight className="h-4 w-4 text-slate-400 group-hover:text-slate-600 transition" />
                        </div>
                    </Link>
                ))}
            </div>}
        </section>
    );
}

