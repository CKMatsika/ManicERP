import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { money, type SharedProps } from '../../lib';

type Account = { id: string; account_number: string; party: string | null; property: string | null; ward: string | null; type: string; status: string; outstanding_minor: number };

export default function Accounts({ accounts, search }: { accounts: { data: Account[]; links: { url: string | null; label: string; active: boolean }[] }; search: string }) {
    const { council } = usePage<SharedProps>().props;
    const { data, setData, get } = useForm({ q: search });

    return (
        <AppLayout title="Customer accounts">
            <Head title="Customer accounts" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    get('/revenue/accounts', { preserveState: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-2"
            >
                <div>
                    <label htmlFor="q" className="block text-xs font-medium text-slate-600">Search accounts, customers or parcels</label>
                    <input id="q" value={data.q} onChange={(e) => setData('q', e.target.value)} className="mt-1 w-72 rounded border border-slate-300 px-2 py-1.5 text-sm" placeholder="ACC-0000001, Tendai, AV-1024" />
                </div>
                <button type="submit" className="rounded bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800">Search</button>
                <Link href="/revenue/accounts" className="text-xs text-slate-500 underline">Clear</Link>
            </form>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                {accounts.data.length === 0 ? (
                    <div className="p-4"><EmptyState title="No accounts match" hint="Accounts are created by revenue clerks with an explicit liable party." /></div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-3 py-2">Account</th><th className="px-3 py-2">Customer</th><th className="px-3 py-2">Property</th><th className="px-3 py-2">Ward</th><th className="px-3 py-2">Type</th><th className="px-3 py-2 text-right">Outstanding</th><th className="px-3 py-2">Status</th></tr>
                        </thead>
                        <tbody>
                            {accounts.data.map((account) => (
                                <tr key={account.id} className="border-t border-slate-100 hover:bg-slate-50">
                                    <td className="px-3 py-1.5 font-mono text-xs">
                                        <Link href={`/revenue/accounts/${account.id}`} className="text-teal-700 hover:underline">{account.account_number}</Link>
                                    </td>
                                    <td className="px-3 py-1.5">{account.party ?? '—'}</td>
                                    <td className="px-3 py-1.5">{account.property ?? '—'}</td>
                                    <td className="px-3 py-1.5">{account.ward ?? '—'}</td>
                                    <td className="px-3 py-1.5 capitalize">{account.type}</td>
                                    <td className="px-3 py-1.5 text-right">{money(account.outstanding_minor, council?.currency)}</td>
                                    <td className="px-3 py-1.5"><Badge tone={statusTone(account.status)}>{account.status}</Badge></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AppLayout>
    );
}
