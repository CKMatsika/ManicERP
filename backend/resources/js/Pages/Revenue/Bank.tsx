import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, money, type SharedProps } from '../../lib';

type Bank = { id: string; code: string; name: string; bank: string; gl_account: string; currency: string };
type Statement = { id: string; number: string; bank: string | null; date: string | null; closing_minor: number; status: string; unmatched: number };

export default function BankPage({ accounts, statements }: { accounts: Bank[]; statements: Statement[] }) {
    const { council } = usePage<SharedProps>().props;
    const form = useForm<{ bank_account_id: string; statement_number: string; statement_date: string; opening_balance: string; closing_balance: string; file: File | null }>({
        bank_account_id: accounts[0]?.id ?? '',
        statement_number: '',
        statement_date: new Date().toISOString().slice(0, 10),
        opening_balance: '0.00',
        closing_balance: '0.00',
        file: null,
    });

    return (
        <AppLayout title="Bank reconciliation">
            <Head title="Bank reconciliation" />

            <div className="grid gap-4 lg:grid-cols-3">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/revenue/bank/import', { forceFormData: true });
                    }}
                    className="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <h2 className="text-sm font-semibold">Stage a bank statement (CSV)</h2>
                    <p className="mt-1 text-xs text-slate-500">Columns: value_date, amount, reference, narrative. Positive amounts are deposits.</p>

                    <label className="mt-3 block text-xs font-medium text-slate-600">Bank account</label>
                    <select value={form.data.bank_account_id} onChange={(e) => form.setData('bank_account_id', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        {accounts.map((account) => <option key={account.id} value={account.id}>{account.code} · {account.name}</option>)}
                    </select>

                    <label className="mt-2 block text-xs font-medium text-slate-600">Statement number</label>
                    <input value={form.data.statement_number} onChange={(e) => form.setData('statement_number', e.target.value)} required className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <label className="mt-2 block text-xs font-medium text-slate-600">Statement date</label>
                    <input type="date" value={form.data.statement_date} onChange={(e) => form.setData('statement_date', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <div className="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label className="block text-xs font-medium text-slate-600">Opening</label>
                            <input value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', e.target.value)} inputMode="decimal" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-600">Closing</label>
                            <input value={form.data.closing_balance} onChange={(e) => form.setData('closing_balance', e.target.value)} inputMode="decimal" className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        </div>
                    </div>

                    <label className="mt-2 block text-xs font-medium text-slate-600">CSV file</label>
                    <input type="file" accept=".csv,text/csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="mt-1 block w-full text-sm" />

                    <button type="submit" disabled={form.processing || accounts.length === 0} className="mt-3 w-full rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">
                        Import and stage
                    </button>
                </form>

                <section className="rounded-lg border border-slate-200 bg-white lg:col-span-2">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Statements</h2>
                        <p className="text-xs text-slate-500">Bank-confirmed money is distinguished from internal cashbook entries.</p>
                    </div>
                    {statements.length === 0 ? (
                        <div className="p-4"><EmptyState title="No statements staged" /></div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-slate-400"><tr><th className="px-3 py-2">Statement</th><th>Bank</th><th>Date</th><th className="text-right">Closing</th><th>Status</th><th>Unmatched</th></tr></thead>
                            <tbody>
                                {statements.map((statement) => (
                                    <tr key={statement.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5"><Link href={`/revenue/bank/${statement.id}`} className="font-mono text-xs text-teal-700 hover:underline">{statement.number}</Link></td>
                                        <td>{statement.bank}</td>
                                        <td className="text-xs">{formatDate(statement.date)}</td>
                                        <td className="text-right">{money(statement.closing_minor, council?.currency)}</td>
                                        <td><Badge tone={statusTone(statement.status)}>{statement.status}</Badge></td>
                                        <td>{statement.unmatched}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    <div className="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                        <p className="font-medium text-slate-600">Bank accounts</p>
                        {accounts.map((account) => (
                            <p key={account.id}>{account.code} · {account.bank} · GL {account.gl_account} · {account.currency}</p>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
