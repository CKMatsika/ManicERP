import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState } from '../../Components/Ui';
import { money } from '../../lib';

type Cemetery = {
    id: string;
    name: string;
    location: string;
    status: string;
    total_plots: number;
    occupied_plots: number;
};

type GravePlot = {
    id: string;
    cemetery_id: string;
    cemetery_name: string;
    section: string;
    block: string;
    plot_number: string;
    depth: string;
    status: string;
    reserved_by: string | null;
    reserved_contact: string | null;
    plot_fee_minor: number;
};

type Interment = {
    id: string;
    interment_order_number: string;
    deceased_name: string;
    deceased_national_id: string | null;
    date_of_death: string;
    date_of_interment: string;
    burial_permit_number: string;
    cause_of_death: string | null;
    undertaker_name: string | null;
    next_of_kin_name: string;
    next_of_kin_phone: string;
    interment_fee_minor: number;
    plot_number: string;
    section: string;
    depth: string;
    cemetery_name: string;
    status: string;
    notes: string | null;
};

type Props = {
    cemeteries: Cemetery[];
    plots: GravePlot[];
    interments: Interment[];
};

export default function Cemeteries({ cemeteries, plots, interments }: Props) {
    const [tab, setTab] = useState<'plots' | 'interments' | 'new_order'>('plots');
    const [selectedPlot, setSelectedPlot] = useState<GravePlot | null>(null);

    // Form state
    const [deceasedName, setDeceasedName] = useState('');
    const [nationalId, setNationalId] = useState('');
    const [dateOfDeath, setDateOfDeath] = useState(new Date().toISOString().split('T')[0]);
    const [dateOfInterment, setDateOfInterment] = useState(new Date().toISOString().split('T')[0]);
    const [burialPermit, setBurialPermit] = useState('');
    const [causeOfDeath, setCauseOfDeath] = useState('Natural causes');
    const [undertaker, setUndertaker] = useState('Doves Funeral Assurance');
    const [nextOfKin, setNextOfKin] = useState('');
    const [nextOfKinPhone, setNextOfKinPhone] = useState('');
    const [intermentFee, setIntermentFee] = useState(120);
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedPlot) return;

        setProcessing(true);
        router.post(
            '/community/cemeteries/interments',
            {
                grave_plot_id: selectedPlot.id,
                deceased_name: deceasedName,
                deceased_national_id: nationalId,
                date_of_death: dateOfDeath,
                date_of_interment: dateOfInterment,
                burial_permit_number: burialPermit,
                cause_of_death: causeOfDeath,
                undertaker_name: undertaker,
                next_of_kin_name: nextOfKin,
                next_of_kin_phone: nextOfKinPhone,
                interment_fee: intermentFee,
                notes,
            },
            {
                onSuccess: () => {
                    setTab('interments');
                    setSelectedPlot(null);
                    setDeceasedName('');
                    setBurialPermit('');
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            }
        );
    }

    return (
        <AppLayout title="Cemeteries & Grave Register">
            <Head title="Cemeteries & Grave Register" />

            {/* Cemetery Summary Cards */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {cemeteries.map((c) => (
                    <div key={c.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Municipal Cemetery</span>
                            <span className="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                {c.status}
                            </span>
                        </div>
                        <h3 className="mt-1 text-base font-bold text-slate-900">{c.name}</h3>
                        <p className="text-xs text-slate-500">{c.location}</p>
                        <div className="mt-3 flex items-center justify-between text-xs border-t border-slate-100 pt-2 font-medium">
                            <span className="text-slate-600">Total Plots: {c.total_plots}</span>
                            <span className="text-emerald-700">Available: {c.total_plots - c.occupied_plots}</span>
                        </div>
                    </div>
                ))}
            </div>

            {/* Tab Navigation */}
            <div className="mb-6 flex border-b border-slate-200">
                <button
                    onClick={() => setTab('plots')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'plots' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Grave Plot Directory ({plots.length})
                </button>
                <button
                    onClick={() => setTab('interments')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'interments' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Burial & Interment Orders ({interments.length})
                </button>
                {selectedPlot && (
                    <button
                        onClick={() => setTab('new_order')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'new_order' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Issue Burial Order for {selectedPlot.plot_number}
                    </button>
                )}
            </div>

            {/* Tab 1: Grave Plots */}
            {tab === 'plots' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900">Municipal Grave Plots & Lawn Sections</h2>
                            <p className="text-xs text-slate-500">Select an available or reserved plot to issue an official municipal burial permit</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Plot Number</th>
                                    <th className="px-3 py-2">Cemetery</th>
                                    <th className="px-3 py-2">Section & Block</th>
                                    <th className="px-3 py-2">Depth</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Reserved For / Owner</th>
                                    <th className="px-3 py-2 text-right">Fee</th>
                                    <th className="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {plots.map((p) => (
                                    <tr key={p.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{p.plot_number}</td>
                                        <td className="px-3 py-2.5">{p.cemetery_name}</td>
                                        <td className="px-3 py-2.5">{p.section} · {p.block}</td>
                                        <td className="px-3 py-2.5 capitalize">{p.depth.replace('_', ' ')}</td>
                                        <td className="px-3 py-2.5">
                                            <Badge tone={p.status === 'interred' ? 'danger' : p.status === 'reserved' ? 'warning' : 'success'}>
                                                {p.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-600">{p.reserved_by ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-right font-medium">{money(p.plot_fee_minor, 'USD')}</td>
                                        <td className="px-3 py-2.5 text-right">
                                            {p.status !== 'interred' ? (
                                                <button
                                                    onClick={() => {
                                                        setSelectedPlot(p);
                                                        setTab('new_order');
                                                    }}
                                                    className="rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-500"
                                                >
                                                    Issue Burial Order →
                                                </button>
                                            ) : (
                                                <span className="text-[11px] text-slate-400">Interred</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 2: Interments Register */}
            {tab === 'interments' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900">Statutory Burial Orders & Interments Register</h2>
                            <p className="text-xs text-slate-500">Official local authority register of deaths and burial authorizations</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Burial Order No.</th>
                                    <th className="px-3 py-2">Deceased Name</th>
                                    <th className="px-3 py-2">Permit / Hospital Ref</th>
                                    <th className="px-3 py-2">Cemetery & Plot</th>
                                    <th className="px-3 py-2">Interment Date</th>
                                    <th className="px-3 py-2">Undertaker</th>
                                    <th className="px-3 py-2">Next of Kin</th>
                                    <th className="px-3 py-2 text-right">Fee</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {interments.map((i) => (
                                    <tr key={i.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{i.interment_order_number}</td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-bold text-slate-900">{i.deceased_name}</p>
                                            <p className="text-[10px] text-slate-400">ID: {i.deceased_national_id ?? 'N/A'}</p>
                                        </td>
                                        <td className="px-3 py-2.5 font-mono text-[11px] text-slate-600">{i.burial_permit_number}</td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-semibold text-slate-800">{i.plot_number}</p>
                                            <p className="text-[10px] text-slate-500">{i.cemetery_name}</p>
                                        </td>
                                        <td className="px-3 py-2.5">{i.date_of_interment}</td>
                                        <td className="px-3 py-2.5">{i.undertaker_name ?? 'Family Arranged'}</td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-medium">{i.next_of_kin_name}</p>
                                            <p className="text-[10px] text-slate-500">{i.next_of_kin_phone}</p>
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-medium text-emerald-800">{money(i.interment_fee_minor, 'USD')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 3: New Burial Order Form */}
            {tab === 'new_order' && selectedPlot && (
                <section className="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="border-b border-slate-100 pb-4">
                        <span className="rounded bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                            Plot: {selectedPlot.plot_number} ({selectedPlot.section})
                        </span>
                        <h2 className="mt-2 text-lg font-bold text-slate-900">Issue Municipal Burial & Interment Order</h2>
                        <p className="text-xs text-slate-500">
                            Urban Councils Act [Cap 29:15] Statutory Cemetery Allocation & Public Health Clearance
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="mt-5 space-y-4 text-xs">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Deceased Full Name</label>
                                <input
                                    type="text"
                                    required
                                    value={deceasedName}
                                    onChange={(e) => setDeceasedName(e.target.value)}
                                    placeholder="e.g. Late Farai Mutasa"
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">National ID Number</label>
                                <input
                                    type="text"
                                    value={nationalId}
                                    onChange={(e) => setNationalId(e.target.value)}
                                    placeholder="e.g. 63-098214-G-42"
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Date of Death</label>
                                <input
                                    type="date"
                                    required
                                    value={dateOfDeath}
                                    onChange={(e) => setDateOfDeath(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Date of Interment</label>
                                <input
                                    type="date"
                                    required
                                    value={dateOfInterment}
                                    onChange={(e) => setDateOfInterment(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Burial Permit Ref No.</label>
                                <input
                                    type="text"
                                    required
                                    value={burialPermit}
                                    onChange={(e) => setBurialPermit(e.target.value)}
                                    placeholder="e.g. BP-8910/2026"
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Certified Cause of Death</label>
                                <input
                                    type="text"
                                    value={causeOfDeath}
                                    onChange={(e) => setCauseOfDeath(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Undertaker / Funeral Assurance</label>
                                <select
                                    value={undertaker}
                                    onChange={(e) => setUndertaker(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                >
                                    <option value="Doves Funeral Assurance">Doves Funeral Assurance</option>
                                    <option value="Nyaradzo Funeral Services">Nyaradzo Funeral Services</option>
                                    <option value="Moonlight Funeral Services">Moonlight Funeral Services</option>
                                    <option value="First Funeral Services">First Funeral Services</option>
                                    <option value="Family / Self Arranged">Family / Self Arranged</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Next of Kin Full Name</label>
                                <input
                                    type="text"
                                    required
                                    value={nextOfKin}
                                    onChange={(e) => setNextOfKin(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Next of Kin Phone</label>
                                <input
                                    type="text"
                                    required
                                    value={nextOfKinPhone}
                                    onChange={(e) => setNextOfKinPhone(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Interment Fee (USD $)</label>
                                <input
                                    type="number"
                                    required
                                    value={intermentFee}
                                    onChange={(e) => setIntermentFee(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none font-bold text-emerald-700"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Supervisor Inspection Notes</label>
                            <textarea
                                rows={2}
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="e.g. 8ft depth verified by cemetery supervisor. All grave dimensions conforming."
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-3 pt-3">
                            <button
                                type="button"
                                onClick={() => setTab('plots')}
                                className="rounded border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-500 shadow-md"
                            >
                                {processing ? 'Processing...' : 'Authorize & Issue Burial Order →'}
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}

