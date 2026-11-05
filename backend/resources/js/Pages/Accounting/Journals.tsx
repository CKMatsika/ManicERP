import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, money, type SharedProps } from '../../lib';
import { usePage } from '@inertiajs/react';

type Entry = {
    id: string;
    entry_number: string;
    entry_date: string | null;
    description: string;
    journal_type: string;
    status: string;
    total_debit_minor: number;
    currency: string;
    period: string | null;
    reversed: boolean;
};

type Account = { code: string; name: string; class: string };

export default function Journals({ entries }: { entries: { data: Entry[]; links: { url: string | null; label: string; active: boolean }[] }; accounts: Account[]; dimensions: any[]; periods: any[] }) {
    const { council } = usePage<SharedProps>().props;
    const [reversing, setReversing] = useState<Entry | null>(null);
    const [lines, setLines] = useState([
        { account_code: '', debit: '', credit: '', memo: '' },
        { account_code: '', debit: '', credit: '', memo: '' },
    ]);

    const form = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        description: '',
        lines,
    });

    function updateLine(index: number, key: string, value: string) {
        const next = lines.map((line, i) => (i === index ? { ...line, [key]: value } : line));
        setLines(next);
        form.setData('lines', next);
    }

    function addLine() {
        const next = [...lines, { account_code: '', debit: '', credit: '', memo: '' }];
        setLines(next);
        form.setData('lines', next);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post('/accounting/journals', {
            onSuccess: () => {
                setLines([
                    { account_code: '', debit: '', credit: '', memo: '' },
                    { account_code: '', debit: '', credit: '', memo: '' },
                ]);
                form.reset('description');
            },
        });
    }

    const reverseForm = useForm({ reason: '', reversal_date: new Date().toISOString().slice(0, 10) });

    return (
        <AppLayout title="Journals">
            <Head title="Journals" />

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="lg:col-span-2 rounded-lg border border-slate-200 bg-white">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h2 className="text-sm font-semibold">Posted and draft entries</h2>
                        <p className="text-xs text-slate-500">Posted entries are immutable; corrections use a linked reversal.</p>
                    </div>
                    <div className="overflow-x-auto">
                        {entries.data.length === 0 ? (
                            <div className="p-4"><EmptyState title="No journal entries" /></div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th className="px-3 py-2">Entry</th><th className="px-3 py-2">Date</th><th className="px-3 py-2">Description</th><th className="px-3 py-2 text-right">Amount</th><th className="px-3 py-2">Status</th><th className="px-3 py-2"></th></tr>
                                </thead>
                                <tbody>
                                    {entries.data.map((entry) => (
                                        <tr key={entry.id} className="border-t border-slate-100 align-top">
                                            <td className="px-3 py-2 font-mono text-xs">{entry.entry_number}</td>
                                            <td className="px-3 py-2 text-xs">{formatDate(entry.entry_date)}</td>
                                            <td className="px-3 py-2">
                                                <p>{entry.description}</p>
                                                <p className="text-xs text-slate-500">{entry.journal_type} · period {entry.period}</p>
                                            </td>
                                            <td className="px-3 py-2 text-right">{money(entry.total_debit_minor, entry.currency)}</td>
                                            <td className="px-3 py-2">
                                                <Badge tone={statusTone(entry.status)}>{entry.status}</Badge>
                                                {entry.reversed && <p className="mt-1 text-[10px] uppercase text-rose-700">Reversed</p>}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {entry.status === 'posted' && !entry.reversed && entry.journal_type !== 'reversal' && (
                                                    <button onClick={() => setReversing(entry)} className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">Reverse</button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>

                <section className="space-y-4">
                    {reversing ? (
                        <form
                            className="rounded-lg border border-rose-200 bg-white p-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                reverseForm.post(`/accounting/journals/${reversing.id}/reverse`, { onSuccess: () => setReversing(null) });
                            }}
                        >
                            <h2 className="text-sm font-semibold">Reverse {reversing.entry_number}</h2>
                            <label className="mt-2 block text-xs font-medium text-slate-600">Reason (required)</label>
                            <textarea value={reverseForm.data.reason} onChange={(e) => reverseForm.setData('reason', e.target.value)} required minLength={5} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" rows={2} />
                            <label className="mt-2 block text-xs font-medium text-slate-600">Reversal date</label>
                            <input type="date" value={reverseForm.data.reversal_date} onChange={(e) => reverseForm.setData('reversal_date', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                            {reverseForm.errors.reason && <p className="mt-1 text-xs text-rose-700">{reverseForm.errors.reason}</p>}
                            <div className="mt-2 flex gap-2">
                                <button type="submit" disabled={reverseForm.processing} className="rounded bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-800 disabled:opacity-50">Post reversal</button>
                                <button type="button" onClick={() => setReversing(null)} className="rounded border border-slate-300 px-3 py-1.5 text-xs">Cancel</button>
                            </div>
                        </form>
                    ) : (
                        <form onSubmit={submit} className="rounded-lg border border-slate-200 bg-white p-4">
                            <h2 className="text-sm font-semibold">New manual journal</h2>
                            <label className="mt-2 block text-xs font-medium text-slate-600">Entry date</label>
                            <input type="date" value={form.data.entry_date} onChange={(e) => form.setData('entry_date', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                            <label className="mt-2 block text-xs font-medium text-slate-600">Description</label>
                            <input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} required className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                            <div className="mt-3 space-y-2">
                                {lines.map((line, index) => (
                                    <div key={index} className="grid grid-cols-12 gap-1">
                                        <select value={line.account_code} onChange={(e) => updateLine(index, 'account_code', e.target.value)} className="col-span-6 rounded border border-slate-300 px-1 py-1 text-xs" aria-label={`Account line ${index + 1}`}>
                                            <option value="">Account</option>
                                            {form.data.lines.length >= 0 && (
                                                <>
                                                    {accounts.map((account) => (
                                                        <option key={account.code} value={account.code}>{account.code} {account.name}</option>
                                                    ))}
                                                </>
                                            )}
                                        </select>
                                        <input value={line.debit} onChange={(e) => updateLine(index, 'debit', e.target.value)} placeholder="Debit" inputMode="decimal" className="col-span-2 rounded border border-slate-300 px-1 py-1 text-xs" aria-label={`Debit line ${index + 1}`} />
                                        <input value={line.credit} onChange={(e) => updateLine(index, 'credit', e.target.value)} placeholder="Credit" inputMode="decimal" className="col-span-2 rounded border border-slate-300 px-1 py-1 text-xs" aria-label={`Credit line ${index + 1}`} />
                                        <input value={line.memo} onChange={(e) => updateLine(index, 'memo', e.target.value)} placeholder="Memo" className="col-span-2 rounded border border-slate-300 px-1 py-1 text-xs" aria-label={`Memo line ${index + 1}`} />
                                    </div>
                                ))}
                            </div>

                            <div className="mt-2 flex flex-wrap gap-2">
                                <button type="button" onClick={addLine} className="rounded border border-slate-300 px-2 py-1 text-xs">Add line</button>
                                <button type="submit" disabled={form.processing} className="rounded bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800 disabled:opacity-50">Post journal</button>
                            </div>
                            {form.errors.lines && <p className="mt-2 text-xs text-rose-700">{form.errors.lines}</p>}
                            <p className="mt-2 text-[11px] text-slate-500">Debits and credits must balance exactly. Amounts use {council?.currency ?? 'USD'}.</p>
                        </form>
                    )}
                </section>
            </div>

            <div className="mt-3 flex flex-wrap gap-1">
                {entries.links.map((link, index) =>
                    link.url ? (
                        <button key={index} onClick={() => router.get(link.url as string, {}, { preserveState: true })} className={`rounded border px-2 py-1 text-xs ${link.active ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 hover:bg-slate-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                        <span key={index} className="rounded border border-slate-200 px-2 py-1 text-xs text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </div>
        </AppLayout>
    );
}
