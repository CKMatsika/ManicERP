import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { EmptyState } from '../../Components/Ui';
import { formatDateTime, type SharedProps } from '../../lib';
import { usePage } from '@inertiajs/react';

type AuditRow = {
    id: string;
    chain_index: number;
    action: string;
    module: string | null;
    actor: string | null;
    role: string | null;
    subject_type: string | null;
    subject_id: string | null;
    occurred_at: string | null;
    hash: string;
};

export default function Audit({
    events,
    filters,
}: {
    events: { data: AuditRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { action?: string | null; from?: string | null; to?: string | null };
}) {
    const { displayTimezone } = usePage<SharedProps>().props;
    const { data, setData, get, processing } = useForm({
        action: filters.action ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        get('/admin/audit', { preserveState: true });
    }

    function verify() {
        router.post('/admin/audit/verify');
    }

    return (
        <AppLayout title="Audit trail">
            <Head title="Audit trail" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="action" className="block text-xs font-medium text-slate-600">Action contains</label>
                        <input id="action" value={data.action} onChange={(e) => setData('action', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label htmlFor="from" className="block text-xs font-medium text-slate-600">From</label>
                        <input id="from" type="date" value={data.from} onChange={(e) => setData('from', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label htmlFor="to" className="block text-xs font-medium text-slate-600">To</label>
                        <input id="to" type="date" value={data.to} onChange={(e) => setData('to', e.target.value)} className="mt-1 rounded border border-slate-300 px-2 py-1.5 text-sm" />
                    </div>
                    <button type="submit" disabled={processing} className="rounded bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">Filter</button>
                </form>

                <button onClick={verify} className="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">
                    Verify hash chain
                </button>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                {events.data.length === 0 ? (
                    <div className="p-4"><EmptyState title="No audit events match" /></div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">#</th>
                                <th className="px-3 py-2">Action</th>
                                <th className="px-3 py-2">Actor</th>
                                <th className="px-3 py-2">Subject</th>
                                <th className="px-3 py-2">When</th>
                                <th className="px-3 py-2">Hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.map((event) => (
                                <tr key={event.id} className="border-t border-slate-100 align-top">
                                    <td className="px-3 py-2 text-xs text-slate-500">{event.chain_index}</td>
                                    <td className="px-3 py-2">
                                        <p className="font-medium">{event.action}</p>
                                        <p className="text-xs text-slate-500">{event.module ?? '—'}</p>
                                    </td>
                                    <td className="px-3 py-2">
                                        <p>{event.actor ?? 'system'}</p>
                                        <p className="text-xs text-slate-500">{event.role ?? '—'}</p>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-600">
                                        {event.subject_type ? `${event.subject_type.split('\\').pop()} ${event.subject_id}` : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-xs">{formatDateTime(event.occurred_at, displayTimezone)}</td>
                                    <td className="px-3 py-2 font-mono text-[10px] text-slate-500">{event.hash}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            <div className="mt-3 flex flex-wrap gap-1">
                {events.links.map((link, index) =>
                    link.url ? (
                        <Link key={index} href={link.url} className={`rounded border px-2 py-1 text-xs ${link.active ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 hover:bg-slate-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                        <span key={index} className="rounded border border-slate-200 px-2 py-1 text-xs text-slate-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </div>
        </AppLayout>
    );
}
