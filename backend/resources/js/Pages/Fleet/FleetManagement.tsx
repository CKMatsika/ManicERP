import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge } from '../../Components/Ui';
import { money } from '../../lib';

type Vehicle = {
    id: string;
    registration_number: string;
    make: string | null;
    model: string | null;
    plant_type: string;
    department: string;
    assigned_driver: string | null;
    current_odometer: number;
    fuel_type: string;
    status: string;
};

type FuelCoupon = {
    id: string;
    coupon_serial: string;
    registration_number: string;
    make: string | null;
    model: string | null;
    issue_date: string;
    litres: number;
    fuel_type: string;
    purpose: string;
    authorized_by: string;
    driver_name: string;
    cost_minor: number;
    status: string;
};

type LogbookEntry = {
    id: string;
    registration_number: string;
    trip_date: string;
    driver_name: string;
    purpose: string;
    start_odometer: number;
    end_odometer: number;
    distance_km: number;
};

type Props = {
    vehicles: Vehicle[];
    coupons: FuelCoupon[];
    logbooks: LogbookEntry[];
};

export default function FleetManagement({ vehicles, coupons, logbooks }: Props) {
    const [tab, setTab] = useState<'vehicles' | 'coupons' | 'logbooks' | 'new_coupon'>('vehicles');
    const [selectedVehicle, setSelectedVehicle] = useState<Vehicle | null>(null);

    // Coupon Form State
    const [litres, setLitres] = useState(100);
    const [fuelType, setFuelType] = useState('diesel');
    const [purpose, setPurpose] = useState('Routine refuse collection and landfill transport');
    const [driverName, setDriverName] = useState('');
    const [ratePerLitre, setRatePerLitre] = useState(1.60);
    const [processing, setProcessing] = useState(false);

    function handleIssueCoupon(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedVehicle) return;

        setProcessing(true);
        router.post(
            '/fleet/coupons',
            {
                vehicle_id: selectedVehicle.id,
                litres,
                fuel_type: fuelType,
                purpose,
                driver_name: driverName || selectedVehicle.assigned_driver || 'Council Driver',
                rate_per_litre: ratePerLitre,
            },
            {
                onSuccess: () => {
                    setTab('coupons');
                    setSelectedVehicle(null);
                    setProcessing(false);
                },
                onError: () => setProcessing(false),
            }
        );
    }

    return (
        <AppLayout title="Fleet & Heavy Plant Management">
            <Head title="Fleet & Heavy Plant Management" />

            {/* Quick Metrics */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-amber-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Council Fleet & Plant</p>
                    <p className="mt-1 text-2xl font-black text-slate-900">{vehicles.length}</p>
                    <p className="mt-0.5 text-xs text-slate-500">Heavy plant, tippers & service vehicles</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-emerald-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Fuel Disbursed (MTD)</p>
                    <p className="mt-1 text-2xl font-black text-emerald-700">
                        {coupons.reduce((sum, c) => sum + Number(c.litres), 0).toFixed(0)} L
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {money(coupons.reduce((sum, c) => sum + Number(c.cost_minor), 0), 'USD')} authorized
                    </p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-blue-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Fleet Availability</p>
                    <p className="mt-1 text-2xl font-black text-blue-700">
                        {Math.round((vehicles.filter((v) => v.status === 'active').length / Math.max(1, vehicles.length)) * 100)}%
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">Operational readiness rate</p>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-l-4 border-l-teal-500">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Distance Logged</p>
                    <p className="mt-1 text-2xl font-black text-teal-800">
                        {logbooks.reduce((sum, l) => sum + Number(l.distance_km), 0)} km
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">Digital logbook trip audits</p>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="mb-6 flex border-b border-slate-200">
                <button
                    onClick={() => setTab('vehicles')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'vehicles' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Vehicles & Heavy Plant Register ({vehicles.length})
                </button>
                <button
                    onClick={() => setTab('coupons')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'coupons' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Fuel Coupon Disbursements ({coupons.length})
                </button>
                <button
                    onClick={() => setTab('logbooks')}
                    className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                        tab === 'logbooks' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                    }`}
                >
                    Trip Logbooks ({logbooks.length})
                </button>
                {selectedVehicle && (
                    <button
                        onClick={() => setTab('new_coupon')}
                        className={`border-b-2 px-4 py-2.5 text-xs font-bold transition ${
                            tab === 'new_coupon' ? 'border-amber-600 text-amber-700' : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Issue Fuel Coupon for {selectedVehicle.registration_number}
                    </button>
                )}
            </div>

            {/* Tab 1: Vehicles Register */}
            {tab === 'vehicles' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Reg. Number</th>
                                    <th className="px-3 py-2">Make & Model</th>
                                    <th className="px-3 py-2">Plant / Vehicle Type</th>
                                    <th className="px-3 py-2">Department</th>
                                    <th className="px-3 py-2">Assigned Driver</th>
                                    <th className="px-3 py-2">Current Odometer</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2 text-right">Fuel Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {vehicles.map((v) => (
                                    <tr key={v.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{v.registration_number}</td>
                                        <td className="px-3 py-2.5 font-semibold text-slate-800">{v.make} {v.model}</td>
                                        <td className="px-3 py-2.5 capitalize">{v.plant_type.replace('_', ' ')}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{v.department}</td>
                                        <td className="px-3 py-2.5">{v.assigned_driver ?? 'Pooled'}</td>
                                        <td className="px-3 py-2.5 font-mono font-medium text-slate-700">
                                            {v.current_odometer.toLocaleString()} {v.plant_type.includes('grader') ? 'Hrs' : 'km'}
                                        </td>
                                        <td className="px-3 py-2.5"><Badge tone="success">{v.status}</Badge></td>
                                        <td className="px-3 py-2.5 text-right">
                                            <button
                                                onClick={() => {
                                                    setSelectedVehicle(v);
                                                    setDriverName(v.assigned_driver ?? '');
                                                    setTab('new_coupon');
                                                }}
                                                className="rounded bg-amber-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-amber-500"
                                            >
                                                Issue Fuel Coupon →
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 2: Fuel Coupons */}
            {tab === 'coupons' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Coupon Serial</th>
                                    <th className="px-3 py-2">Vehicle</th>
                                    <th className="px-3 py-2">Issue Date</th>
                                    <th className="px-3 py-2">Litres</th>
                                    <th className="px-3 py-2">Purpose / Municipal Route</th>
                                    <th className="px-3 py-2">Driver</th>
                                    <th className="px-3 py-2">Authorized By</th>
                                    <th className="px-3 py-2 text-right">Value</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {coupons.map((c) => (
                                    <tr key={c.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-amber-800">{c.coupon_serial}</td>
                                        <td className="px-3 py-2.5 font-semibold text-slate-900">{c.registration_number}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{c.issue_date}</td>
                                        <td className="px-3 py-2.5 font-bold text-slate-900">{c.litres} L ({c.fuel_type})</td>
                                        <td className="px-3 py-2.5 text-slate-700">{c.purpose}</td>
                                        <td className="px-3 py-2.5">{c.driver_name}</td>
                                        <td className="px-3 py-2.5 text-slate-500">{c.authorized_by}</td>
                                        <td className="px-3 py-2.5 text-right font-medium text-emerald-800">{money(c.cost_minor, 'USD')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 3: Logbooks */}
            {tab === 'logbooks' && (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Vehicle</th>
                                    <th className="px-3 py-2">Trip Date</th>
                                    <th className="px-3 py-2">Driver</th>
                                    <th className="px-3 py-2">Municipal Purpose & Route</th>
                                    <th className="px-3 py-2 text-right">Start Km</th>
                                    <th className="px-3 py-2 text-right">End Km</th>
                                    <th className="px-3 py-2 text-right">Distance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logbooks.map((l) => (
                                    <tr key={l.id} className="hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5 font-mono font-bold text-slate-900">{l.registration_number}</td>
                                        <td className="px-3 py-2.5">{l.trip_date}</td>
                                        <td className="px-3 py-2.5 font-medium">{l.driver_name}</td>
                                        <td className="px-3 py-2.5 text-slate-700">{l.purpose}</td>
                                        <td className="px-3 py-2.5 text-right font-mono">{l.start_odometer}</td>
                                        <td className="px-3 py-2.5 text-right font-mono">{l.end_odometer}</td>
                                        <td className="px-3 py-2.5 text-right font-mono font-bold text-teal-800">{l.distance_km} km</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* Tab 4: Issue Coupon Form */}
            {tab === 'new_coupon' && selectedVehicle && (
                <section className="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        Issue Municipal Fuel Coupon for {selectedVehicle.registration_number} ({selectedVehicle.make})
                    </h2>
                    <form onSubmit={handleIssueCoupon} className="mt-4 space-y-4 text-xs">
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block font-medium text-slate-700">Litres Authorized</label>
                                <input
                                    type="number"
                                    required
                                    min="5"
                                    max="500"
                                    value={litres}
                                    onChange={(e) => setLitres(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-amber-600 focus:outline-none font-bold"
                                />
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Fuel Grade</label>
                                <select
                                    value={fuelType}
                                    onChange={(e) => setFuelType(e.target.value)}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-amber-600 focus:outline-none"
                                >
                                    <option value="diesel">Diesel 50 (Bulk)</option>
                                    <option value="petrol">Unleaded Petrol</option>
                                </select>
                            </div>
                            <div>
                                <label className="block font-medium text-slate-700">Rate / Litre ($)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={ratePerLitre}
                                    onChange={(e) => setRatePerLitre(Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-amber-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Driver Full Name</label>
                            <input
                                type="text"
                                required
                                value={driverName}
                                onChange={(e) => setDriverName(e.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-amber-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block font-medium text-slate-700">Official Municipal Duty / Route Purpose</label>
                            <input
                                type="text"
                                required
                                value={purpose}
                                onChange={(e) => setPurpose(e.target.value)}
                                className="mt-1 w-full rounded border border-slate-300 px-3 py-1.5 focus:border-amber-600 focus:outline-none"
                            />
                        </div>

                        <div className="rounded bg-amber-50 border border-amber-200 p-3 flex justify-between items-center text-xs">
                            <span className="font-semibold text-amber-900">Total Authorized Expenditure:</span>
                            <span className="text-base font-black text-amber-900">${(litres * ratePerLitre).toFixed(2)} USD</span>
                        </div>

                        <div className="flex justify-end gap-3 pt-3">
                            <button
                                type="button"
                                onClick={() => setTab('vehicles')}
                                className="rounded border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-amber-600 px-5 py-2 text-xs font-bold text-white hover:bg-amber-500 shadow-md"
                            >
                                {processing ? 'Authorizing...' : 'Disburse Serialized Coupon →'}
                            </button>
                        </div>
                    </form>
                </section>
            )}
        </AppLayout>
    );
}

