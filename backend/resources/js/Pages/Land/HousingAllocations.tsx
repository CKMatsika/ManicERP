import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Applicant = {
    id: string;
    waiting_list_number: string;
    national_id: string;
    full_name: string;
    phone_number: string;
    email?: string;
    category: string;
    preferred_suburbs?: string[];
    marital_status: string;
    number_of_dependents: number;
    employment_status: string;
    monthly_income_minor: number;
    currency_code: string;
    priority_points: number;
    status: string;
    application_date: string;
    expiry_date: string;
};

type Parcel = {
    id: string;
    parcel_number: string;
    description: string;
    suburb: string;
    stand_category: string;
    area_sqm: number;
    servicing_status: string;
    purchase_price_minor: number;
    deposit_required_minor: number;
    currency_code: string;
    allocation_status: string;
};

type Allocation = {
    id: string;
    allocation_reference: string;
    stand_number: string;
    suburb: string;
    category: string;
    category_label: string;
    area_sqm: number;
    purchase_price_usd: string;
    deposit_required_usd: string;
    deposit_paid_usd: string;
    currency_code: string;
    covenants: string[];
    allocated_at: string;
    offer_expires_at: string;
    days_remaining: number;
    status: string;
    accepted_at?: string;
};

type Props = {
    applicants: Applicant[];
    availableParcels: Parcel[];
    allocations: Allocation[];
    selectedCategory?: string;
};

export default function HousingAllocations({ applicants, availableParcels, allocations, selectedCategory }: Props) {
    const [tab, setTab] = useState<'queue' | 'inventory' | 'allocations'>('queue');
    const [allocatingApplicant, setAllocatingApplicant] = useState<Applicant | null>(null);
    const [selectedParcelId, setSelectedParcelId] = useState('');
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);

    function handleAllocate(e: React.FormEvent) {
        e.preventDefault();
        if (!allocatingApplicant || !selectedParcelId) return;

        setBusy(true);
        router.post(
            '/land/housing-allocations',
            {
                waiting_list_entry_id: allocatingApplicant.id,
                land_parcel_id: selectedParcelId,
                notes,
            },
            {
                onSuccess: () => {
                    setAllocatingApplicant(null);
                    setSelectedParcelId('');
                    setNotes('');
                    setBusy(false);
                },
                onError: () => setBusy(false),
            }
        );
    }

    return (
        <AppLayout title="Housing & Land Allocations">
            <Head title="Housing Stand Allocations" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-teal-700">
                            Department of Housing & Community Services
                        </p>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Municipal Housing Waiting List & Stand Allocations
                        </h1>
                        <p className="text-sm text-slate-600">
                            Transparent applicant merit vetting and cadastral stand allocation under the Urban Councils Act [Chapter 29:15].
                        </p>
                    </div>

                    {/* Stats pills */}
                    <div className="flex flex-wrap gap-2 text-xs">
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs">
                            <span className="text-slate-500">Active Queue:</span>{' '}
                            <span className="font-bold text-teal-800">{applicants.length} Applicants</span>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs">
                            <span className="text-slate-500">Available Stands:</span>{' '}
                            <span className="font-bold text-emerald-700">{availableParcels.length} Surveyed</span>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs">
                            <span className="text-slate-500">Offers Issued:</span>{' '}
                            <span className="font-bold text-amber-700">{allocations.length}</span>
                        </div>
                    </div>
                </header>

                {/* Tabs */}
                <div className="flex border-b border-slate-200 space-x-6 text-sm font-medium">
                    <button
                        onClick={() => setTab('queue')}
                        className={`pb-3 border-b-2 transition-colors ${
                            tab === 'queue'
                                ? 'border-teal-700 text-teal-800 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Waiting List Queue ({applicants.length})
                    </button>
                    <button
                        onClick={() => setTab('inventory')}
                        className={`pb-3 border-b-2 transition-colors ${
                            tab === 'inventory'
                                ? 'border-teal-700 text-teal-800 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Surveyed Stands Inventory ({availableParcels.length})
                    </button>
                    <button
                        onClick={() => setTab('allocations')}
                        className={`pb-3 border-b-2 transition-colors ${
                            tab === 'allocations'
                                ? 'border-teal-700 text-teal-800 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Allocations & Offer History ({allocations.length})
                    </button>
                </div>

                {/* Tab 1: Waiting List Queue */}
                {tab === 'queue' && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                        <div className="border-b border-slate-100 bg-slate-50/50 p-4 flex justify-between items-center">
                            <h2 className="font-semibold text-slate-800">
                                Priority Ranked Applicants (Awaiting Stand Allocation)
                            </h2>
                            <div className="flex gap-2">
                                <a
                                    href="/land/housing-allocations"
                                    className={`rounded px-2.5 py-1 text-xs font-medium ${!selectedCategory ? 'bg-teal-700 text-white' : 'bg-white border text-slate-600'}`}
                                >
                                    All Categories
                                </a>
                                <a
                                    href="/land/housing-allocations?category=high_density"
                                    className={`rounded px-2.5 py-1 text-xs font-medium ${selectedCategory === 'high_density' ? 'bg-teal-700 text-white' : 'bg-white border text-slate-600'}`}
                                >
                                    High Density
                                </a>
                                <a
                                    href="/land/housing-allocations?category=medium_density"
                                    className={`rounded px-2.5 py-1 text-xs font-medium ${selectedCategory === 'medium_density' ? 'bg-teal-700 text-white' : 'bg-white border text-slate-600'}`}
                                >
                                    Medium Density
                                </a>
                                <a
                                    href="/land/housing-allocations?category=low_density"
                                    className={`rounded px-2.5 py-1 text-xs font-medium ${selectedCategory === 'low_density' ? 'bg-teal-700 text-white' : 'bg-white border text-slate-600'}`}
                                >
                                    Low Density
                                </a>
                            </div>
                        </div>

                        {applicants.length === 0 ? (
                            <div className="p-8 text-center text-sm text-slate-500">
                                No active applicants found in this category.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm text-slate-600">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3">Rank / Score</th>
                                            <th className="px-4 py-3">Applicant Name & ID</th>
                                            <th className="px-4 py-3">Category & Suburbs</th>
                                            <th className="px-4 py-3">Family & Income</th>
                                            <th className="px-4 py-3">Application Date</th>
                                            <th className="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {applicants.map((item, idx) => (
                                            <tr key={item.id} className="hover:bg-slate-50/50">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-teal-100 font-bold text-teal-800 text-xs">
                                                            #{idx + 1}
                                                        </span>
                                                        <span className="font-bold text-slate-800">
                                                            {item.priority_points} pts
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-900">{item.full_name}</div>
                                                    <div className="font-mono text-xs text-slate-500">
                                                        {item.national_id} · {item.waiting_list_number}
                                                    </div>
                                                    <div className="text-xs text-slate-500">{item.phone_number}</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="inline-block rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 capitalize">
                                                        {item.category.replace('_', ' ')}
                                                    </span>
                                                    {item.preferred_suburbs && item.preferred_suburbs.length > 0 && (
                                                        <div className="mt-1 text-xs text-slate-500 truncate max-w-xs">
                                                            {item.preferred_suburbs.join(', ')}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div>{item.marital_status} · {item.number_of_dependents} dependents</div>
                                                    <div className="text-slate-500">
                                                        {item.employment_status} · ${(item.monthly_income_minor / 100).toFixed(0)}/mo
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div>{item.application_date}</div>
                                                    <div className="text-slate-400">Renewal: {item.expiry_date}</div>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <button
                                                        onClick={() => setAllocatingApplicant(item)}
                                                        className="rounded bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800"
                                                    >
                                                        Allocate Stand
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 2: Surveyed Stand Inventory */}
                {tab === 'inventory' && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                        <div className="border-b border-slate-100 bg-slate-50/50 p-4">
                            <h2 className="font-semibold text-slate-800">
                                Surveyed Council Land Parcels (Ready for Allocation)
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Stand Number</th>
                                        <th className="px-4 py-3">Suburb & Ward</th>
                                        <th className="px-4 py-3">Category & Area</th>
                                        <th className="px-4 py-3">Servicing Status</th>
                                        <th className="px-4 py-3">Price & Deposit</th>
                                        <th className="px-4 py-3">Cadastral Source</th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {availableParcels.map((p) => (
                                        <tr key={p.id} className="hover:bg-slate-50/50">
                                            <td className="px-4 py-3 font-mono font-bold text-slate-900">
                                                {p.parcel_number}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800">{p.suburb}</div>
                                                <div className="text-xs text-slate-500">{p.description}</div>
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                <div className="capitalize font-semibold text-slate-700">
                                                    {p.stand_category.replace('_', ' ')}
                                                </div>
                                                <div className="text-slate-500">{p.area_sqm} m²</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="rounded bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 capitalize">
                                                    {p.servicing_status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                <div className="font-semibold text-slate-900">
                                                    USD ${(p.purchase_price_minor / 100).toFixed(2)}
                                                </div>
                                                <div className="text-teal-700">
                                                    Deposit: ${(p.deposit_required_minor / 100).toFixed(2)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                <div>{p.coordinate_source}</div>
                                                <div className="font-mono text-slate-400">{p.coordinate_accuracy}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="rounded bg-teal-100 px-2 py-0.5 text-xs font-bold text-teal-800 uppercase">
                                                    {p.allocation_status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 3: Allocations & Offer History */}
                {tab === 'allocations' && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                        <div className="border-b border-slate-100 bg-slate-50/50 p-4">
                            <h2 className="font-semibold text-slate-800">
                                Issued Stand Allocation Offers & Status
                            </h2>
                        </div>
                        {allocations.length === 0 ? (
                            <div className="p-8 text-center text-sm text-slate-500">
                                No allocations recorded yet. Select an applicant from the queue to issue an offer.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm text-slate-600">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3">Offer Reference</th>
                                            <th className="px-4 py-3">Stand & Suburb</th>
                                            <th className="px-4 py-3">Stand Size & Category</th>
                                            <th className="px-4 py-3">Price & Deposit Paid</th>
                                            <th className="px-4 py-3">Allocated Date</th>
                                            <th className="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {allocations.map((a) => (
                                            <tr key={a.id} className="hover:bg-slate-50/50">
                                                <td className="px-4 py-3 font-mono font-bold text-teal-800">
                                                    {a.allocation_reference}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-900">{a.stand_number}</div>
                                                    <div className="text-xs text-slate-500">{a.suburb}</div>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div>{a.category_label}</div>
                                                    <div className="text-slate-500">{a.area_sqm} m²</div>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div>Price: USD ${a.purchase_price_usd}</div>
                                                    <div className="font-semibold text-teal-700">
                                                        Deposit Paid: ${a.deposit_paid_usd} / ${a.deposit_required_usd}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div>{a.allocated_at}</div>
                                                    <div className="text-amber-700">Expires: {a.offer_expires_at} ({a.days_remaining}d left)</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`rounded px-2.5 py-1 text-xs font-bold uppercase ${
                                                            a.status === 'deposit_paid' || a.status === 'agreement_signed'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : a.status === 'accepted'
                                                                ? 'bg-blue-100 text-blue-800'
                                                                : 'bg-amber-100 text-amber-800'
                                                        }`}
                                                    >
                                                        {a.status.replace('_', ' ')}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Allocation Modal */}
                {allocatingApplicant && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-xs">
                        <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                            <div className="flex justify-between items-start">
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900">
                                        Allocate Municipal Stand
                                    </h3>
                                    <p className="text-xs text-slate-500">
                                        Applicant: <span className="font-semibold">{allocatingApplicant.full_name}</span> ({allocatingApplicant.national_id})
                                    </p>
                                </div>
                                <button
                                    onClick={() => setAllocatingApplicant(null)}
                                    className="text-slate-400 hover:text-slate-600"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleAllocate} className="space-y-4 text-sm">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                                        Select Available Surveyed Stand *
                                    </label>
                                    <select
                                        required
                                        value={selectedParcelId}
                                        onChange={(e) => setSelectedParcelId(e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-teal-700 focus:outline-none"
                                    >
                                        <option value="">-- Choose a surveyed parcel --</option>
                                        {availableParcels
                                            .filter((p) => p.stand_category === allocatingApplicant.category || !p.stand_category)
                                            .map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.parcel_number} · {p.suburb} ({p.area_sqm} m² - Price: ${ (p.purchase_price_minor / 100).toFixed(0) }, Deposit: ${ (p.deposit_required_minor / 100).toFixed(0) })
                                                </option>
                                            ))}
                                    </select>
                                </div>

                                <div className="rounded-lg bg-slate-50 p-3 text-xs space-y-1.5 text-slate-600 border border-slate-200">
                                    <p className="font-semibold text-slate-800">Zimbabwe Urban Councils Standard Covenants:</p>
                                    <ul className="list-disc pl-4 space-y-1">
                                        <li>Deposit payable within 30 days to confirm provisional allocation.</li>
                                        <li>Building construction to window level within 24 months.</li>
                                        <li>Title deed issued upon 100% purchase price clearance and Certificate of Occupancy.</li>
                                    </ul>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 uppercase mb-1">
                                        Council Resolution / Officer Notes
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={notes}
                                        onChange={(e) => setNotes(e.target.value)}
                                        placeholder="e.g. Approved under Housing Committee Minute 42/2026."
                                        className="w-full rounded-lg border border-slate-300 p-2.5 focus:border-teal-700 focus:outline-none text-xs"
                                    />
                                </div>

                                <div className="flex justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setAllocatingApplicant(null)}
                                        className="rounded-lg border px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={busy || !selectedParcelId}
                                        className="rounded-lg bg-teal-700 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-800 disabled:opacity-50"
                                    >
                                        {busy ? 'Allocating...' : 'Issue Provisional Stand Offer'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

