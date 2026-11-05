import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { EmptyState } from '../../Components/Ui';
import { formatDateTime, money, type SharedProps } from '../../lib';

type PaymentRow = { number: string; receipt: string | null; account: string | null; amount_minor: number; method: string; received_at: string | null };

export default function Cashier({ session, payments, cashTotalMinor }: { session: { id: string; number: string; opened_at: string | null; opening_float_minor: number } | null; payments: PaymentRow[]; cashTotalMinor: number }) {
    const { council, auth, displayTimezone } = usePage<SharedProps>().props;

    const open = useForm({ opening_float: '0.00' });
    const capture = useForm({ account_number: '', amount: '', method: 'cash', reference: '' });
    const close = useForm({ counted_cash: '' });

    return (
        <AppLayout title="Cashier">
            <Head title="Cashier" />

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Session</h2>
                    {session ? (
                        <>
                            <p className="mt-1 font-mono text-xs">{session.number}</p>
                            <p className="text-xs text-slate-500">Opened {formatDateTime(session.opened_at, displayTimezone)}</p>
                            <p className="mt-2 text-sm">Cashier: {auth?.name}</p>
                            <p className="text-sm">Opening float: {money(session.opening_float_minor, council?.currency)}</p>
                            <p className="text-sm">Cash received: {money(cashTotalMinor, council?.currency)}</p>

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    close.post('/revenue/cashier/close');
                                }}
                                className="mt-3 border-t border-slate-200 pt-3"
                            >
                                <label htmlFor="counted_cash" className="block text-xs font-medium text-slate-600">Counted cash at close</label>
                                <input id="counted_cash" value={close.data.counted_cash} onChange={(e) => close.setData('counted_cash', e.target.value)} inputMode="decimal" placeholder="0.00" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                                <button type="submit" disabled={close.processing} className="mt-2 w-full rounded border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 disabled:opacity-50">Close and reconcile session</button>
                            </form>
                        </>
                    ) : (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                open.post('/revenue/cashier/open');
                            }}
                            className="mt-2"
                        >
                            <label htmlFor="opening_float" className="block text-xs font-medium text-slate-600">Opening float</label>
                            <input id="opening_float" value={open.data.opening_float} onChange={(e) => open.setData('opening_float', e.target.value)} inputMode="decimal" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                            <button type="submit" disabled={open.processing} className="mt-2 w-full rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">Open cashier session</button>
                        </form>
                    )}
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
                    <h2 className="text-sm font-semibold">Capture a verified payment</h2>
                    <p className="mt-1 text-xs text-slate-500">A receipt is issued only after the payment is verified on the server. Payments are allocated oldest-first across open bills.</p>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            capture.post('/revenue/cashier/capture', { onSuccess: () => capture.reset('account_number', 'amount', 'reference') });
                        }}
                        className="mt-3 grid gap-2 sm:grid-cols-2"
                    >
                        <div>
                            <label htmlFor="account_number" className="block text-xs font-medium text-slate-600">Account number</label>
                            <input id="account_number" value={capture.data.account_number} onChange={(e) => capture.setData('account_number', e.target.value)} required className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" placeholder="ACC-0000001" />
                            {capture.errors.account_number && <p className="mt-1 text-xs text-rose-700">{capture.errors.account_number}</p>}
                        </div>
                        <div>
                            <label htmlFor="amount" className="block text-xs font-medium text-slate-600">Amount ({council?.currency})</label>
                            <input id="amount" value={capture.data.amount} onChange={(e) => capture.setData('amount', e.target.value)} required inputMode="decimal" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" placeholder="0.00" />
                            {capture.errors.amount && <p className="mt-1 text-xs text-rose-700">{capture.errors.amount}</p>}
                        </div>
                        <div>
                            <label htmlFor="method" className="block text-xs font-medium text-slate-600">Tender</label>
                            <select id="method" value={capture.data.method} onChange={(e) => capture.setData('method', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="reference" className="block text-xs font-medium text-slate-600">External reference</label>
                            <input id="reference" value={capture.data.reference} onChange={(e) => capture.setData('reference', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div className="sm:col-span-2">
                            <button type="submit" disabled={capture.processing || !session} className="rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">Verify and issue receipt</button>
                            {!session && <span className="ml-2 text-xs text-amber-700">Open a session first.</span>}
                        </div>
                    </form>

                    <div className="mt-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Session payments</h3>
                        {payments.length === 0 ? (
                            <div className="mt-2"><EmptyState title="No payments in this session" /></div>
                        ) : (
                            <table className="mt-2 w-full text-sm">
                                <thead className="text-left text-xs uppercase text-slate-400"><tr><th className="py-1">Payment</th><th>Receipt</th><th>Account</th><th>Tender</th><th className="text-right">Amount</th><th>When</th></tr></thead>
                                <tbody>
                                    {payments.map((payment) => (
                                        <tr key={payment.number} className="border-t border-slate-100">
                                            <td className="py-1.5 font-mono text-xs">{payment.number}</td>
                                            <td className="font-mono text-xs">{payment.receipt ?? '—'}</td>
                                            <td className="font-mono text-xs">{payment.account}</td>
                                            <td className="capitalize">{payment.method.replace('_', ' ')}</td>
                                            <td className="text-right">{money(payment.amount_minor, council?.currency)}</td>
                                            <td className="text-xs">{formatDateTime(payment.received_at, displayTimezone)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
