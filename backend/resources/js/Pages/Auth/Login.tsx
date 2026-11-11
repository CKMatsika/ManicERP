import { Head, useForm, usePage } from '@inertiajs/react';

export default function Login() {
    const { flash } = usePage<{ flash: { status?: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Sign in" />
            <div className="flex min-h-screen items-center justify-center px-4 py-10">
                <div className="w-full max-w-md">
                    <div className="mb-6 text-center">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-teal-700">ManicERP</p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">Municipal ERP sign in</h1>
                        <p className="mt-1 text-sm text-slate-600">Staff ERP and citizen portal use the same secure sign-in.</p>
                    </div>

                    <form onSubmit={submit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                        {flash.status && <div role="status" className="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{flash.status}</div>}
                        {errors.email && <div className="rounded border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800">{errors.email}</div>}

                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-slate-700">Email address</label>
                            <input
                                id="email"
                                type="email"
                                autoComplete="username"
                                required
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-slate-700">Password</label>
                            <input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="rounded border-slate-300" />
                            Keep me signed in on this device
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 disabled:opacity-50"
                        >
                            {processing ? 'Signing in...' : 'Sign in'}
                        </button>

                        <p className="text-center text-xs text-slate-500">New citizen? <a className="font-medium text-teal-800 underline" href="/register">Create an account</a>. Registration does not automatically grant access to council accounts.</p>
                    </form>

                </div>
            </div>
        </>
    );
}
