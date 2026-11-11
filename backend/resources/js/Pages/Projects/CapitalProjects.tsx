import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/Ui';
import { money } from '../../lib';

type Project = {
    id: string;
    project_code: string;
    title: string;
    category: string;
    funding_source: string;
    approved_budget_minor: number;
    contract_value_minor: number;
    contractor_name: string;
    contractor_praz_number: string | null;
    commencement_date: string;
    expected_completion_date: string;
    physical_progress_percent: number;
    retention_rate_percent: number;
    retention_held_minor: number;
    certified_claims_minor: number;
    paid_to_date_minor: number;
    status: string;
    scope_of_works: string | null;
};

type Certificate = {
    id: string;
    certificate_number: string;
    project_code: string;
    project_title: string;
    valuation_date: string;
    gross_certified_minor: number;
    retention_deducted_minor: number;
    net_payable_minor: number;
    cumulative_progress_percent: number;
    certifying_engineer: string;
    approval_status: string;
};

type Props = {
    projects: Project[];
    certificates: Certificate[];
};

export default function CapitalProjects({ projects, certificates }: Props) {
    const [tab, setTab] = useState<'projects' | 'certificates' | 'new_certificate'>('projects');
    const [selectedProject, setSelectedProject] = useState<Project | null>(null);

    // Certificate Form State
    const [grossCertified, setGrossCertified] = useState(150000);
    const [progressPercent, setProgressPercent] = useState(75);
    const [engineerName, setEngineerName] = useState('Eng. C. Sithole (Director of Works)');
    const [processing, setProcessing] = useState(false);

    function handleCertify(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedProject) return;

        setProcessing(true);
        router.post(
            '/projects/capital/certificates',
            {
                capital_project_id: selectedProject.id,
                gross_certified: grossCertified,
                cumulative_progress_percent: progressPercent,
                certifying_engineer: engineerName,
            },
            {
                onSuccess: () => {
                    setTab('certificates');
                    setSelectedProject(null);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            }
        );
    }

    const totalBudget = projects.reduce((sum, p) => sum + Number(p.approved_budget_minor), 0);
    const totalRetention = projects.reduce((sum, p) => sum + Number(p.retention_held_minor), 0);
    const totalPaid = projects.reduce((sum, p) => sum + Number(p.paid_to_date_minor), 0);

    return (
        <AppLayout title="Devolution & Capital Infrastructure Projects">
            <Head title="Devolution & Capital Projects" />

            {/* Quick Metrics */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-blue-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Capital Portfolio</p>
                    <p className="mt-1 text-2xl font-black text-slate-900">{projects.length} Projects</p>
                    <p className="mt-0.5 text-xs text-slate-500">Roads, water reservoirs & clinics</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-emerald-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Approved Budget</p>
                    <p className="mt-1 text-2xl font-black text-emerald-700">{money(totalBudget, 'USD')}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Devolution grants & capital development</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-teal-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Disbursed to Date</p>
                    <p className="mt-1 text-2xl font-black text-teal-800">{money(totalPaid, 'USD')}</p>
                    <p className="mt-0.5 text-xs text-slate-500">IPC interim contractor disbursements</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-amber-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Retention Money Held (10%)</p>
                    <p className="mt-1 text-2xl font-black text-amber-700">{money(totalRetention, 'USD')}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Defects liability statutory security</p>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="mb-6 flex border-b border-slate-200">
                <button
                    onClick={() => setTab('projects')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'projects' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Capital Infrastructure Projects ({projects.length})
                </button>
                <button
                    onClick={() => setTab('certificates')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'certificates' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Interim Payment Certificates ({certificates.length})
                </button>
                {selectedProject && (
                    <button
                        onClick={() => setTab('new_certificate')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'new_certificate' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Issue Payment Certificate for {selectedProject.project_code}
                    </button>
                )}
            </div>

            {/* Tab 1: Capital Projects */}
            {tab === 'projects' && (
                <div className="space-y-4">
                    {projects.map((p) => (
                        <div key={p.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-xs font-bold text-blue-800">{p.project_code}</span>
                                        <span className="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-800 uppercase tracking-wide">
                                            {p.funding_source.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <h3 className="mt-1 text-base font-bold text-slate-900">{p.title}</h3>
                                </div>
                                <div className="text-right">
                                    <Badge tone="success">{p.status}</Badge>
                                    <p className="text-[11px] text-slate-500 mt-1">Contractor: <strong>{p.contractor_name}</strong></p>
                                </div>
                            </div>

                            <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                                <div>
                                    <span className="text-slate-500">Contract Value:</span>
                                    <p className="font-bold text-slate-900">{money(p.contract_value_minor, 'USD')}</p>
                                </div>
                                <div>
                                    <span className="text-slate-500">Certified Claims:</span>
                                    <p className="font-bold text-slate-900">{money(p.certified_claims_minor, 'USD')}</p>
                                </div>
                                <div>
                                    <span className="text-slate-500">10% Retention Held:</span>
                                    <p className="font-bold text-amber-700">{money(p.retention_held_minor, 'USD')}</p>
                                </div>
                                <div>
                                    <span className="text-slate-500">Paid to Date:</span>
                                    <p className="font-bold text-emerald-800">{money(p.paid_to_date_minor, 'USD')}</p>
                                </div>
                            </div>

                            {/* Progress bar */}
                            <div className="mt-4">
                                <div className="flex justify-between text-xs font-semibold mb-1">
                                    <span>Physical Progress on Site:</span>
                                    <span className="text-blue-700">{p.physical_progress_percent}%</span>
                                </div>
                                <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                    <div
                                        className="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
                                        style={{ width: `${p.physical_progress_percent}%` }}
                                    ></div>
                                </div>
                            </div>

                            <p className="mt-3 text-xs text-slate-600 italic border-t border-slate-50 pt-2">{p.scope_of_works}</p>

                            <div className="mt-4 flex justify-end">
                                <button
                                    onClick={() => {
                                        setSelectedProject(p);
                                        setProgressPercent(Number(p.physical_progress_percent) + 5);
                                        setTab('new_certificate');
                                    }}
                                    className="rounded bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-500 shadow-sm"
                                >
                                    Certify Interim Stage Claim (IPC) →
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Tab 2: Payment Certificates */}
            {tab === 'certificates' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">IPC No.</th>
                                    <th className="px-3 py-2">Project</th>
                                    <th className="px-3 py-2">Valuation Date</th>
                                    <th className="px-3 py-2">Cumulative %</th>
                                    <th className="px-3 py-2 text-right">Gross Certified</th>
                                    <th className="px-3 py-2 text-right">10% Retention</th>
                                    <th className="px-3 py-2 text-right">Net Payable</th>
                                    <th className="px-3 py-2">Certifying Engineer</th>
                                    <th className="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {certificates.map((c) => (
                                    <tr key={c.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-blue-900">{c.certificate_number}</td>
                                        <td className="px-3 py-2.5 font-medium text-slate-800">{c.project_title}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{c.valuation_date}</td>
                                        <td className="px-3 py-2.5 font-bold text-blue-700">{c.cumulative_progress_percent}%</td>
                                        <td className="px-3 py-2.5 text-right font-medium">{money(c.gross_certified_minor, 'USD')}</td>
                                        <td className="px-3 py-2.5 text-right font-medium text-amber-700">-{money(c.retention_deducted_minor, 'USD')}</td>
                                        <td className="px-3 py-2.5 text-right font-bold text-emerald-800">{money(c.net_payable_minor, 'USD')}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{c.certifying_engineer}</td>
                                        <td className="px-3 py-2.5"><Badge tone="success">{c.approval_status}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 3: New Certificate Form */}
            {tab === 'new_certificate' && selectedProject && (
                <section className="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        Issue Interim Payment Certificate for {selectedProject.project_code}
                    </h2>
                    <p className="text-xs text-slate-500 mt-1">Contractor: {selectedProject.contractor_name}</p>

                    <form onSubmit={handleCertify} className="mt-4 space-y-4 text-xs">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Gross Valuation Claim (USD $)</label>
                                <input
                                    type="number"
                                    required
                                    value={grossCertified}
                                    onChange={(e) => setGrossCertified(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-blue-600 focus:outline-none font-bold"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Cumulative Physical Progress (%)</label>
                                <input
                                    type="number"
                                    required
                                    min="1"
                                    max="100"
                                    value={progressPercent}
                                    onChange={(e) => setProgressPercent(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-blue-600 focus:outline-none font-bold"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Certifying Municipal Engineer</label>
                            <input
                                type="text"
                                required
                                value={engineerName}
                                onChange={(e) => setEngineerName(e.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        {/* Retention Calculation Breakdown */}
                        <div className="rounded bg-blue-50 border border-blue-200 p-3.5 space-y-1.5 text-xs">
                            <div className="flex justify-between">
                                <span>Gross Certified Valuation:</span>
                                <span className="font-bold">${grossCertified.toFixed(2)} USD</span>
                            </div>
                            <div className="flex justify-between text-amber-800">
                                <span>10% Statutory Retention Withheld:</span>
                                <span className="font-bold">-${(grossCertified * 0.10).toFixed(2)} USD</span>
                            </div>
                            <div className="flex justify-between border-t border-blue-200 pt-1.5 font-black text-emerald-800 text-sm">
                                <span>Net Approved for Disbursement:</span>
                                <span>${(grossCertified * 0.90).toFixed(2)} USD</span>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-3">
                            <button
                                type="button"
                                onClick={() => setTab('projects')}
                                className="rounded border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-500 shadow-md"
                            >
                                {processing ? 'Certifying...' : 'Certify & Approve Payment Voucher →'}
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}

