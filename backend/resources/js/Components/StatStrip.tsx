import React from 'react';
import Icon from './Icon';

export interface StatItem {
    label: string;
    value: string | number;
    helper?: string;
    tone?: 'default' | 'emerald' | 'amber' | 'rose' | 'slate';
    icon?: string;
}

interface StatStripProps {
    stats: StatItem[];
    className?: string;
}

export default function StatStrip({ stats, className = '' }: StatStripProps) {
    if (!stats || stats.length === 0) return null;

    const toneClasses = {
        default: 'text-slate-900',
        emerald: 'text-emerald-700',
        amber: 'text-amber-700',
        rose: 'text-rose-700',
        slate: 'text-slate-600',
    };

    return (
        <div className={`mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 ${className}`}>
            {stats.map((stat, idx) => (
                <div key={idx} className="rounded-lg border border-slate-200 bg-white p-3.5 transition-all">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-slate-500">{stat.label}</span>
                        {stat.icon && <Icon name={stat.icon} className="h-4 w-4 text-slate-400" />}
                    </div>
                    <div className="mt-1 flex items-baseline gap-2">
                        <span className={`text-xl font-semibold tabular-nums tracking-tight ${toneClasses[stat.tone || 'default']}`}>
                            {stat.value}
                        </span>
                        {stat.helper && (
                            <span className="text-xs text-slate-500">{stat.helper}</span>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

