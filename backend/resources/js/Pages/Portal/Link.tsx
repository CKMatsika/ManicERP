import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { EmptyState } from '../../Components/Ui';
import type { SharedProps } from '../../lib';

type Pending = { id: string; account: string | null };
type Verified = { id: string; account: string | null; since: string | null };

export default function LinkAccount({ pending, verified }: { pending: Pending[]; verified: Verified[] }) {
    const { flash } = usePage<SharedProps>().props;
    const [codeFor, setCodeFor] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const request = useForm({ account_number: '', relationship: 'owner' });

    return (
        <PortalLayout title="Link an account">
            <Head title="Link an account" />

            <div className="grid gap-4 lg:grid-cols-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        request.post('/portal/link', { preserveScroll: true });
                    }}
                    className="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <h2 className="text-sm font-semibold">Request access to an account</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        A one-time code is sent to the verified contact on the council account. Knowing an account number alone does not grant access.
                    </p>

                    <label htmlFor="account_number" className="mt-3 block text-xs font-medium text-slate-600">Account number</label>
                    <input id="account_number" value={request.data.account_number} onChange={(e) => request.setData('account_number', e.target.value)} required className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" placeholder="ACC-0000001" />

                    <label htmlFor="relationship" className="mt-2 block text-xs font-medium text-slate-600">Relationship</label>
                    <select id="relationship" value={request.data.relationship} onChange={(e) => request.setData('relationship', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="owner">Owner</option>
                        <option value="representative">Authorised representative</option>
                    </select>

                    <button type="submit" disabled={request.processing} className="mt-3 w-full rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50">
                        Send verification code
                    </button>

                    {flash?.masked && <p className="mt-2 text-xs text-slate-600">Code sent to {flash.masked}.</p>}
                </form>

                <section className="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 className="text-sm font-semibold">Pending verification</h2>
                    {pending.length === 0 ? (
                        <div className="mt-2"><EmptyState title="No pending requests" /></div>
                    ) : (
                        <ul className="mt-2 space-y-3">
                            {pending.map((item) => (
                                <li key={item.id} className="rounded border border-slate-200 p-3">
                                    <p className="font-mono text-xs">{item.account}</p>
                                    {codeFor === item.id ? (
                                        <div className="mt-2 flex gap-2">
                                            <input value={code} onChange={(e) => setCode(e.target.value)} inputMode="numeric" placeholder="6-digit code" className="w-32 rounded border border-slate-300 px-2 py-1 text-sm" aria-label="Verification code" />
                                            <button
                                                onClick={() => router.post(`/portal/link/${item.id}/verify`, { code }, { onSuccess: () => { setCodeFor(null); setCode(''); } })}
                                                className="rounded bg-teal-700 px-2 py-1 text-xs font-medium text-white"
                                            >
                                                Verify
                                            </button>
                                        </div>
                                    ) : (
                                        <button onClick={() => setCodeFor(item.id)} className="mt-2 rounded border border-slate-300 px-2 py-1 text-xs">Enter code</button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <h2 className="mt-4 text-sm font-semibold">Linked accounts</h2>
                    {verified.length === 0 ? (
                        <p className="mt-1 text-xs text-slate-500">None yet.</p>
                    ) : (
                        <ul className="mt-2 space-y-2">
                            {verified.map((item) => (
                                <li key={item.id} className="flex items-center justify-between gap-2 text-sm">
                                    <span className="font-mono text-xs">{item.account}</span>
                                    <button
                                        onClick={() => {
                                            const reason = window.prompt('Reason for revoking this link?');
                                            if (reason) router.post(`/portal/link/${item.id}/revoke`, { reason });
                                        }}
                                        className="text-xs text-rose-700 hover:underline"
                                    >
                                        Revoke
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </PortalLayout>
    );
}
