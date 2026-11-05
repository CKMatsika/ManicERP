import { Head, useForm } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDateTime } from '../../lib';

type RequestRow = { reference: string; category: string; subject: string; status: string; priority: string; created_at: string | null; resolution: string | null };

export default function Requests({ requests, accounts }: { requests: RequestRow[]; accounts: { id: string; number: string }[] }) {
    const form = useForm({ account_id: accounts[0]?.id ?? '', category: 'billing_dispute', subject: '', description: '' });

    return (
        <PortalLayout title="Service requests">
            <Head title="Service requests" />

            <div className="grid gap-4 lg:grid-cols-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/portal/requests', { onSuccess: () => form.reset('subject', 'description') });
                    }}
                    className="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <h2 className="text-sm font-semibold">Raise a complaint or service request</h2>

                    <label className="mt-3 block text-xs font-medium text-slate-600">Account (optional)</label>
                    <select value={form.data.account_id} onChange={(e) => form.setData('account_id', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">No specific account</option>
                        {accounts.map((account) => <option key={account.id} value={account.id}>{account.number}</option>)}
                    </select>

                    <label className="mt-2 block text-xs font-medium text-slate-600">Category</label>
                    <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="billing_dispute">Billing dispute</option>
                        <option value="leak">Water leak</option>
                        <option value="water_interruption">Water interruption</option>
                        <option value="refuse">Refuse collection</option>
                        <option value="complaint">General complaint</option>
                        <option value="other">Other</option>
                    </select>

                    <label className="mt-2 block text-xs font-medium text-slate-600">Subject</label>
                    <input value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} required className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <label className="mt-2 block text-xs font-medium text-slate-600">Description</label>
                    <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} required rows={4} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />

                    <button type="submit" disabled={form.processing} className="mt-3 w-full rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">
                        Submit request
                    </button>
                </form>

                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Your requests</h2>
                    {requests.length === 0 ? (
                        <div className="mt-2"><EmptyState title="No requests yet" /></div>
                    ) : (
                        <ul className="mt-2 space-y-3">
                            {requests.map((item) => (
                                <li key={item.reference} className="border-b border-slate-100 pb-2 last:border-0">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="font-mono text-xs">{item.reference}</p>
                                        <Badge tone={statusTone(item.status)}>{item.status}</Badge>
                                    </div>
                                    <p className="text-sm">{item.subject}</p>
                                    <p className="text-xs text-slate-500 capitalize">{item.category.replace('_', ' ')} · {item.priority} priority · {formatDateTime(item.created_at)}</p>
                                    {item.resolution && <p className="mt-1 text-xs text-slate-600">{item.resolution}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </PortalLayout>
    );
}
