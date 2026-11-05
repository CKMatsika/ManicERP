import { Head, useForm, usePage } from '@inertiajs/react';

export default function MfaSetup({ enabled, secret, uri }: { enabled: boolean; secret: string | null; uri: string | null }) {
    const page = usePage<{ flash: { recovery_codes?: string[] } }>();
    const recoveryCodes = (page.props.flash as any)?.recovery_codes as string[] | undefined;
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/security/mfa/setup');
    }

    return (
        <>
            <Head title="Multi-factor authentication" />
            <div className="mx-auto max-w-2xl px-4 py-10">
                <h1 className="text-xl font-semibold">Multi-factor authentication</h1>

                {enabled ? (
                    <div className="mt-4 space-y-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <p className="font-medium">Your authenticator is enrolled.</p>
                        <p>You will be asked for a code when your session expires. Recovery codes were shown once at enrolment.</p>
                        <a href="/dashboard" className="inline-block rounded bg-teal-700 px-3 py-2 text-white">Back to dashboard</a>
                    </div>
                ) : (
                    <div className="mt-4 space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                        <p className="text-sm text-slate-600">
                            Your role is privileged, so multi-factor authentication is required. Add this secret to your authenticator app, then confirm a generated code.
                        </p>

                        <div className="rounded border border-slate-200 bg-slate-50 p-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Secret</p>
                            <p className="mt-1 break-all font-mono text-sm">{secret}</p>
                            {uri && <p className="mt-2 break-all text-xs text-slate-500">{uri}</p>}
                        </div>

                        {recoveryCodes && recoveryCodes.length > 0 && (
                            <div className="rounded border border-amber-300 bg-amber-50 p-3 text-sm">
                                <p className="font-semibold">Recovery codes (save these now)</p>
                                <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-xs">
                                    {recoveryCodes.map((code) => <li key={code}>{code}</li>)}
                                </ul>
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-3">
                            {errors.code && <div className="rounded border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800">{errors.code}</div>}
                            <div>
                                <label htmlFor="code" className="block text-sm font-medium text-slate-700">Code from your authenticator</label>
                                <input
                                    id="code"
                                    inputMode="numeric"
                                    value={data.code}
                                    onChange={(event) => setData('code', event.target.value)}
                                    className="mt-1 w-48 rounded border border-slate-300 px-3 py-2 font-mono focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                                />
                            </div>
                            <button type="submit" disabled={processing} className="rounded bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                                Enable multi-factor authentication
                            </button>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}
