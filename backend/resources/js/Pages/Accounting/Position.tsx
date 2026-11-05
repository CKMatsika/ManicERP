import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { money, type SharedProps } from '../../lib';

export default function Position({ position, asOf }: { position: { assets_minor: number; liabilities_minor: number; net_assets_minor: number; revenue_minor: number; expenses_minor: number; result_minor: number; check: number }; asOf: string }) {
    const { council } = usePage<SharedProps>().props;

    const rows = [
        ['Assets', position.assets_minor],
        ['Liabilities', position.liabilities_minor],
        ['Net assets / equity', position.net_assets_minor],
        ['Revenue to date', position.revenue_minor],
        ['Expenses to date', position.expenses_minor],
        ['Current period result', position.result_minor],
    ] as const;

    return (
        <AppLayout title="Financial position">
            <Head title="Financial position" />

            <div className="max-w-xl rounded-lg border border-slate-200 bg-white p-4">
                <p className="text-xs uppercase tracking-wide text-slate-500">As at {asOf}</p>
                <dl className="mt-3 space-y-2">
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex items-center justify-between border-b border-slate-100 pb-1.5 text-sm">
                            <dt className="text-slate-600">{label}</dt>
                            <dd className="font-medium">{money(value, council?.currency)}</dd>
                        </div>
                    ))}
                </dl>
                <p className={`mt-3 text-sm font-medium ${position.check === 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                    Assets − (liabilities + net assets + result) = {money(position.check, council?.currency)}
                </p>
                <p className="mt-2 text-xs text-slate-500">
                    Prepared on an accrual basis from posted journals. This is a management report, not a complete IPSAS financial statement; disclosure notes, comparatives
                    and consolidation are not yet implemented.
                </p>
            </div>
        </AppLayout>
    );
}
