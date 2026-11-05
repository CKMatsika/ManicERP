import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, statusTone } from '../../Components/Ui';
import { formatDate, money, type SharedProps } from '../../lib';

type Suggestion = {
    line_id: string;
    line_number: number;
    value_date: string | null;
    amount_minor: number;
    reference: string | null;
    narrative: string | null;
    status: string;
    payment_id: string | null;
    payment_number: string | null;
    confidence: number;
    reason: string;
};

export default function BankStatement({ statement, suggestions }: { statement: { id: string; number: string; date: string | null; opening_minor: number; closing_minor: number; bank: string | null; status: string }; suggestions: Suggestion[] }) {
    const { council } = usePage<SharedProps>().props;

    function match(lineId: string, paymentId: string | null) {
        if (!paymentId) return;
        router.post(`/revenue/bank/lines/${lineId}/match`, { payment_id: paymentId }, { preserveScroll: true });
    }

    return (
        <AppLayout title={`Statement ${statement.number}`}>
            <Head title={`Statement ${statement.number}`} />

            <div className="mb-4 grid gap-3 sm:grid-cols-4">
                <Fact label="Bank" value={statement.bank ?? '—'} />
                <Fact label="Date" value={formatDate(statement.date)} />
                <Fact label="Opening" value={money(statement.opening_minor, council?.currency)} />
                <Fact label="Closing" value={money(statement.closing_minor, council?.currency)} />
            </div>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-slate-600">Status <Badge tone={statusTone(statement.status)}>{statement.status}</Badge></p>
                <button onClick={() => router.post(`/revenue/bank/${statement.id}/reconcile`)} className="rounded bg-teal-700 px-3 py-1.5 text-sm text-white hover:bg-teal-800">
                    Run reconciliation
                </button>
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th className="px-3 py-2">#</th><th>Value date</th><th>Reference</th><th>Narrative</th><th className="text-right">Amount</th><th>Status</th><th>Suggested match</th><th></th></tr>
                    </thead>
                    <tbody>
                        {suggestions.map((suggestion) => (
                            <tr key={suggestion.line_id} className="border-t border-slate-100 align-top">
                                <td className="px-3 py-2 text-xs text-slate-500">{suggestion.line_number}</td>
                                <td className="text-xs">{formatDate(suggestion.value_date)}</td>
                                <td className="font-mono text-xs">{suggestion.reference ?? '—'}</td>
                                <td className="text-xs">{suggestion.narrative ?? '—'}</td>
                                <td className="text-right">{money(suggestion.amount_minor, council?.currency)}</td>
                                <td><Badge tone={statusTone(suggestion.status)}>{suggestion.status}</Badge></td>
                                <td className="text-xs">
                                    {suggestion.payment_number ? (
                                        <>
                                            <p className="font-mono">{suggestion.payment_number}</p>
                                            <p className="text-slate-500">{suggestion.confidence}% · {suggestion.reason}</p>
                                        </>
                                    ) : (
                                        <span className="text-slate-500">{suggestion.reason}</span>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    {suggestion.status === 'unmatched' && suggestion.payment_id && (
                                        <button onClick={() => match(suggestion.line_id, suggestion.payment_id)} className="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">Match</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-slate-500">
                Matching records bank confirmation. It does not create a receipt; receipts are only issued on verified settlement. Unresolved lines remain visible until matched or ignored.
            </p>
        </AppLayout>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-3">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-medium">{value}</p>
        </div>
    );
}
