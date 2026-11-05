import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../Components/Ui';
import { formatDate, formatDateTime, money, type SharedProps } from '../lib';

type BillRow = { number: string; account: string | null; service: string; total_minor: number; balance_minor: number; status: string; issue_date: string | null };
type EventRow = { action: string; actor: string | null; at: string | null; subject: string | null };

export default function Dashboard({
    stats,
    recentBills,
    recentEvents,
    position,
    revenueEnabled,
    accountingEnabled,
}: {
    stats: { accounts: number; outstanding_minor: number; collected_today_minor: number; billed_this_month_minor: number; receipts_today: number };
    recentBills: BillRow[];
    recentEvents: EventRow[];
    position: { assets_minor: number; liabilities_minor: number; result_minor: number; check: number } | null;
    revenueEnabled: boolean;
    accountingEnabled: boolean;
}) {
    const { council, displayTimezone } = usePage<SharedProps>().props;

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            {council?.is_demo && (
                <div className="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <strong>Demonstration council.</strong> All figures come from seeded demonstration data. This is not a production implementation.
                </div>
            )}

            {revenueEnabled ? (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Active accounts" value={String(stats.accounts)} hint="Customer accounts with active status" />
                    <Stat label="Outstanding" value={money(stats.outstanding_minor, council?.currency)} hint="Open bill balances" />
                    <Stat label="Collected today" value={money(stats.collected_today_minor, council?.currency)} hint={`${stats.receipts_today} verified payment(s)`} />
                    <Stat label="Billed this month" value={money(stats.billed_this_month_minor, council?.currency)} hint="Issued, part-paid and paid bills" />
                </div>
            ) : (
                <EmptyState title="Revenue module is not enabled" hint="Enable it in Administration → Modules if this council is licensed for revenue." />
            )}

            <div className="mt-5 grid gap-4 lg:grid-cols-3">
                <section className="rounded-lg border border-slate-200 bg-white lg:col-span-2">
                    <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Recent bills</h2>
                        <Link href="/revenue/accounts" className="text-xs text-teal-700 hover:underline">View accounts</Link>
                    </div>
                    <div className="overflow-x-auto">
                        {recentBills.length === 0 ? (
                            <div className="p-4"><EmptyState title="No bills yet" hint="Approved billing runs create bills and journals." /></div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2">Bill</th>
                                        <th className="px-4 py-2">Account</th>
                                        <th className="px-4 py-2">Service</th>
                                        <th className="px-4 py-2 text-right">Total</th>
                                        <th className="px-4 py-2 text-right">Balance</th>
                                        <th className="px-4 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentBills.map((bill) => (
                                        <tr key={bill.number} className="border-t border-slate-100">
                                            <td className="px-4 py-2 font-mono text-xs">{bill.number}</td>
                                            <td className="px-4 py-2">{bill.account ?? '—'}</td>
                                            <td className="px-4 py-2 capitalize">{bill.service}</td>
                                            <td className="px-4 py-2 text-right">{money(bill.total_minor, council?.currency)}</td>
                                            <td className="px-4 py-2 text-right">{money(bill.balance_minor, council?.currency)}</td>
                                            <td className="px-4 py-2"><Badge tone={statusTone(bill.status)}>{bill.status}</Badge></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>

                <div className="space-y-4">
                    {accountingEnabled && position && (
                        <section className="rounded-lg border border-slate-200 bg-white p-4">
                            <h2 className="text-sm font-semibold">Financial position</h2>
                            <dl className="mt-2 space-y-1 text-sm">
                                <Row label="Assets" value={money(position.assets_minor, council?.currency)} />
                                <Row label="Liabilities" value={money(position.liabilities_minor, council?.currency)} />
                                <Row label="Current result" value={money(position.result_minor, council?.currency)} />
                            </dl>
                            <p className={`mt-2 text-xs ${position.check === 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                {position.check === 0 ? 'Equation check passed.' : `Equation out by ${money(position.check, council?.currency)}.`}
                            </p>
                            <Link href="/accounting/reports/position" className="mt-2 inline-block text-xs text-teal-700 hover:underline">Open report</Link>
                        </section>
                    )}

                    <section className="rounded-lg border border-slate-200 bg-white p-4">
                        <h2 className="text-sm font-semibold">Recent audit activity</h2>
                        <ul className="mt-2 space-y-2 text-xs">
                            {recentEvents.length === 0 && <li className="text-slate-500">No audit events yet.</li>}
                            {recentEvents.map((event, index) => (
                                <li key={`${event.action}-${index}`} className="border-b border-slate-100 pb-2 last:border-0">
                                    <p className="font-medium text-slate-800">{event.action}</p>
                                    <p className="text-slate-500">{event.actor ?? 'system'} · {formatDateTime(event.at, displayTimezone)}</p>
                                </li>
                            ))}
                        </ul>
                        <Link href="/admin/audit" className="mt-2 inline-block text-xs text-teal-700 hover:underline">Full audit trail</Link>
                    </section>
                </div>
            </div>

            <p className="mt-6 text-xs text-slate-500">
                All amounts are stored in integer minor units and posted through immutable journals. Displayed dates use {displayTimezone}.
                A {formatDate(new Date().toISOString())} snapshot.
            </p>
        </AppLayout>
    );
}

function Stat({ label, value, hint }: { label: string; value: string; hint: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight">{value}</p>
            <p className="mt-1 text-xs text-slate-500">{hint}</p>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <dt className="text-slate-600">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
