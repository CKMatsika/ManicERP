import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, money, type SharedProps } from '../../lib';

type Run = { id: string; number: string; service: string; period: string; status: string; totals: any };
type Tariff = { code: string; name: string; service: string; basis: string; status: string; effective_from: string | null; version: number; bands: any[] };

export default function Billing({ runs, tariffs }: { runs: Run[]; tariffs: Tariff[] }) {
    const { council, permissions } = usePage<SharedProps>().props;
    const preview = useForm({
        service_type: 'rates',
        period_start: new Date().toISOString().slice(0, 8) + '01',
        period_end: new Date().toISOString().slice(0, 10),
    });

    const canApprove = permissions.includes('revenue.billing.approve');

    return (
        <AppLayout title="Billing runs">
            <Head title="Billing runs" />

            <div className="grid gap-4 lg:grid-cols-3">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        preview.post('/revenue/billing/preview');
                    }}
                    className="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <h2 className="text-sm font-semibold">New preview</h2>
                    <p className="mt-1 text-xs text-slate-500">Preview calculates charges and control totals without creating bills. Approval locks the totals; posting creates bills, snapshots and journals.</p>

                    <label htmlFor="service_type" className="mt-3 block text-xs font-medium text-slate-600">Service</label>
                    <select id="service_type" value={preview.data.service_type} onChange={(e) => preview.setData('service_type', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="rates">Rates</option>
                        <option value="water">Water</option>
                        <option value="refuse">Refuse</option>
                        <option value="rental">Rental</option>
                    </select>

                    <label htmlFor="period_start" className="mt-2 block text-xs font-medium text-slate-600">Period start</label>
                    <input id="period_start" type="date" value={preview.data.period_start} onChange={(e) => preview.setData('period_start', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <label htmlFor="period_end" className="mt-2 block text-xs font-medium text-slate-600">Period end</label>
                    <input id="period_end" type="date" value={preview.data.period_end} onChange={(e) => preview.setData('period_end', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <button type="submit" disabled={preview.processing} className="mt-3 w-full rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">
                        Preview billing run
                    </button>
                </form>

                <section className="rounded-lg border border-slate-200 bg-white lg:col-span-2">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Recent runs</h2>
                    </div>
                    {runs.length === 0 ? (
                        <div className="p-4"><EmptyState title="No billing runs yet" /></div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {runs.map((run) => (
                                <li key={run.id} className="px-4 py-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="font-mono text-xs">{run.number} <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 capitalize">{run.service}</span></p>
                                            <p className="text-xs text-slate-500">{run.period} · {run.totals?.included ?? 0} included · {run.totals?.skipped ?? 0} skipped</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">{money(run.totals?.total_minor ?? 0, council?.currency)}</span>
                                            <Badge tone={statusTone(run.status)}>{run.status}</Badge>
                                        </div>
                                    </div>

                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {run.status === 'previewed' && canApprove && (
                                            <button onClick={() => router.post(`/revenue/billing/${run.id}/approve`)} className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">Approve</button>
                                        )}
                                        {run.status === 'approved' && canApprove && (
                                            <button onClick={() => router.post(`/revenue/billing/${run.id}/post`)} className="rounded bg-teal-700 px-2 py-1 text-xs font-medium text-white hover:bg-teal-800">Post bills and journals</button>
                                        )}
                                        {run.totals?.posted_bills !== undefined && (
                                            <span className="text-xs text-slate-500">{run.totals.posted_bills} bills posted</span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section className="mt-4 rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Tariff versions</h2>
                    <p className="text-xs text-slate-500">Effective-dated; only approved versions are used for billing. Rates use cents per $100 of approved valuation.</p>
                </div>
                <ul className="divide-y divide-slate-100">
                    {tariffs.map((tariff) => (
                        <li key={`${tariff.code}-${tariff.version}`} className="px-4 py-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-sm font-medium">{tariff.name}</p>
                                    <p className="text-xs text-slate-500">{tariff.code} v{tariff.version} · {tariff.basis} · effective {formatDate(tariff.effective_from)}</p>
                                </div>
                                <Badge tone={statusTone(tariff.status)}>{tariff.status}</Badge>
                            </div>
                            <table className="mt-2 w-full max-w-lg text-xs">
                                <thead className="text-left uppercase text-slate-400"><tr><th className="py-1">Band</th><th>From</th><th>To</th><th className="text-right">Rate (cents)</th><th className="text-right">Fixed</th></tr></thead>
                                <tbody>
                                    {tariff.bands.map((band) => (
                                        <tr key={band.band} className="border-t border-slate-100">
                                            <td className="py-1">{band.band}</td>
                                            <td>{band.from}</td>
                                            <td>{band.to ?? '∞'}</td>
                                            <td className="text-right">{band.rate_minor}</td>
                                            <td className="text-right">{money(band.fixed_minor, council?.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}
