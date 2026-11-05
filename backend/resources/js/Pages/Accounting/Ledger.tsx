import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatDate, money, type SharedProps } from '../../lib';
import { EmptyState } from '../../Components/Ui';

export default function Ledger({ account, from, to, rows, balance_minor }: { account: string; from: string; to: string; rows: any[]; balance_minor: number }) {
    const { council } = usePage<SharedProps>().props;

    return (
        <AppLayout title={`General ledger ${account}`}>
            <Head title={`Ledger ${account}`} />

            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <p className="text-slate-600">Account <span className="font-mono">{account}</span> · {formatDate(from)} to {formatDate(to)}</p>
                <p className="font-semibold">Balance {money(balance_minor, council?.currency)}</p>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                {rows.length === 0 ? (
                    <div className="p-4"><EmptyState title="No posted lines for this account" /></div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-3 py-2">Date</th><th className="px-3 py-2">Entry</th><th className="px-3 py-2">Description</th><th className="px-3 py-2 text-right">Debit</th><th className="px-3 py-2 text-right">Credit</th></tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="border-t border-slate-100">
                                    <td className="px-3 py-1.5 text-xs">{formatDate(row.entry_date)}</td>
                                    <td className="px-3 py-1.5 font-mono text-xs">{row.entry_number}</td>
                                    <td className="px-3 py-1.5">{row.description}</td>
                                    <td className="px-3 py-1.5 text-right">{row.debit_minor ? money(row.debit_minor, council?.currency) : ''}</td>
                                    <td className="px-3 py-1.5 text-right">{row.credit_minor ? money(row.credit_minor, council?.currency) : ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AppLayout>
    );
}
