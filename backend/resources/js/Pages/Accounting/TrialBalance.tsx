import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { money, type SharedProps } from '../../lib';

type Row = { code: string; name: string; class: string; normal_balance: string; debit_minor: number; credit_minor: number; balance_minor: number };

export default function TrialBalance({ report, asOf, position }: { report: { rows: Row[]; totals: { debit_minor: number; credit_minor: number; balanced: boolean }; as_of: string }; asOf: string; position: { assets_minor: number; liabilities_minor: number; result_minor: number; check: number } }) {
    const { council } = usePage<SharedProps>().props;
    const { data, setData, get } = useForm({ as_of: asOf });

    return (
        <AppLayout title="Trial balance">
            <Head title="Trial balance" />

            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        get('/accounting/reports/trial-balance', { preserveState: true });
                    }}
                    className="flex items-end gap-2"
                >
                    <div>
                        <label htmlFor="as_of" className="block text-xs font-medium text-slate-600">As at</label>
                        <input id="as_of" type="date" value={data.as_of} onChange={(e) => setData('as_of', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                    <button type="submit" className="rounded bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800">Run</button>
                </form>

                <div className="rounded border border-slate-200 bg-white px-3 py-2 text-xs">
                    <span className={report.totals.balanced ? 'text-emerald-700' : 'text-rose-700'}>
                        {report.totals.balanced ? 'Debits equal credits' : 'Trial balance is out of balance'}
                    </span>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-3 py-2">Code</th>
                            <th className="px-3 py-2">Account</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2 text-right">Debit</th>
                            <th className="px-3 py-2 text-right">Credit</th>
                            <th className="px-3 py-2 text-right">Balance</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.rows.map((row) => (
                            <tr key={row.code} className="border-t border-slate-100">
                                <td className="px-3 py-1.5 font-mono text-xs">{row.code}</td>
                                <td className="px-3 py-1.5">{row.name}</td>
                                <td className="px-3 py-1.5 capitalize">{row.class.replace('_', ' ')}</td>
                                <td className="px-3 py-1.5 text-right">{money(row.debit_minor, council?.currency)}</td>
                                <td className="px-3 py-1.5 text-right">{money(row.credit_minor, council?.currency)}</td>
                                <td className="px-3 py-1.5 text-right">{money(row.balance_minor, council?.currency)}</td>
                                <td className="px-3 py-1.5 text-right">
                                    <a href={`/accounting/reports/ledger?account=${encodeURIComponent(row.code)}`} className="text-xs text-teal-700 hover:underline">Ledger</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-slate-300 font-semibold">
                            <td className="px-3 py-2" colSpan={3}>Totals</td>
                            <td className="px-3 py-2 text-right">{money(report.totals.debit_minor, council?.currency)}</td>
                            <td className="px-3 py-2 text-right">{money(report.totals.credit_minor, council?.currency)}</td>
                            <td className="px-3 py-2 text-right">{money(report.totals.debit_minor - report.totals.credit_minor, council?.currency)}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <Summary label="Assets" value={money(position.assets_minor, council?.currency)} />
                <Summary label="Liabilities" value={money(position.liabilities_minor, council?.currency)} />
                <Summary label="Equation check" value={position.check === 0 ? 'Passed' : money(position.check, council?.currency)} tone={position.check === 0 ? 'text-emerald-700' : 'text-rose-700'} />
            </div>

            <p className="mt-4 text-xs text-slate-500">
                Figures are derived from posted journal lines to {report.as_of}. Draft entries and unposted billing previews are excluded.
            </p>
        </AppLayout>
    );
}

function Summary({ label, value, tone = '' }: { label: string; value: string; tone?: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-3">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-lg font-semibold ${tone}`}>{value}</p>
        </div>
    );
}
