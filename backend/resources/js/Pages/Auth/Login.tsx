import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ArrowLeft, ArrowRight, Building2, CheckCircle2, ShieldCheck } from 'lucide-react';

export default function Login() {
    const { flash } = usePage<{ flash: { status?: string; success?: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Sign in | ManicERP" />
            <main className="min-h-screen bg-canvas lg:grid lg:grid-cols-2">
                <section className="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-14 xl:px-20">
                    <Link href="/" className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-ink"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-civic text-base font-bold text-white">M</span>ManicERP</Link>
                    <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-12">
                        <Link href="/" className="mb-8 inline-flex items-center gap-1.5 text-sm text-muted hover:text-civic"><ArrowLeft className="h-4 w-4" />Back to home</Link>
                        <p className="text-sm font-medium text-civic">Welcome back</p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-ink">Sign in to ManicERP</h1>
                        <p className="mt-2 text-sm leading-6 text-muted">Use your council or citizen account to continue.</p>

                        <form onSubmit={submit} className="mt-8 space-y-5 rounded-xl border border-line bg-white p-5 shadow-sm sm:p-6">
                            {(flash.status || flash.success) && <div role="status" className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-900">{flash.status ?? flash.success}</div>}
                            {errors.email && <div role="alert" className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-800">{errors.email}</div>}
                            <div>
                                <label htmlFor="email" className="block text-sm font-medium text-ink">Email address</label>
                                <input id="email" type="email" autoComplete="username" required autoFocus value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1.5 w-full rounded-md border border-line bg-white px-3 py-2.5 text-sm text-ink outline-none transition placeholder:text-subtle focus:border-civic focus:ring-2 focus:ring-civic/20" placeholder="name@council.gov.zw" />
                            </div>
                            <div>
                                <div className="flex items-center justify-between gap-3"><label htmlFor="password" className="block text-sm font-medium text-ink">Password</label></div>
                                <input id="password" type="password" autoComplete="current-password" required value={data.password} onChange={(event) => setData('password', event.target.value)} className="mt-1.5 w-full rounded-md border border-line bg-white px-3 py-2.5 text-sm text-ink outline-none transition focus:border-civic focus:ring-2 focus:ring-civic/20" />
                            </div>
                            <label className="flex items-center gap-2.5 text-sm text-slate-700"><input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="h-4 w-4 rounded border-line accent-civic" />Keep me signed in on this device</label>
                            <button type="submit" disabled={processing} className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-civic px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-civic-hover focus:outline-none focus:ring-2 focus:ring-civic focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">{processing ? 'Signing in…' : 'Sign in'}{!processing && <ArrowRight className="h-4 w-4" />}</button>
                            <p className="text-center text-sm text-muted">New citizen? <a className="font-medium text-civic underline-offset-4 hover:underline" href="/register">Create an account</a></p>
                            <p className="text-center text-xs leading-5 text-subtle">Registration does not automatically grant access to council accounts.</p>
                        </form>
                        <p className="mt-6 text-center text-xs text-subtle">Secure access is managed by your council.</p>
                    </div>
                </section>

                <aside className="relative hidden overflow-hidden bg-civic px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-16">
                    <div aria-hidden="true" className="pointer-events-none absolute -right-28 -top-28 h-96 w-96 rounded-full border border-white/10" />
                    <div aria-hidden="true" className="pointer-events-none absolute -right-12 -top-12 h-64 w-64 rounded-full border border-white/10" />
                    <div className="relative flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-lg border border-white/20 bg-white/10"><Building2 className="h-5 w-5" /></span><div><p className="text-sm font-semibold">ManicERP</p><p className="text-xs text-white/70">Local authority management</p></div></div>
                    <div className="relative max-w-xl py-10">
                        <p className="text-sm font-medium text-white/75">One connected council workspace</p>
                        <h2 className="mt-3 text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">Make everyday council work clearer.</h2>
                        <p className="mt-5 max-w-lg text-base leading-7 text-white/80">Access the tools your role needs for finance, revenue and municipal services, with shared records and practical controls.</p>
                        <ul className="mt-8 space-y-4 text-sm text-white/90">
                            <li className="flex items-center gap-3"><CheckCircle2 className="h-5 w-5 text-white/75" />Role-based council workspaces</li>
                            <li className="flex items-center gap-3"><CheckCircle2 className="h-5 w-5 text-white/75" />Connected operational and financial records</li>
                            <li className="flex items-center gap-3"><ShieldCheck className="h-5 w-5 text-white/75" />Approval and audit history for council work</li>
                        </ul>
                    </div>
                    <p className="relative text-xs text-white/65">ManicERP · Local authority management</p>
                </aside>
            </main>
        </>
    );
}
