import { Head, useForm } from '@inertiajs/react';

export default function MfaChallenge() {
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/security/mfa/challenge');
    }

    return (
        <>
            <Head title="Two-factor authentication" />
            <div className="flex min-h-screen items-center justify-center px-4">
                <form onSubmit={submit} className="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 className="text-lg font-semibold">Two-factor authentication</h1>
                    <p className="text-sm text-slate-600">Enter the 6-digit code from your authenticator app, or a recovery code.</p>

                    {errors.code && <div className="rounded border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800">{errors.code}</div>}

                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-slate-700">Authentication code</label>
                        <input
                            id="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            required
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value)}
                            className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-center font-mono text-lg tracking-widest focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                        />
                    </div>

                    <button type="submit" disabled={processing} className="w-full rounded bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50">
                        Verify and continue
                    </button>
                </form>
            </div>
        </>
    );
}
