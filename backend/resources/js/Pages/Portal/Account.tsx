import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, formatDateTime, money } from '../../lib';

type Bill = { id: string; number: string; service: string; period: string; total_minor: number; balance_minor: number; status: string; due_date: string | null; explanation: any[] };
type Payment = { number: string; receipt: string | null; amount_minor: number; method: string; received_at: string | null };

export default function PortalAccount({ account, bills, payments }: { account: { id: string; number: string; type: string; property: string | null; address: string | null; outstanding_minor: number; currency: string }; bills: Bill[]; payments: Payment[] }) {
    return (
        <PortalLayout title={`Account ${account.number}`}>
            <Head title={`Account ${account.number}`} />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <div>
                    <p className="text-sm font-medium capitalize">{account.type} account</p>
                    <p className="text-xs text-slate-500">{account.property} · {account.address}</p>
                </div>
                <div className="text-right">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Outstanding</p>
                    <p className="text-lg font-semibold">{money(account.outstanding_minor, account.currency)}</p>
                </div>
                <div className="flex gap-2 text-xs">
                    <Link href={`/portal/pay?account=${account.id}`} className="rounded bg-teal-700 px-3 py-1.5 font-medium text-white hover:bg-teal-800">Pay this account</Link>
                    <a href={`/portal/accounts/${account.id}/statement`} className="rounded border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Statement PDF</a>
                </div>
            </div>

            <section className="rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Bills</h2>
                </div>
                {bills.length === 0 ? (
                    <div className="p-4"><EmptyState title="No bills on this account" /></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {bills.map((bill) => (
                            <li key={bill.id} className="px-4 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-mono text-xs">{bill.number}</p>
                                        <p className="text-xs text-slate-500 capitalize">{bill.service} · {bill.period} · due {formatDate(bill.due_date)}</p>
                                    </div>
                                    <div className="flex items-center gap-3 text-sm">
                                        <span>Total {money(bill.total_minor, account.currency)}</span>
                                        <span className="font-medium">Balance {money(bill.balance_minor, account.currency)}</span>
                                        <Badge tone={statusTone(bill.status)}>{bill.status}</Badge>
                                    </div>
                                </div>
                                {bill.explanation?.length > 0 && (
                                    <details className="mt-1 text-xs text-slate-600">
                                        <summary className="cursor-pointer text-teal-700">How this was calculated</summary>
                                        <ul className="mt-1 space-y-1">
                                            {bill.explanation.map((line: any, index: number) => (
                                                <li key={index}>
                                                    <p>{line.description}: {money(line.amount_minor, account.currency)}</p>
                                                    {(line.explanation ?? []).map((step: any, stepIndex: number) => (
                                                        <p key={stepIndex} className="text-slate-500">
                                                            {step.description}{step.units ? ` · ${step.units} units` : ''}{step.rate_minor ? ` @ ${step.rate_minor}c` : ''}
                                                        </p>
                                                    ))}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="mt-4 rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Verified payments and receipts</h2>
                </div>
                {payments.length === 0 ? (
                    <div className="p-4"><EmptyState title="No payments yet" /></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {payments.map((payment) => (
                            <li key={payment.number} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                                <span className="font-mono text-xs">{payment.receipt ?? payment.number}</span>
                                <span className="text-xs text-slate-500">{payment.method.replace('_', ' ')} · {formatDateTime(payment.received_at)}</span>
                                <span className="font-medium">{money(payment.amount_minor, account.currency)}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PortalLayout>
    );
}
