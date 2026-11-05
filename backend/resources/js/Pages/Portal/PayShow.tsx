import { Head, router, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { money, type SharedProps } from '../../lib';

export default function PayShow({ intent, checkout }: { intent: { id: string; reference: string; amount_minor: number; currency: string; status: string; account: string }; checkout: any }) {
    const { council } = usePage<SharedProps>().props;

    return (
        <PortalLayout title="Confirm payment">
            <Head title="Confirm payment" />

            <div className="mx-auto max-w-lg rounded-lg border border-slate-200 bg-white p-5">
                <p className="text-xs uppercase tracking-wide text-slate-500">Sandbox checkout</p>
                <p className="mt-1 text-sm text-slate-600">Account <span className="font-mono">{intent.account}</span></p>

                <p className="mt-4 text-2xl font-semibold">{money(intent.amount_minor, intent.currency)}</p>
                <p className="text-xs text-slate-500">Reference {intent.reference} · status {intent.status}</p>

                <div className="mt-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {checkout?.message ?? 'Sandbox only.'}
                </div>

                <button
                    onClick={() => router.post(`/portal/pay/${intent.id}/sandbox-confirm`)}
                    className="mt-4 w-full rounded bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800"
                >
                    Simulate verified provider settlement
                </button>
                <p className="mt-2 text-[11px] text-slate-500">
                    This button calls the server-side sandbox settlement endpoint. It is clearly a demonstration adapter; a real gateway would confirm server-to-server through a
                    signed webhook instead. A browser return alone never issues a receipt.
                </p>
            </div>
        </PortalLayout>
    );
}
