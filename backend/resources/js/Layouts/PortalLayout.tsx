import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '../lib';
import { Flash } from '../Components/Ui';

const NAV = [
    { label: 'Overview', href: '/portal' },
    { label: 'Pay', href: '/portal/pay' },
    { label: 'Link account', href: '/portal/link' },
    { label: 'Requests', href: '/portal/requests' },
    { label: 'Licences', href: '/portal/licensing' },
    { label: 'Notifications', href: '/portal/licensing/notifications' },
    { label: 'Licence help', href: '/portal/licensing/assistant' },
];

export default function PortalLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth, council, membership, displayTimezone } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">
                Skip to content
            </a>

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <Link href="/portal" className="text-sm font-semibold text-teal-800">ManicERP</Link>
                        <span className="text-xs text-slate-500">{council?.name}</span>
                        {council?.is_demo && (
                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-300">
                                Demo
                            </span>
                        )}
                    </div>
                    <form method="post" action="/logout" className="flex items-center gap-2 text-xs text-slate-600">
                        <span className="hidden sm:inline">{auth?.name}</span>
                        <input type="hidden" name="_token" value={(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''} />
                        <button type="submit" className="rounded border border-slate-300 px-2 py-1 font-medium hover:bg-slate-50">
                            Sign out
                        </button>
                    </form>
                </div>

                <nav className="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-3 pb-2" aria-label="Citizen portal">
                    {NAV.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="whitespace-nowrap rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600"
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </header>

            <main id="main" className="mx-auto max-w-5xl px-4 py-6">
                <div className="mb-4 flex items-baseline justify-between gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    <span className="text-xs text-slate-500">{displayTimezone}</span>
                </div>
                <Flash />
                {children}
            </main>

            <footer className="mx-auto max-w-5xl px-4 pb-8 text-xs text-slate-500">
                {membership?.role_name ?? 'Customer'} portal. Payments are only official once verified on the server and a receipt is issued.
            </footer>
        </div>
    );
}
