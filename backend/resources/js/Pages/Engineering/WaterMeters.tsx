import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type WaterMeter = {
    id: string;
    meter_number: string;
    serial_number: string;
    size: string;
    account_number: string;
    property: string;
    suburb: string;
    address: string;
    last_reading: number;
    last_reading_date?: string;
    avg_consumption: number;
    category: string;
    status: string;
};

type Reading = {
    id: string;
    meter_number?: string;
    account_number?: string;
    property?: string;
    period: string;
    previous: number;
    current: number;
    consumption: number;
    anomaly: string;
    billed: string;
    date?: string;
};

type Summary = {
    total_meters_count: number;
    active_meters_count: number;
    burst_alerts_count: number;
    meters: WaterMeter[];
    recent_readings: Reading[];
};

type Props = {
    summary: Summary;
};

export default function WaterMeters({ summary }: Props) {
    const [showCaptureModal, setShowCaptureModal] = useState(false);
    const [showBillingModal, setShowBillingModal] = useState(false);

    const [captureForm, setCaptureForm] = useState({
        water_meter_id: summary.meters[0]?.id || '',
        current_reading: '',
        reading_period: new Date().toISOString().slice(0, 7),
        gps_lat: '',
        gps_lng: '',
        notes: '',
    });

    const [billingPeriod, setBillingPeriod] = useState(
        new Date().toISOString().slice(0, 7)
    );

    const handleCaptureSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/engineering/water-meters/capture', captureForm, {
            onSuccess: () => setShowCaptureModal(false),
        });
    };

    const handleBillingSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/engineering/water-meters/billing-run', {
            reading_period: billingPeriod,
        }, {
            onSuccess: () => setShowBillingModal(false),
        });
    };

    return (
        <AppLayout title="Water Metering & AMR Billing">
            <Head title="Water Metering & AMR Billing - ManicERP" />

            <div className="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-800">
                                MUNICIPAL WATER & RETICULATION
                            </span>
                            <span className="text-xs text-slate-500 font-medium">Inverted Block Tariff (IBT) • Leak Geofencing</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 mt-1">
                            Water Meter Management & Automated Consumption Billing
                        </h1>
                        <p className="text-sm text-slate-600 mt-0.5">
                            Real-time meter reading capture, burst & leak anomaly detection, and tiered stepped water tariffs.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setShowBillingModal(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 shadow-sm"
                        >
                            <span>⚡</span>
                            <span>Generate Water Billing Run</span>
                        </button>
                        <button
                            onClick={() => {
                                setCaptureForm({ ...captureForm, water_meter_id: summary.meters[0]?.id || '' });
                                setShowCaptureModal(true);
                            }}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-cyan-700 hover:bg-cyan-800 text-white rounded-lg text-sm font-medium shadow-sm transition"
                        >
                            <span>📱</span>
                            <span>Capture Meter Reading</span>
                        </button>
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Water Connections</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{summary.total_meters_count} Meters</div>
                        <div className="text-xs text-emerald-600 font-medium mt-1">{summary.active_meters_count} Active & Metered</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Burst / Leak Anomaly Alerts</div>
                        <div className={`mt-2 text-2xl font-bold ${summary.burst_alerts_count > 0 ? 'text-rose-600' : 'text-slate-900'}`}>
                            {summary.burst_alerts_count} Leak Alert(s)
                        </div>
                        <div className="text-xs text-rose-600 font-medium mt-1">Exceeding 220% average monthly consumption</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Stepped Domestic Tariff</div>
                        <div className="mt-2 text-lg font-bold text-cyan-800">Lifeline: $0.45 / m³</div>
                        <div className="text-xs text-slate-500 mt-1">Tier 2: $0.85 • Tier 3: $1.40 • Tier 4: $2.10</div>
                    </div>
                </div>

                {/* Tariff Explainer Banner */}
                <div className="bg-gradient-to-r from-cyan-900 to-blue-900 rounded-xl p-4 text-white flex flex-col md:flex-row items-start md:items-center justify-between gap-3 shadow-sm">
                    <div>
                        <div className="text-xs uppercase font-bold text-cyan-300">Council Municipal Water By-Laws</div>
                        <div className="font-semibold text-sm">Domestic Lifeline & Conservation Stepped Block Tariff</div>
                        <div className="text-xs text-cyan-100">
                            Band 1 (0-10m³): $0.45/m³ | Band 2 (11-25m³): $0.85/m³ | Band 3 (26-50m³): $1.40/m³ | Band 4 (51m³+): $2.10/m³. Sanitation surcharge: 25%.
                        </div>
                    </div>
                    <span className="px-3 py-1 bg-white/20 rounded text-xs font-mono font-bold whitespace-nowrap">
                        ZIMBABWE S.I. COMPLIANT
                    </span>
                </div>

                {/* Meters Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">Municipal Water Meter Registry</h2>
                            <p className="text-xs text-slate-500 mt-0.5">Physical dial readings, geofences, and ratepayer account bindings</p>
                        </div>
                        <span className="text-xs font-medium text-slate-600 bg-white px-2.5 py-1 rounded border border-slate-200">
                            {summary.meters.length} Installed Meters
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-slate-600 font-semibold text-left">
                                <tr>
                                    <th className="py-3 px-4">Meter No.</th>
                                    <th className="py-3 px-4">Serial / Size</th>
                                    <th className="py-3 px-4">Stand / Suburb</th>
                                    <th className="py-3 px-4">Ratepayer Account</th>
                                    <th className="py-3 px-4 text-right">Last Index (m³)</th>
                                    <th className="py-3 px-4 text-right">3-Mo Avg (m³)</th>
                                    <th className="py-3 px-4 text-center">Category</th>
                                    <th className="py-3 px-4 text-center">Status</th>
                                    <th className="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {summary.meters.map((meter) => (
                                    <tr key={meter.id} className="hover:bg-slate-50/75 transition">
                                        <td className="py-3.5 px-4 font-mono font-bold text-cyan-900 whitespace-nowrap">
                                            {meter.meter_number}
                                        </td>
                                        <td className="py-3.5 px-4 text-xs">
                                            <div className="font-mono text-slate-900">{meter.serial_number}</div>
                                            <div className="text-slate-500">{meter.size}</div>
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <div className="font-medium text-slate-900">{meter.property}</div>
                                            <div className="text-xs text-slate-500">{meter.suburb}</div>
                                        </td>
                                        <td className="py-3.5 px-4 font-mono text-emerald-800 text-xs">
                                            {meter.account_number}
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-bold text-slate-900">
                                            {meter.last_reading} m³
                                        </td>
                                        <td className="py-3.5 px-4 text-right text-slate-600">
                                            {meter.avg_consumption} m³
                                        </td>
                                        <td className="py-3.5 px-4 text-center">
                                            <span className="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-cyan-50 text-cyan-800 border border-cyan-200 uppercase">
                                                {meter.category}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-center">
                                            <span className="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 uppercase">
                                                {meter.status}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-right">
                                            <button
                                                onClick={() => {
                                                    setCaptureForm({
                                                        ...captureForm,
                                                        water_meter_id: meter.id,
                                                        current_reading: (meter.last_reading + 5).toFixed(1),
                                                    });
                                                    setShowCaptureModal(true);
                                                }}
                                                className="text-xs font-medium text-cyan-700 hover:text-cyan-900 underline"
                                            >
                                                Log Reading
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Field Readings Logs with Anomaly Badges */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-base font-bold text-slate-900 mb-4">Recent Meter Reading Batches & Burst Alerts</h2>
                    <div className="divide-y divide-slate-100 text-sm">
                        {summary.recent_readings.map((r) => (
                            <div key={r.id} className="py-3 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className={`w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold ${
                                        r.anomaly === 'burst_alert' ? 'bg-rose-100 text-rose-700 ring-2 ring-rose-400' :
                                        r.anomaly === 'stuck_meter' ? 'bg-amber-100 text-amber-700' :
                                        'bg-cyan-100 text-cyan-700'
                                    }`}>
                                        {r.anomaly === 'burst_alert' ? '🚨' : r.anomaly === 'stuck_meter' ? '⏸️' : '💧'}
                                    </span>
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {r.meter_number} ({r.property}) • Consumption: {r.consumption} m³
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            Period: {r.period} • Dial: {r.previous} m³ &rarr; {r.current} m³ • {r.date}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    {r.anomaly === 'burst_alert' && (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 uppercase mr-2">
                                            BURST / LEAK ALERT
                                        </span>
                                    )}
                                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
                                        r.billed === 'billed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'
                                    }`}>
                                        {r.billed === 'billed' ? 'BILLED' : 'PENDING RUN'}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Modal: Capture Reading */}
            {showCaptureModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Capture Meter Field Reading</h3>
                            <button onClick={() => setShowCaptureModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleCaptureSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Select Water Meter</label>
                                <select
                                    value={captureForm.water_meter_id}
                                    onChange={(e) => setCaptureForm({ ...captureForm, water_meter_id: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-cyan-500"
                                >
                                    {summary.meters.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.meter_number} - {m.property} ({m.suburb}) [Last: {m.last_reading} m³]
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Current Dial Reading (m³)</label>
                                    <input
                                        type="number"
                                        required
                                        step="0.001"
                                        value={captureForm.current_reading}
                                        onChange={(e) => setCaptureForm({ ...captureForm, current_reading: e.target.value })}
                                        placeholder="e.g. 158.400"
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-cyan-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Billing Period</label>
                                    <input
                                        type="month"
                                        required
                                        value={captureForm.reading_period}
                                        onChange={(e) => setCaptureForm({ ...captureForm, reading_period: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-cyan-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Field Reader Notes / Physical Observations</label>
                                <textarea
                                    rows={2}
                                    value={captureForm.notes}
                                    onChange={(e) => setCaptureForm({ ...captureForm, notes: e.target.value })}
                                    placeholder="e.g. Dial glass clear, no surface dampness detected."
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-cyan-500"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowCaptureModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-cyan-700 hover:bg-cyan-800 rounded-lg"
                                >
                                    Save & Check Anomalies
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Billing Run */}
            {showBillingModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Execute Monthly Water Billing Run</h3>
                            <button onClick={() => setShowBillingModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>
                        <p className="text-xs text-slate-600">
                            Processes all verified pending readings for the selected period, calculates tiered IBT tariffs + 25% sanitation surcharge, and posts water bills to customer rate accounts.
                        </p>
                        <form onSubmit={handleBillingSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Billing Period (YYYY-MM)</label>
                                <input
                                    type="month"
                                    required
                                    value={billingPeriod}
                                    onChange={(e) => setBillingPeriod(e.target.value)}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-cyan-500"
                                />
                            </div>
                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowBillingModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-cyan-700 hover:bg-cyan-800 rounded-lg"
                                >
                                    Generate Water Bills
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

