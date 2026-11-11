import { Head, useForm, usePage } from '@inertiajs/react';

type Council = { code: string; name: string };

export default function Register({ councils }: { councils: Council[] }) {
    const { flash } = usePage<{ flash: { status?: string } }>().props;
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', council_code: '', password: '', password_confirmation: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post('/register');
    }

    return <>
        <Head title="Citizen registration" />
        <main className="mx-auto flex min-h-screen max-w-xl items-center px-4 py-8">
            <section className="w-full rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-teal-700">ManicERP</p>
                <h1 className="mt-2 text-2xl font-semibold">Create a citizen account</h1>
                <p className="mt-2 text-sm text-slate-600">We will send a one-time email verification link. Registration does not link properties, customer accounts or business records; request a verified account link after signing in.</p>
                {flash.status && <div role="status" className="mt-4 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{flash.status}</div>}
                {(Object.keys(errors).length > 0) && <div role="alert" className="mt-4 rounded border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900">Please correct the highlighted registration details.</div>}
                <form onSubmit={submit} className="mt-5 space-y-4">
                    <div><label htmlFor="name" className="block text-sm font-medium">Full name</label><input id="name" autoComplete="name" required value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-3 py-2" />{errors.name && <p role="alert" className="mt-1 text-sm text-rose-700">{errors.name}</p>}</div>
                    <div><label htmlFor="email" className="block text-sm font-medium">Email address</label><input id="email" type="email" autoComplete="email" required value={data.email} onChange={e => setData('email', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-3 py-2" />{errors.email && <p role="alert" className="mt-1 text-sm text-rose-700">{errors.email}</p>}</div>
                    <div><label htmlFor="council" className="block text-sm font-medium">Council</label><select id="council" required value={data.council_code} onChange={e => setData('council_code', e.target.value)} className="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2"><option value="">Select a council</option>{councils.map(council => <option key={council.code} value={council.code}>{council.name}</option>)}</select>{errors.council_code && <p role="alert" className="mt-1 text-sm text-rose-700">{errors.council_code}</p>}</div>
                    <div><label htmlFor="password" className="block text-sm font-medium">Password</label><input id="password" type="password" autoComplete="new-password" required value={data.password} onChange={e => setData('password', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-3 py-2" />{errors.password && <p role="alert" className="mt-1 text-sm text-rose-700">{errors.password}</p>}</div>
                    <div><label htmlFor="password_confirmation" className="block text-sm font-medium">Confirm password</label><input id="password_confirmation" type="password" autoComplete="new-password" required value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} className="mt-1 w-full rounded border border-slate-300 px-3 py-2" /></div>
                    <button type="submit" disabled={processing} className="w-full rounded bg-teal-700 px-3 py-2 font-semibold text-white disabled:opacity-50">{processing ? 'Sending verification...' : 'Create account'}</button>
                </form>
                <p className="mt-4 text-center text-sm text-slate-600"><a className="text-teal-800 underline" href="/login">Return to sign in</a></p>
            </section>
        </main>
    </>;
}
