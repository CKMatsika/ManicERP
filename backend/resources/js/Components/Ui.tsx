import { usePage } from '@inertiajs/react';
import type { SharedProps } from '../lib';

export function Flash() {
    const { flash } = usePage<SharedProps>().props;

    if (!flash) return null;

    const entries = [
        { key: 'success', tone: 'border-emerald-300 bg-emerald-50 text-emerald-900' },
        { key: 'error', tone: 'border-rose-300 bg-rose-50 text-rose-900' },
        { key: 'warning', tone: 'border-amber-300 bg-amber-50 text-amber-900' },
        { key: 'info', tone: 'border-sky-300 bg-sky-50 text-sky-900' },
    ] as const;

    const visible = entries.filter((entry) => flash[entry.key]);

    if (visible.length === 0) return null;

    return (
        <div className="mb-4 space-y-2" role="status" aria-live="polite">
            {visible.map((entry) => (
                <div key={entry.key} className={`rounded-md border px-3 py-2 text-sm ${entry.tone}`}>
                    {flash[entry.key]}
                </div>
            ))}
        </div>
    );
}

export function Badge({ children, tone = 'slate' }: { children: React.ReactNode; tone?: 'slate' | 'green' | 'amber' | 'rose' | 'sky' }) {
    const tones = {
        slate: 'bg-slate-100 text-slate-700 ring-slate-200',
        green: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        amber: 'bg-amber-50 text-amber-900 ring-amber-200',
        rose: 'bg-rose-50 text-rose-800 ring-rose-200',
        sky: 'bg-sky-50 text-sky-800 ring-sky-200',
    };

    return <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}>{children}</span>;
}

export function statusTone(status: string): 'slate' | 'green' | 'amber' | 'rose' | 'sky' {
    if (['paid', 'active', 'approved', 'posted', 'completed', 'verified', 'issued', 'enabled'].includes(status)) return 'green';
    if (['previewed', 'partially_paid', 'pending', 'draft', 'validated', 'captured'].includes(status)) return 'amber';
    if (['failed', 'revoked', 'cancelled', 'disabled', 'void', 'rejected', 'provisioning_failed'].includes(status)) return 'rose';
    if (['closed', 'matched', 'imported', 'locked'].includes(status)) return 'sky';

    return 'slate';
}

export function EmptyState({ title, hint }: { title: string; hint?: string }) {
    return (
        <div className="rounded-md border border-dashed border-slate-300 px-4 py-8 text-center">
            <p className="text-sm font-medium text-slate-700">{title}</p>
            {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
        </div>
    );
}
