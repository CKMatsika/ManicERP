import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { money, type SharedProps } from '../../lib';

type Line = { code: string; name: string; amount_minor: number };

export default function Performance({ report }: { report: { from: string; to: string; revenue: Line[]; expenses: Line[]; totals: { revenue_minor: number; expenses_minor: number } } }) {
    const { council } = usePage<SharedProps>().props;

    return (
        <AppLayout title="Financial performance">
            <Head title="Financial performance" />

            <p className="mb-3 text-xs text-slate-500">{report.from} to {report.to}</p>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Revenue</h2>
                    <ul className="mt-2 space-y-1 text-sm">
                        {report.revenue.length === 0 && <li className="text-slate-500">No revenue posted in this range.</li>}
                        {report.revenue.map((line) => (
                            <li key={line.code} className="flex justify-between border-b border-slate-100 pb-1">
                                <span><span className="font-mono text-xs text-slate-500">{line.code}</span> {line.name}</span>
                                <span className="font-medium">{money(line.amount_minor, council?.currency)}</span>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 flex justify-between border-t border-slate-300 pt-2 text-sm font-semibold">
                        <span>Total revenue</span><span>{money(report.totals.revenue_minor, council?.currency)}</span>
                    </p>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Expenses</h2>
                    <ul className="mt-2 space-y-1 text-sm">
                        {report.expenses.length === 0 && <li className="text-slate-500">No expenses posted in this range.</li>}
                        {report.expenses.map((line) => (
                            <li key={line.code} className="flex justify-between border-b border-slate-100 pb-1">
                                <span><span className="font-mono text-xs text-slate-500">{line.code}</span> {line.name}</span>
                                <span className="font-medium">{money(line.amount_minor, council?.currency)}</span>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 flex justify-between border-t border-slate-300 pt-2 text-sm font-semibold">
                        <span>Total expenses</span><span>{money(report.totals.expenses_minor, council?.currency)}</span>
                    </p>
                </section>
            </div>

            <div className="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-sm">
                <span className="text-slate-600">Surplus / (deficit) for the period: </span>
                <span className="font-semibold">{money(report.totals.revenue_minor - report.totals.expenses_minor, council?.currency)}</span>
            </div>
        </AppLayout>
    );
}
