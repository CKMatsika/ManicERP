import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, formatDateTime, money, type SharedProps } from '../../lib';

type Bill = { id: string; number: string; service: string; period: string; total_minor: number; paid_minor: number; balance_minor: number; status: string; due_date: string | null; snapshot: any };
type Payment = { id: string; number: string; receipt: string | null; amount_minor: number; method: string; status: string; source: string | null; received_at: string | null };

export default function AccountShow({ account, bills, payments }: { account: any; bills: Bill[]; payments: Payment[] }) {
    const { council, displayTimezone } = usePage<SharedProps>().props;

    return (
        <AppLayout title={`Account ${account.account_number}`}>
            <Head title={`Account ${account.account_number}`} />

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Account</h2>
                    <dl className="mt-2 space-y-1 text-sm">
                        <div className="flex justify-between"><dt className="text-slate-500">Number</dt><dd className="font-mono text-xs">{account.account_number}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Type</dt><dd className="capitalize">{account.type}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Status</dt><dd><Badge tone={statusTone(account.status)}>{account.status}</Badge></dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Opened</dt><dd>{formatDate(account.opened_on)}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Outstanding</dt><dd className="font-semibold">{money(account.outstanding_minor, account.currency)}</dd></div>
                    </dl>

                    <h3 className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Liable customer</h3>
                    <p className="mt-1 text-sm">{account.party?.name}</p>
                    <p className="text-xs text-slate-500">{account.party?.code} · {account.party?.type}</p>
                    <ul className="mt-1 space-y-0.5 text-xs text-slate-600">
                        {(account.party?.contacts ?? []).map((contact: any, index: number) => (
                            <li key={index}>{contact.type}: {contact.value} {contact.verified ? '(verified)' : '(unverified)'}</li>
                        ))}
                    </ul>

                    {account.property && (
                        <>
                            <h3 className="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Property</h3>
                            <p className="mt-1 text-sm">{account.property.parcel}</p>
                            <p className="text-xs text-slate-500">{account.property.address}, {account.property.suburb} · {account.property.ward}</p>
                        </>
                    )}
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Connections and meters</h2>
                    <ul className="mt-2 space-y-3 text-sm">
                        {(account.connections ?? []).map((connection: any) => (
                            <li key={connection.number} className="border-b border-slate-100 pb-2 last:border-0">
                                <p className="font-medium capitalize">{connection.service} · <span className="font-mono text-xs">{connection.number}</span></p>
                                <p className="text-xs text-slate-500">{connection.status} · installed {formatDate(connection.status === 'active' ? account.opened_on : null)}</p>
                                <ul className="mt-1 space-y-0.5 text-xs text-slate-600">
                                    {(connection.meters ?? []).map((meter: any) => (
                                        <li key={meter.serial}>Meter {meter.serial} · {meter.status}</li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                        {(account.connections ?? []).length === 0 && <li className="text-slate-500">No service connections.</li>}
                    </ul>
                    <p className="mt-3 text-xs text-slate-500">Meter replacement preserves reading continuity and history.</p>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Recent payments</h2>
                    <ul className="mt-2 space-y-2 text-sm">
                        {payments.length === 0 && <li className="text-slate-500">No verified payments.</li>}
                        {payments.map((payment) => (
                            <li key={payment.id} className="border-b border-slate-100 pb-2 last:border-0">
                                <div className="flex justify-between gap-2">
                                    <span className="font-mono text-xs">{payment.number}</span>
                                    <span className="font-medium">{money(payment.amount_minor, account.currency)}</span>
                                </div>
                                <p className="text-xs text-slate-500">
                                    Receipt {payment.receipt ?? '—'} · {payment.method} · {payment.source ?? 'n/a'} · {formatDateTime(payment.received_at, displayTimezone)}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <section className="mt-4 rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Bills and calculation snapshots</h2>
                    <p className="text-xs text-slate-500">Every bill retains the tariff version, reading and calculation used when it was issued.</p>
                </div>
                {bills.length === 0 ? (
                    <div className="p-4"><EmptyState title="No bills" hint="Approve and post a billing run." /></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {bills.map((bill) => (
                            <li key={bill.id} className="px-4 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-mono text-xs">{bill.number} <span className="ml-2 rounded bg-slate-100 px-1.5 py-0.5 capitalize">{bill.service}</span></p>
                                        <p className="text-xs text-slate-500">{bill.period} · due {formatDate(bill.due_date)}</p>
                                    </div>
                                    <div className="flex items-center gap-3 text-sm">
                                        <span>Total {money(bill.total_minor, council?.currency)}</span>
                                        <span>Balance {money(bill.balance_minor, council?.currency)}</span>
                                        <Badge tone={statusTone(bill.status)}>{bill.status}</Badge>
                                    </div>
                                </div>
                                {bill.snapshot?.lines && (
                                    <details className="mt-2 text-xs text-slate-600">
                                        <summary className="cursor-pointer text-teal-700">Calculation details</summary>
                                        <ul className="mt-1 space-y-1">
                                            {bill.snapshot.lines.map((line: any, index: number) => (
                                                <li key={index} className="rounded bg-slate-50 px-2 py-1">
                                                    <p>{line.description}: {money(line.amount_minor, council?.currency)}</p>
                                                    {(line.explanation ?? []).map((step: any, stepIndex: number) => (
                                                        <p key={stepIndex} className="text-slate-500">
                                                            {step.description} {step.units ? `· ${step.units} units` : ''} {step.amount_minor !== undefined ? `· ${money(step.amount_minor, council?.currency)}` : ''}
                                                        </p>
                                                    ))}
                                                </li>
                                            ))}
                                        </ul>
                                        <p className="mt-1">Engine: {bill.snapshot.engine} · tariff {bill.snapshot.tariff_code} · source {bill.snapshot.source}</p>
                                    </details>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
