import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { EmptyState } from '../../Components/Ui';
import { money, type SharedProps } from '../../lib';

type Bill = { id: string; number: string; service: string; balance_minor: number };
type Account = { id: string; account_number: string; currency: string; outstanding_minor: number; bills: Bill[] };

export default function Pay({ accounts, selectedAccount }: { accounts: Account[]; selectedAccount: string | null; sandbox: boolean }) {
    const { council } = usePage<SharedProps>().props;
    const [accountId, setAccountId] = useState(selectedAccount ?? accounts[0]?.id ?? '');
    const current = accounts.find((account) => account.id === accountId);
    const form = useForm({ account_id: accountId, bill_id: '', amount: '' });

    function chooseAccount(id: string) {
        setAccountId(id);
        form.setData('account_id', id);
        form.setData('bill_id', '');
    }

    if (accounts.length === 0) {
        return (
            <PortalLayout title="Pay">
                <Head title="Pay" />
                <EmptyState title="No linked accounts" hint="Link an account before making a payment." />
            </PortalLayout>
        );
    }

    return (
        <PortalLayout title="Pay your account">
            <Head title="Pay" />

            <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Choose an account</h2>
                    <div className="mt-2 space-y-2">
                        {accounts.map((account) => (
                            <label key={account.id} className={`flex cursor-pointer items-center justify-between rounded border px-3 py-2 ${accountId === account.id ? 'border-teal-600 bg-teal-50' : 'border-slate-200'}`}>
                                <span>
                                    <span className="block font-mono text-xs">{account.account_number}</span>
                                    <span className="block text-xs text-slate-500">Outstanding {money(account.outstanding_minor, account.currency)}</span>
                                </span>
                                <input type="radio" name="account" checked={accountId === account.id} onChange={() => chooseAccount(account.id)} className="text-teal-700" />
                            </label>
                        ))}
                    </div>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/portal/pay');
                    }}
                    className="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <h2 className="text-sm font-semibold">Payment details</h2>

                    <label className="mt-3 block text-xs font-medium text-slate-600">Pay a specific bill (optional)</label>
                    <select value={form.data.bill_id} onChange={(e) => form.setData('bill_id', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">All outstanding bills (oldest first)</option>
                        {current?.bills.map((bill) => (
                            <option key={bill.id} value={bill.id}>{bill.number} · {bill.service} · {money(bill.balance_minor, current.currency)}</option>
                        ))}
                    </select>

                    {!form.data.bill_id && (
                        <>
                            <label htmlFor="amount" className="mt-2 block text-xs font-medium text-slate-600">Amount ({council?.currency})</label>
                            <input id="amount" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} inputMode="decimal" placeholder="0.00" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                            {form.errors.amount && <p className="mt-1 text-xs text-rose-700">{form.errors.amount}</p>}
                        </>
                    )}

                    {form.errors.account_id && <p className="mt-1 text-xs text-rose-700">{form.errors.account_id}</p>}

                    <button type="submit" disabled={form.processing} className="mt-3 w-full rounded bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                        Continue to payment
                    </button>

                    <div className="mt-3 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <strong>Sandbox payment adapter.</strong> No card or mobile-money details are collected and no real money moves. A receipt is issued only after the
                        server verifies the settlement. Real gateways require documented API access.
                    </div>
                </form>
            </div>
        </PortalLayout>
    );
}
