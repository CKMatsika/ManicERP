import { Form, Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { useState } from 'react';
import { can, type SharedProps } from '../../lib';
const field = 'rounded border px-2 py-1 text-sm';
const button = 'rounded bg-teal-700 px-3 py-1 text-sm text-white disabled:opacity-50';
export default function Refunds({ refunds, accounts, allocations }: any) {
    const { permissions } = usePage<SharedProps>().props;
    return <AppLayout title="Refunds (sandbox)"><Head title="Refunds" /><p className="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm">Sandbox settlement only. No real funds move. Original receipts remain unchanged. Allocated payments require an explicit, separately audited allocation reversal before refunding. Deposits require an approved release policy.</p>
        {can(permissions, 'revenue.refund.request') && <Form action="/revenue/refunds" method="post" className="mb-5 flex flex-wrap gap-2">{({ errors, processing }) => <>
            <select name="customer_account_id" required className={field}><option value="">Customer account</option>{accounts.map((a: any) => <option key={a.id} value={a.id}>{a.account_number}</option>)}</select><input name="payment_id" placeholder="Payment ID (omit for opening unapplied credit)" aria-label="Payment ID" className={field} /><input name="amount_minor" type="number" min="1" step="1" required placeholder="Amount in minor units" aria-label="Amount in minor units" className={field} /><input name="reason" required placeholder="Refund reason" aria-label="Refund reason" className={field} /><input name="idempotency_key" required placeholder="Unique request reference" aria-label="Unique request reference" className={field} /><button disabled={processing} className={button}>Request refund</button><p role="alert" className="text-rose-800">{Object.values(errors).join(' ')}</p>
        </>}</Form>}
        {refunds.map((r: any) => <article key={r.id} className="mb-3 rounded border bg-white p-4 text-sm"><h2 className="font-semibold">{r.refund_number} · {r.account?.account_number} · {r.amount_minor} minor units · {r.status}</h2><p>{r.reason} · {r.customer_note}</p><p className="my-2 text-xs">Original payment {r.payment?.payment_number ?? 'Opening unapplied funds'} · receipt {r.payment?.receipt?.receipt_number ?? 'No historic receipt manufactured'} · journal {r.journal_entry_id ?? 'Pending'} · {r.sandbox_reference}</p><div className="flex flex-wrap gap-2">
            {r.status === 'requested' && can(permissions, 'revenue.refund.approve') && <Form action={`/revenue/refunds/${r.id}/approve`} method="post"><button className={button}>Approve independently</button></Form>}
            {['requested', 'approved'].includes(r.status) && can(permissions, 'revenue.refund.approve') && <Form action={`/revenue/refunds/${r.id}/reject`} method="post"><input name="reason" required placeholder="Rejection reason" aria-label="Rejection reason" className={field} /><button className={button}>Reject</button></Form>}
            {['approved', 'failed'].includes(r.status) && can(permissions, 'revenue.refund.settle') && <Form action={`/revenue/refunds/${r.id}/settle`} method="post"><button className={button}>{r.status === 'failed' ? 'Retry sandbox settlement' : 'Settle in sandbox'}</button></Form>}
        </div></article>)}
        {can(permissions, 'revenue.payment.reverse') && <section className="mt-4 rounded border bg-white p-4"><h2 className="font-semibold">Reverse an allocation explicitly</h2><p className="mb-2 text-sm">This restores the invoice balance and transfers the GL control balance to unapplied funds. It does not reverse the receipt or make a refund.</p><AllocationReversal allocations={allocations} /></section>}
    </AppLayout>;
}
function AllocationReversal({ allocations }: any) {
    const [selected, select] = useState('');
    return <Form action={`/revenue/allocations/${selected}/reverse`} method="post">{({ errors, processing }) => <><select aria-label="Allocation to reverse" required value={selected} onChange={e => select(e.target.value)} className={field}><option value="">Select allocation</option>{allocations.map((a: any) => <option key={a.id} value={a.id}>{a.payment?.payment_number} / {a.bill?.bill_number} / {a.amount_minor} minor units</option>)}</select><input name="reason" required placeholder="Reason for allocation reversal" aria-label="Reason for allocation reversal" className={field} /><button disabled={processing || !selected} className={button}>Reverse selected allocation</button><p role="alert">{Object.values(errors).join(' ')}</p></>}</Form>;
}
