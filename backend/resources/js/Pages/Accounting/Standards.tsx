import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/Ui';

type Standard = {
    code: string;
    title: string;
    edition: string | null;
    effective_from: string | null;
    jurisdiction: string;
    applicability: string;
    transition_method: string | null;
    verification_status: string;
    approved: boolean;
    notes: string | null;
};

export default function Standards({ standards, summary }: { standards: Standard[]; summary: { total: number; verified: number; unverified: number } }) {
    return (
        <AppLayout title="Accounting standards register">
            <Head title="Standards register" />

            <div className="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <strong>Not a compliance claim.</strong> Entries below are planning assumptions with {summary.unverified} of {summary.total} sources still unverified.
                Each standard needs a verified source document, an applicability decision and an approved policy choice before affected production reporting.
            </div>

            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-3 py-2">Standard</th>
                            <th className="px-3 py-2">International effective</th>
                            <th className="px-3 py-2">Applicability</th>
                            <th className="px-3 py-2">Transition</th>
                            <th className="px-3 py-2">Verification</th>
                            <th className="px-3 py-2">Policy approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        {standards.map((standard) => (
                            <tr key={standard.code} className="border-t border-slate-100 align-top">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{standard.code} — {standard.title}</p>
                                    <p className="text-xs text-slate-500">{standard.edition}</p>
                                    {standard.notes && <p className="mt-1 text-xs text-slate-500">{standard.notes}</p>}
                                </td>
                                <td className="px-3 py-2 text-xs">{standard.effective_from ?? 'Not verified'}</td>
                                <td className="px-3 py-2"><Badge tone="amber">{standard.applicability.replace('_', ' ')}</Badge></td>
                                <td className="px-3 py-2 text-xs">{standard.transition_method?.replace(/_/g, ' ') ?? '—'}</td>
                                <td className="px-3 py-2"><Badge tone={standard.verification_status === 'verified' ? 'green' : 'rose'}>{standard.verification_status}</Badge></td>
                                <td className="px-3 py-2"><Badge tone={standard.approved ? 'green' : 'slate'}>{standard.approved ? 'approved' : 'pending'}</Badge></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-slate-500">
                See docs/ACCOUNTING_SOURCES.md for the verification procedure, the missing Zimbabwe Treasury/PAAB sources and the production blockers.
            </p>
        </AppLayout>
    );
}
