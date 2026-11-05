import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { EmptyState } from '../../Components/Ui';
import { formatDateTime, money } from '../../lib';

type Account = { id: string; account_number: string; type: string; property: string | null; address: string | null; outstanding_minor: number; currency: string };
type Payment = { number: string; receipt: string | null; account: string | null; amount_minor: number; method: string; status: string; received_at: string | null };
type Pending = { id: string; account: string | null; requested_at: string | null };

export default function PortalDashboard({ accounts, payments, pendingLinks }: { accounts: Account[]; payments: Payment[]; pendingLinks: Pending[] }) {
    return (
        <PortalLayout title="Your municipal accounts">
            <Head title="Citizen portal" />

            {accounts.length === 0 ? (
                <div className="rounded-lg border border-slate-200 bg-white p-6">
                    <EmptyState title="No accounts linked yet" hint="Linking requires a verification code sent to the contact details on the council account. Account numbers alone do not grant access." />
                    <div className="mt-3 text-center">
                        <Link href="/portal/link" className="rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Link an account</Link>
                    </div>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                    {accounts.map((account) => (
                        <div key={account.id} className="rounded-lg border border-slate-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-mono text-xs text-slate-500">{account.account_number}</p>
                                    <p className="mt-1 text-sm font-medium capitalize">{account.type} account</p>
                                    <p className="text-xs text-slate-500">{account.property} · {account.address}</p>
                                </div>
                                <span className="rounded bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">
                                    {money(account.outstanding_minor, account.currency)}
                                </span>
                            </div>
                            <div className="mt-3 flex gap-3 text-xs">
                                <Link href={`/portal/accounts/${account.id}`} className="text-teal-700 hover:underline">View bills</Link>
                                <Link href={`/portal/pay?account=${account.id}`} className="text-teal-700 hover:underline">Pay</Link>
                                <a href={`/portal/accounts/${account.id}/statement`} className="text-teal-700 hover:underline">Statement PDF</a>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pendingLinks.length > 0 && (
                <section className="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <h2 className="text-sm font-semibold text-amber-900">Verification pending</h2>
                    <ul className="mt-1 text-sm text-amber-900">
                        {pendingLinks.map((link) => (
                            <li key={link.id}>{link.account} requested {formatDateTime(link.requested_at)}</li>
                        ))}
                    </ul>
                    <Link href="/portal/link" className="mt-1 inline-block text-xs font-medium underline">Enter verification code</Link>
                </section>
            )}

            <section className="mt-4 rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Recent verified payments</h2>
                </div>
                {payments.length === 0 ? (
                    <div className="p-4"><EmptyState title="No verified payments yet" /></div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {payments.map((payment) => (
                            <li key={payment.number} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                                <span className="font-mono text-xs">{payment.receipt ?? payment.number}</span>
                                <span className="text-xs text-slate-500">{payment.account} · {payment.method.replace('_', ' ')} · {formatDateTime(payment.received_at)}</span>
                                <span className="font-medium">{money(payment.amount_minor)}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <p className="mt-4 text-xs text-slate-500">
                Balances are computed from issued bills and verified payments. Official receipts are issued only after server-side verification.
            </p>
        </PortalLayout>
    );
}
