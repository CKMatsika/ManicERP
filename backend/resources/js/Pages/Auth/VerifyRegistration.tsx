import { Head, useForm } from '@inertiajs/react';

export default function VerifyRegistration({ email }: { email: string }) {
    const { post, processing } = useForm({});
    return <>
        <Head title="Verify citizen account" />
        <main className="mx-auto flex min-h-screen max-w-xl items-center px-4 py-8">
            <section className="w-full rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 className="text-2xl font-semibold">Verify your email</h1>
                <p className="mt-2 text-sm text-slate-600">Confirm the one-time link sent to <strong>{email}</strong>. This link expires after 30 minutes. Opening it alone does not activate the account.</p>
                <form method="post" action={window.location.href} onSubmit={e => { e.preventDefault(); post(window.location.pathname + window.location.search); }} className="mt-5">
                    <button type="submit" disabled={processing} className="w-full rounded bg-teal-700 px-3 py-2 font-semibold text-white disabled:opacity-50">{processing ? 'Verifying...' : 'Verify email and activate account'}</button>
                </form>
                <p className="mt-4 text-xs text-slate-500">Account activation does not grant access to customer or property records. Use the verified account-link request after signing in.</p>
            </section>
        </main>
    </>;
}
