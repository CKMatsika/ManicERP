import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState, statusTone } from '../../Components/Ui';
import { formatDate, formatDateTime } from '../../lib';

type Version = { id: string; name: string; version_no: number; status: string; source_type: string; production_blocker: boolean; accounts_count: number; effective_from: string | null };
type Account = { code: string; name: string; class: string; type: string; normal_balance: string; is_control: boolean };
type Batch = { id: string; file: string; status: string; rows: number; errors: number; created_at: string | null; chart_version: string | null };

export default function Chart({ versions, accounts, activeVersion, batches, demoBlocker }: { versions: Version[]; accounts: Account[]; activeVersion: string | null; batches: Batch[]; demoBlocker: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; name: string; source_type: string; source_authority: string }>({
        file: null,
        name: '',
        source_type: 'demo',
        source_authority: '',
    });

    function importChart(event: React.FormEvent) {
        event.preventDefault();
        post('/accounting/chart/import', {
            forceFormData: true,
            onSuccess: () => reset('file', 'name', 'source_authority'),
        });
    }

    return (
        <AppLayout title="Chart of accounts">
            <Head title="Chart of accounts" />

            {demoBlocker && (
                <div className="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <strong>The active chart is a DEMONSTRATION chart, not an official Ministry of Finance schedule.</strong> Production reporting remains blocked until an
                    authorised, versioned schedule is imported, verified and approved.
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Active chart</h2>
                    <p className="mt-1 text-sm text-slate-700">{activeVersion ?? 'No active chart'}</p>
                    <p className="mt-1 text-xs text-slate-500">{accounts.length} accounts in the active version.</p>
                    <p className="mt-3 text-xs text-slate-500">Codes are stored as strings, preserving leading zeros. Posted account identities are never rewritten by an upgrade.</p>
                </section>

                <section className="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
                    <h2 className="text-sm font-semibold">Stage a chart import (CSV or XLSX)</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Required headers: code, name, class, type, parent_code, normal_balance, is_control, active_from, active_to, description, gfsm_code.
                    </p>

                    <form onSubmit={importChart} className="mt-3 grid gap-2 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label htmlFor="file" className="block text-xs font-medium text-slate-600">File</label>
                            <input id="file" type="file" accept=".csv,.xlsx" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className="mt-1 block w-full text-sm" />
                            {errors.file && <p className="mt-1 text-xs text-rose-700">{errors.file}</p>}
                        </div>
                        <div>
                            <label htmlFor="name" className="block text-xs font-medium text-slate-600">Chart name</label>
                            <input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                            {errors.name && <p className="mt-1 text-xs text-rose-700">{errors.name}</p>}
                        </div>
                        <div>
                            <label htmlFor="source_type" className="block text-xs font-medium text-slate-600">Source</label>
                            <select id="source_type" value={data.source_type} onChange={(e) => setData('source_type', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="demo">Demonstration (blocked for production)</option>
                                <option value="official">Official council schedule</option>
                            </select>
                        </div>
                        <div className="sm:col-span-2">
                            <label htmlFor="authority" className="block text-xs font-medium text-slate-600">Issuing authority / evidence reference</label>
                            <input id="authority" value={data.source_authority} onChange={(e) => setData('source_authority', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div className="sm:col-span-2">
                            <button type="submit" disabled={processing} className="rounded bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">
                                Validate and stage
                            </button>
                        </div>
                    </form>

                    <div className="mt-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Recent import batches</h3>
                        {batches.length === 0 ? (
                            <p className="mt-2 text-xs text-slate-500">No imports staged.</p>
                        ) : (
                            <table className="mt-2 w-full text-xs">
                                <thead className="text-left uppercase text-slate-400">
                                    <tr><th className="py-1">File</th><th>Status</th><th>Rows</th><th>Errors</th><th>When</th><th></th></tr>
                                </thead>
                                <tbody>
                                    {batches.map((batch) => (
                                        <tr key={batch.id} className="border-t border-slate-100">
                                            <td className="py-1.5">{batch.file}</td>
                                            <td><Badge tone={statusTone(batch.status)}>{batch.status}</Badge></td>
                                            <td>{batch.rows}</td>
                                            <td>{batch.errors}</td>
                                            <td>{formatDateTime(batch.created_at)}</td>
                                            <td className="text-right">
                                                {batch.status !== 'approved' && batch.errors === 0 && (
                                                    <button onClick={() => router.post(`/accounting/chart/batches/${batch.id}/approve`)} className="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50">
                                                        Approve
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>
            </div>

            <section className="mt-4 rounded-lg border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-4 py-3">
                    <h2 className="text-sm font-semibold">Active accounts</h2>
                </div>
                <div className="overflow-x-auto">
                    {accounts.length === 0 ? (
                        <div className="p-4"><EmptyState title="No active chart" hint="Import and approve a chart to start posting." /></div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr><th className="px-3 py-2">Code</th><th className="px-3 py-2">Name</th><th className="px-3 py-2">Class</th><th className="px-3 py-2">Type</th><th className="px-3 py-2">Normal</th><th className="px-3 py-2">Control</th></tr>
                            </thead>
                            <tbody>
                                {accounts.map((account) => (
                                    <tr key={account.code} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5 font-mono text-xs">{account.code}</td>
                                        <td className="px-3 py-1.5">{account.name}</td>
                                        <td className="px-3 py-1.5 capitalize">{account.class.replace('_', ' ')}</td>
                                        <td className="px-3 py-1.5 capitalize">{account.type}</td>
                                        <td className="px-3 py-1.5 capitalize">{account.normal_balance}</td>
                                        <td className="px-3 py-1.5">{account.is_control ? 'Yes' : 'No'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </section>

            <section className="mt-4 rounded-lg border border-slate-200 bg-white p-4">
                <h2 className="text-sm font-semibold">Chart versions</h2>
                <ul className="mt-2 space-y-2 text-sm">
                    {versions.map((version) => (
                        <li key={version.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2 last:border-0">
                            <div>
                                <p className="font-medium">{version.name} <span className="text-xs text-slate-500">v{version.version_no}</span></p>
                                <p className="text-xs text-slate-500">{version.accounts_count} accounts · {version.source_type} · effective {formatDate(version.effective_from)}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {version.production_blocker && <Badge tone="amber">Production blocked</Badge>}
                                <Badge tone={statusTone(version.status)}>{version.status}</Badge>
                            </div>
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}
