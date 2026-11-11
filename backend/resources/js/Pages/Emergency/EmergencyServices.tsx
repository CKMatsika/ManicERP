import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/Ui';
import { money } from '../../lib';

type Incident = {
    id: string;
    incident_number: string;
    incident_type: string;
    caller_name: string;
    caller_phone: string;
    location_address: string;
    suburb: string;
    station_code: string;
    appliances_dispatched: string;
    dispatched_at: string;
    arrived_at: string | null;
    contained_at: string | null;
    casualties: number;
    service_charge_minor: number;
    billed_account_number: string | null;
    status: string;
    incident_report: string | null;
};

type Inspection = {
    id: string;
    certificate_number: string;
    premises_name: string;
    stand_number: string;
    owner_name: string;
    occupancy_type: string;
    compliance_status: string;
    inspection_date: string;
    expires_at: string;
    officer_name: string;
    inspection_fee_minor: number;
};

type Hydrant = {
    id: string;
    hydrant_code: string;
    location_address: string;
    suburb: string;
    static_pressure_bar: number;
    flow_rate_lpm: number;
    status: string;
    last_inspected_at: string | null;
    inspected_by: string | null;
};

type Props = {
    incidents: Incident[];
    inspections: Inspection[];
    hydrants: Hydrant[];
};

export default function EmergencyServices({ incidents, inspections, hydrants }: Props) {
    const [tab, setTab] = useState<'incidents' | 'inspections' | 'hydrants' | 'new_incident'>('incidents');

    // Incident Form State
    const [incidentType, setIncidentType] = useState('structural_fire');
    const [callerName, setCallerName] = useState('');
    const [callerPhone, setCallerPhone] = useState('');
    const [address, setAddress] = useState('');
    const [suburb, setSuburb] = useState('CBD');
    const [appliances, setAppliances] = useState('Major Tender 1, Water Bowser 3');
    const [serviceCharge, setServiceCharge] = useState(350);
    const [report, setReport] = useState('');
    const [processing, setProcessing] = useState(false);

    function handleDispatch(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.post(
            '/emergency/incidents',
            {
                incident_type: incidentType,
                caller_name: callerName,
                caller_phone: callerPhone,
                location_address: address,
                suburb,
                appliances_dispatched: appliances,
                service_charge: serviceCharge,
                incident_report: report,
            },
            {
                onSuccess: () => {
                    setTab('incidents');
                    setCallerName('');
                    setAddress('');
                    setReport('');
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            }
        );
    }

    return (
        <AppLayout title="Emergency Services & Fire Brigade">
            <Head title="Emergency Services & Fire Brigade" />

            {/* Quick Metrics */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-rose-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Emergency Incidents</p>
                    <p className="mt-1 text-2xl font-black text-slate-900">{incidents.length}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Active fire & ambulance calls</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-blue-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Fire Hydrants Network</p>
                    <p className="mt-1 text-2xl font-black text-slate-900">{hydrants.length}</p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {hydrants.filter((h) => h.status === 'operational').length} operational in zone
                    </p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-emerald-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Safety Certificates</p>
                    <p className="mt-1 text-2xl font-black text-slate-900">{inspections.length}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Commercial compliance audits</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-amber-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Response Readiness</p>
                    <p className="mt-1 text-2xl font-black text-emerald-600">&lt; 8 Mins</p>
                    <p className="mt-0.5 text-xs text-slate-500">Central station response time</p>
                </div>
            </div>

            {/* Tab Controls */}
            <div className="mb-6 flex items-center justify-between border-b border-slate-200">
                <div className="flex">
                    <button
                        onClick={() => setTab('incidents')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'incidents' ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Incidents Dispatch Log ({incidents.length})
                    </button>
                    <button
                        onClick={() => setTab('inspections')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'inspections' ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Fire Safety Certificates ({inspections.length})
                    </button>
                    <button
                        onClick={() => setTab('hydrants')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'hydrants' ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Municipal Fire Hydrant Grid ({hydrants.length})
                    </button>
                </div>

                <button
                    onClick={() => setTab('new_incident')}
                    className="mb-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-500 shadow-md flex items-center gap-1"
                >
                    <span>+ Log Emergency Dispatch</span>
                </button>
            </div>

            {/* Tab 1: Incidents Log */}
            {tab === 'incidents' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Incident No.</th>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Location & Suburb</th>
                                    <th className="px-3 py-2">Appliances Dispatched</th>
                                    <th className="px-3 py-2">Caller / Reporter</th>
                                    <th className="px-3 py-2">Dispatched At</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2 text-right">Fee</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {incidents.map((inc) => (
                                    <tr key={inc.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{inc.incident_number}</td>
                                        <td className="px-3 py-2.5 capitalize font-medium text-slate-800">
                                            {inc.incident_type.replace('_', ' ')}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-medium text-slate-800">{inc.location_address}</p>
                                            <p className="text-[10px] text-slate-500">{inc.suburb}</p>
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-600">{inc.appliances_dispatched}</td>
                                        <td className="px-3 py-2.5">
                                            <p>{inc.caller_name}</p>
                                            <p className="text-[10px] text-slate-400">{inc.caller_phone}</p>
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-600">{inc.dispatched_at}</td>
                                        <td className="px-3 py-2.5">
                                            <Badge tone={inc.status === 'closed' ? 'success' : 'warning'}>{inc.status}</Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-medium text-slate-900">
                                            {inc.service_charge_minor > 0 ? money(inc.service_charge_minor, 'USD') : 'Statutory Free'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 2: Fire Safety Inspections */}
            {tab === 'inspections' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Certificate No.</th>
                                    <th className="px-3 py-2">Premises</th>
                                    <th className="px-3 py-2">Stand No.</th>
                                    <th className="px-3 py-2">Occupancy Class</th>
                                    <th className="px-3 py-2">Inspection Date</th>
                                    <th className="px-3 py-2">Expires At</th>
                                    <th className="px-3 py-2">Compliance Status</th>
                                    <th className="px-3 py-2 text-right">Fee</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {inspections.map((ins) => (
                                    <tr key={ins.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{ins.certificate_number}</td>
                                        <td className="px-3 py-2.5 font-bold text-slate-900">{ins.premises_name}</td>
                                        <td className="px-3 py-2.5 font-mono text-slate-600">{ins.stand_number}</td>
                                        <td className="px-3 py-2.5">{ins.occupancy_type}</td>
                                        <td className="px-3 py-2.5">{ins.inspection_date}</td>
                                        <td className="px-3 py-2.5">{ins.expires_at}</td>
                                        <td className="px-3 py-2.5">
                                            <Badge tone={ins.compliance_status === 'compliant' ? 'success' : 'danger'}>
                                                {ins.compliance_status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-medium">{money(ins.inspection_fee_minor, 'USD')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 3: Hydrants Grid */}
            {tab === 'hydrants' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Hydrant Code</th>
                                    <th className="px-3 py-2">Location Address</th>
                                    <th className="px-3 py-2">Suburb</th>
                                    <th className="px-3 py-2">Static Pressure</th>
                                    <th className="px-3 py-2">Flow Rate</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Last Inspected</th>
                                    <th className="px-3 py-2">Inspecting Officer</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {hydrants.map((h) => (
                                    <tr key={h.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-blue-800">{h.hydrant_code}</td>
                                        <td className="px-3 py-2.5 font-medium">{h.location_address}</td>
                                        <td className="px-3 py-2.5">{h.suburb}</td>
                                        <td className="px-3 py-2.5 font-bold text-slate-800">{h.static_pressure_bar} Bar</td>
                                        <td className="px-3 py-2.5 font-bold text-slate-800">{h.flow_rate_lpm} L/min</td>
                                        <td className="px-3 py-2.5">
                                            <Badge tone={h.status === 'operational' ? 'success' : 'warning'}>{h.status}</Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-600">{h.last_inspected_at ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{h.inspected_by ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 4: Dispatch Form */}
            {tab === 'new_incident' && (
                <section className="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        Emergency Dispatch & Incident Capture
                    </h2>
                    <form onSubmit={handleDispatch} className="mt-4 space-y-4 text-xs">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Incident Category</label>
                                <select
                                    value={incidentType}
                                    onChange={(e) => {
                                        setIncidentType(e.target.value);
                                        if (e.target.value === 'ambulance_run') setServiceCharge(45);
                                        else if (e.target.value === 'structural_fire') setServiceCharge(350);
                                        else setServiceCharge(150);
                                    }}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                                >
                                    <option value="structural_fire">Structural Fire (Building)</option>
                                    <option value="veld_fire">Veld / Grass Fire</option>
                                    <option value="road_traffic_accident">Road Traffic Accident (RTA Extrication)</option>
                                    <option value="ambulance_run">Ambulance Medical Emergency</option>
                                    <option value="chemical_hazard">Chemical / Industrial Hazard</option>
                                </select>
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Service Fee (USD $)</label>
                                <input
                                    type="number"
                                    value={serviceCharge}
                                    onChange={(e) => setServiceCharge(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none font-bold text-rose-700"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Caller Full Name</label>
                                <input
                                    type="text"
                                    required
                                    value={callerName}
                                    onChange={(e) => setCallerName(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Caller Contact Phone</label>
                                <input
                                    type="text"
                                    required
                                    value={callerPhone}
                                    onChange={(e) => setCallerPhone(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div className="col-span-2">
                                <label className="block font-medium text-slate-700">Location Address</label>
                                <input
                                    type="text"
                                    required
                                    value={address}
                                    onChange={(e) => setAddress(e.target.value)}
                                    placeholder="e.g. 14 Aerodrome Road, Stand 104"
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Suburb / Zone</label>
                                <input
                                    type="text"
                                    required
                                    value={suburb}
                                    onChange={(e) => setSuburb(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Appliances & Crews Dispatched</label>
                            <input
                                type="text"
                                required
                                value={appliances}
                                onChange={(e) => setAppliances(e.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Initial Radio Incident Report</label>
                            <textarea
                                rows={2}
                                value={report}
                                onChange={(e) => setReport(e.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-rose-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-3 pt-3">
                            <button
                                type="button"
                                onClick={() => setTab('incidents')}
                                className="rounded border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-rose-600 px-5 py-2 text-xs font-bold text-white hover:bg-rose-500 shadow-md"
                            >
                                {processing ? 'Dispatching...' : 'Dispatch Appliance & Log Run →'}
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}

