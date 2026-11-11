import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState } from '../../Components/Ui';
import { money, type SharedProps } from '../../lib';

type Parcel = {
    id: string;
    parcel_number: string;
    description: string;
    ward: string;
    suburb: string;
    stand_category: string;
    area_sqm: number;
    servicing_status: string;
    purchase_price_minor: number;
    deposit_required_minor: number;
    currency_code: string;
    allocation_status: string;
    latitude: number;
    longitude: number;
    coordinate_source: string;
    coordinate_accuracy: string;
    status: string;
    updated_at: string;
};

type Stats = {
    total_stands: number;
    available_stands: number;
    allocated_stands: number;
    reserved_stands: number;
    total_area_sqm: number;
    total_valuation_minor: number;
    high_density_count: number;
    medium_density_count: number;
    low_density_count: number;
    commercial_count: number;
    industrial_count: number;
};

export default function GisCadastre({
    parcels,
    stats,
    suburbs,
    wards,
    filters,
}: {
    parcels: Parcel[];
    stats: Stats;
    suburbs: string[];
    wards: string[];
    filters: { search?: string; ward?: string; category?: string; status?: string; servicing?: string };
}) {
    const { council } = usePage<SharedProps>().props;

    const [viewMode, setViewMode] = useState<'map' | 'table'>('map');
    const [selectedParcel, setSelectedParcel] = useState<Parcel | null>(parcels[0] ?? null);
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedWard, setSelectedWard] = useState(filters.ward ?? '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category ?? '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status ?? '');
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Form state
    const [form, setForm] = useState({
        parcel_number: '',
        description: '',
        ward: 'W02',
        suburb: 'Chikanga',
        stand_category: 'high_density',
        area_sqm: 300,
        servicing_status: 'serviced',
        purchase_price_usd: 2500,
        deposit_usd: 400,
        latitude: -18.9654,
        longitude: 32.6412,
        coordinate_source: 'Surveyor General Cadastral Diagram 2026',
        coordinate_accuracy: 'GPS RTK 0.05m',
    });
    const [submitting, setSubmitting] = useState(false);

    // Bounding box for visual map calculation
    const mapBounds = useMemo(() => {
        if (parcels.length === 0) return { minLat: -19.05, maxLat: -18.90, minLng: 32.60, maxLng: 32.70 };
        const lats = parcels.map((p) => p.latitude);
        const lngs = parcels.map((p) => p.longitude);
        const minLat = Math.min(...lats) - 0.005;
        const maxLat = Math.max(...lats) + 0.005;
        const minLng = Math.min(...lngs) - 0.005;
        const maxLng = Math.max(...lngs) + 0.005;
        return { minLat, maxLat, minLng, maxLng };
    }, [parcels]);

    function getMapPosition(lat: number, lng: number) {
        const { minLat, maxLat, minLng, maxLng } = mapBounds;
        const xPercent = ((lng - minLng) / (maxLng - minLng)) * 88 + 6;
        // Invert Y because latitude goes up as you go north
        const yPercent = ((maxLat - lat) / (maxLat - minLat)) * 80 + 10;
        return { left: `${Math.min(94, Math.max(6, xPercent))}%`, top: `${Math.min(90, Math.max(10, yPercent))}%` };
    }

    function applyFilter(key: string, value: string) {
        router.get(
            '/land/gis',
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    function handleSearchSubmit(e: React.FormEvent) {
        e.preventDefault();
        applyFilter('search', search);
    }

    function handleCreateSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        router.post('/land/gis/parcels', form, {
            onSuccess: () => {
                setShowCreateModal(false);
                setSubmitting(false);
            },
            onError: () => setSubmitting(false),
        });
    }

    function categoryColor(cat: string) {
        switch (cat) {
            case 'high_density':
                return { bg: 'bg-blue-600', text: 'text-blue-700', light: 'bg-blue-50', border: 'border-blue-300' };
            case 'medium_density':
                return { bg: 'bg-amber-600', text: 'text-amber-700', light: 'bg-amber-50', border: 'border-amber-300' };
            case 'low_density':
                return { bg: 'bg-emerald-600', text: 'text-emerald-700', light: 'bg-emerald-50', border: 'border-emerald-300' };
            case 'commercial':
                return { bg: 'bg-purple-600', text: 'text-purple-700', light: 'bg-purple-50', border: 'border-purple-300' };
            case 'industrial':
                return { bg: 'bg-orange-600', text: 'text-orange-700', light: 'bg-orange-50', border: 'border-orange-300' };
            default:
                return { bg: 'bg-slate-600', text: 'text-slate-700', light: 'bg-slate-50', border: 'border-slate-300' };
        }
    }

    function statusBadge(status: string) {
        switch (status) {
            case 'available':
                return <Badge tone="positive">Available</Badge>;
            case 'allocated':
                return <Badge tone="info">Allocated</Badge>;
            case 'reserved':
                return <Badge tone="warning">Reserved</Badge>;
            default:
                return <Badge tone="neutral">{status}</Badge>;
        }
    }

    return (
        <AppLayout title="GIS & Cadastral Property Database">
            <Head title="GIS & Cadastral Property Database" />

            {/* Header with Title and Primary Actions */}
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-slate-900">
                        GIS Cadastre & Municipal Property Database
                    </h1>
                    <p className="text-xs text-slate-500">
                        Surveyor General cadastral coordinates, digital stand register, valuation roll and servicing status.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-xs">
                        <button
                            type="button"
                            onClick={() => setViewMode('map')}
                            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${
                                viewMode === 'map' ? 'bg-teal-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            🗺️ Interactive Map
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode('table')}
                            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${
                                viewMode === 'table' ? 'bg-teal-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            📋 Property Table
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowCreateModal(true)}
                        className="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-teal-800"
                    >
                        + Add Cadastral Stand
                    </button>
                </div>
            </div>

            {/* Metrics Ribbon */}
            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                    <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Total Registered Stands</p>
                    <p className="mt-1 text-2xl font-extrabold text-slate-900">{stats.total_stands}</p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {(stats.total_area_sqm / 10000).toFixed(2)} Hectares ({(stats.total_area_sqm).toLocaleString()} m²)
                    </p>
                </div>
                <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs">
                    <p className="text-xs font-medium uppercase tracking-wider text-emerald-700">Available For Allocation</p>
                    <p className="mt-1 text-2xl font-extrabold text-emerald-900">{stats.available_stands}</p>
                    <p className="mt-0.5 text-xs text-emerald-700">
                        {stats.total_stands > 0 ? Math.round((stats.available_stands / stats.total_stands) * 100) : 0}% unallocated inventory
                    </p>
                </div>
                <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 shadow-xs">
                    <p className="text-xs font-medium uppercase tracking-wider text-blue-700">Allocated / Title Deeds</p>
                    <p className="mt-1 text-2xl font-extrabold text-blue-900">{stats.allocated_stands}</p>
                    <p className="mt-0.5 text-xs text-blue-700">Linked to ratepayer assessment rolls</p>
                </div>
                <div className="rounded-lg border border-purple-200 bg-purple-50/50 p-4 shadow-xs">
                    <p className="text-xs font-medium uppercase tracking-wider text-purple-700">Total Stand Valuation</p>
                    <p className="mt-1 text-2xl font-extrabold text-purple-900">
                        {money(stats.total_valuation_minor, council?.currency)}
                    </p>
                    <p className="mt-0.5 text-xs text-purple-700">{stats.commercial_count + stats.industrial_count} commercial / industrial units</p>
                </div>
            </div>

            {/* Filter and Search Bar */}
            <div className="mb-4 rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
                <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2">
                    <div className="min-w-[200px] flex-1">
                        <input
                            type="text"
                            placeholder="Search stand number, suburb, deed diagram, or ward..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs focus:border-teal-600 focus:outline-none"
                        />
                    </div>

                    <select
                        value={selectedWard}
                        onChange={(e) => {
                            setSelectedWard(e.target.value);
                            applyFilter('ward', e.target.value);
                        }}
                        className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 focus:border-teal-600 focus:outline-none"
                    >
                        <option value="">All Wards</option>
                        {wards.map((w) => (
                            <option key={w} value={w}>
                                Ward {w}
                            </option>
                        ))}
                    </select>

                    <select
                        value={selectedCategory}
                        onChange={(e) => {
                            setSelectedCategory(e.target.value);
                            applyFilter('category', e.target.value);
                        }}
                        className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 focus:border-teal-600 focus:outline-none"
                    >
                        <option value="">All Categories</option>
                        <option value="high_density">High Density Residential</option>
                        <option value="medium_density">Medium Density Residential</option>
                        <option value="low_density">Low Density Residential</option>
                        <option value="commercial">Commercial / Retail</option>
                        <option value="industrial">Heavy / Light Industrial</option>
                    </select>

                    <select
                        value={selectedStatus}
                        onChange={(e) => {
                            setSelectedStatus(e.target.value);
                            applyFilter('status', e.target.value);
                        }}
                        className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 focus:border-teal-600 focus:outline-none"
                    >
                        <option value="">All Allocation Statuses</option>
                        <option value="available">Available</option>
                        <option value="allocated">Allocated</option>
                        <option value="reserved">Reserved</option>
                    </select>

                    <button
                        type="submit"
                        className="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900"
                    >
                        Filter
                    </button>

                    {(search || selectedWard || selectedCategory || selectedStatus) && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setSelectedWard('');
                                setSelectedCategory('');
                                setSelectedStatus('');
                                router.get('/land/gis');
                            }}
                            className="text-xs text-rose-600 hover:underline"
                        >
                            Reset filters
                        </button>
                    )}
                </form>
            </div>

            {/* Main Content Area */}
            {viewMode === 'map' ? (
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Interactive GIS Spatial Canvas */}
                    <div className="rounded-lg border border-slate-200 bg-white shadow-xs lg:col-span-2 overflow-hidden flex flex-col">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3 bg-slate-50">
                            <div className="flex items-center gap-2">
                                <span className="flex h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Interactive Spatial Cadastre Canvas
                                </span>
                            </div>
                            <div className="flex items-center gap-3 text-[11px] text-slate-500">
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-blue-600"></span> High Density
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-amber-600"></span> Med Density
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-emerald-600"></span> Low Density
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-purple-600"></span> Commercial
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-2 w-2 rounded-full bg-orange-600"></span> Industrial
                                </span>
                            </div>
                        </div>

                        {/* Visual GIS Grid with SVG Overlay */}
                        <div className="relative min-h-[460px] flex-1 bg-slate-900 select-none overflow-hidden">
                            {/* Cadastral Grid Lines */}
                            <svg className="absolute inset-0 h-full w-full opacity-20 pointer-events-none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <pattern id="cadastralGrid" width="40" height="40" patternUnits="userSpaceOnUse">
                                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#94a3b8" strokeWidth="0.5" />
                                    </pattern>
                                    <pattern id="cadastralMajorGrid" width="160" height="160" patternUnits="userSpaceOnUse">
                                        <rect width="160" height="160" fill="none" stroke="#38bdf8" strokeWidth="1" strokeDasharray="3 3" />
                                    </pattern>
                                </defs>
                                <rect width="100%" height="100%" fill="url(#cadastralGrid)" />
                                <rect width="100%" height="100%" fill="url(#cadastralMajorGrid)" />
                            </svg>

                            {/* Center Coordinates & Compass */}
                            <div className="absolute top-3 left-3 rounded bg-slate-800/80 backdrop-blur-xs px-2.5 py-1 text-[10px] font-mono text-cyan-300 border border-slate-700">
                                Lat: {selectedParcel?.latitude.toFixed(6) ?? '-18.965400'} | Lng: {selectedParcel?.longitude.toFixed(6) ?? '32.641200'} | WGS84
                            </div>
                            <div className="absolute top-3 right-3 rounded bg-slate-800/80 backdrop-blur-xs px-2 py-1 text-[11px] font-bold text-slate-300 border border-slate-700">
                                🧭 N
                            </div>

                            {/* Pins for each parcel */}
                            {parcels.map((parcel) => {
                                const pos = getMapPosition(parcel.latitude, parcel.longitude);
                                const isSelected = selectedParcel?.id === parcel.id;
                                const col = categoryColor(parcel.stand_category);

                                return (
                                    <button
                                        key={parcel.id}
                                        type="button"
                                        onClick={() => setSelectedParcel(parcel)}
                                        style={{ left: pos.left, top: pos.top }}
                                        className={`group absolute -translate-x-1/2 -translate-y-1/2 cursor-pointer transition transform focus:outline-none ${
                                            isSelected ? 'scale-125 z-30' : 'hover:scale-110 z-10'
                                        }`}
                                    >
                                        <div className="relative flex flex-col items-center">
                                            {/* Beacon Pin */}
                                            <div
                                                className={`flex h-7 w-7 items-center justify-center rounded-full border-2 text-[10px] font-bold text-white shadow-lg transition ${
                                                    isSelected ? 'border-white ring-4 ring-cyan-400 bg-cyan-600' : `${col.bg} border-slate-200`
                                                }`}
                                            >
                                                {parcel.ward}
                                            </div>

                                            {/* Stand Label Badge */}
                                            <div
                                                className={`mt-1 whitespace-nowrap rounded px-1.5 py-0.5 text-[9px] font-mono font-bold tracking-tight shadow-md border ${
                                                    isSelected
                                                        ? 'bg-cyan-900/90 text-cyan-200 border-cyan-400'
                                                        : 'bg-slate-900/85 text-slate-200 border-slate-700 group-hover:bg-slate-800'
                                                }`}
                                            >
                                                {parcel.parcel_number}
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Interactive Map Footer Help */}
                        <div className="flex items-center justify-between border-t border-slate-200 px-4 py-2.5 bg-slate-50 text-xs text-slate-500">
                            <span>Click any cadastral stand pin on the canvas to inspect its diagram and title records.</span>
                            <span>{parcels.length} parcel(s) plotted</span>
                        </div>
                    </div>

                    {/* Cadastral Stand Dossier Panel */}
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                        {selectedParcel ? (
                            <div>
                                <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-base font-bold text-slate-900 font-mono">
                                                {selectedParcel.parcel_number}
                                            </h3>
                                            {statusBadge(selectedParcel.allocation_status)}
                                        </div>
                                        <p className="text-xs text-slate-500">{selectedParcel.description}</p>
                                    </div>
                                    <span className="text-xs font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded border border-teal-200">
                                        Ward {selectedParcel.ward}
                                    </span>
                                </div>

                                <div className="mt-4 space-y-3 text-xs">
                                    <div className="rounded-md border border-slate-100 bg-slate-50 p-2.5">
                                        <p className="font-semibold text-slate-700 uppercase tracking-wider text-[10px]">
                                            Cadastral Beacon Coordinates (WGS84)
                                        </p>
                                        <div className="mt-1 font-mono text-slate-900">
                                            {selectedParcel.latitude.toFixed(6)}, {selectedParcel.longitude.toFixed(6)}
                                        </div>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            {selectedParcel.coordinate_source} · Accuracy: {selectedParcel.coordinate_accuracy}
                                        </p>
                                        <a
                                            href={`https://maps.google.com/?q=${selectedParcel.latitude},${selectedParcel.longitude}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="mt-2 inline-flex items-center gap-1 font-semibold text-teal-700 hover:underline"
                                        >
                                            Open in Satellite Maps ↗
                                        </a>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded border border-slate-100 p-2">
                                            <p className="text-[10px] text-slate-400 uppercase font-semibold">Stand Area</p>
                                            <p className="font-bold text-slate-800 text-sm mt-0.5">{selectedParcel.area_sqm} m²</p>
                                            <p className="text-[10px] text-slate-500">{(selectedParcel.area_sqm / 10000).toFixed(3)} Ha</p>
                                        </div>
                                        <div className="rounded border border-slate-100 p-2">
                                            <p className="text-[10px] text-slate-400 uppercase font-semibold">Suburb / Township</p>
                                            <p className="font-bold text-slate-800 text-sm mt-0.5">{selectedParcel.suburb}</p>
                                            <p className="text-[10px] text-slate-500 capitalize">{selectedParcel.stand_category.replace('_', ' ')}</p>
                                        </div>
                                    </div>

                                    <div className="rounded-md border border-slate-100 p-3 space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="text-slate-500">Servicing Status:</span>
                                            <span className="font-semibold text-slate-800 capitalize">
                                                {selectedParcel.servicing_status.replace('_', ' ')}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-500">Council Valuation:</span>
                                            <span className="font-bold text-slate-900">
                                                {money(selectedParcel.purchase_price_minor, selectedParcel.currency_code)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-500">Required Deposit:</span>
                                            <span className="font-semibold text-teal-700">
                                                {money(selectedParcel.deposit_required_minor, selectedParcel.currency_code)}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Quick Actions */}
                                    <div className="pt-2 border-t border-slate-100 flex gap-2">
                                        <a
                                            href={`/land/housing-allocations`}
                                            className="flex-1 rounded-md bg-teal-700 py-2 text-center text-xs font-semibold text-white hover:bg-teal-800"
                                        >
                                            Allocate Stand →
                                        </a>
                                        <a
                                            href={`/revenue/accounts`}
                                            className="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Linked Rates
                                        </a>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="py-12">
                                <EmptyState title="No Stand Selected" hint="Click a stand pin on the GIS map to view cadastral records." />
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                /* Tabular Property Database Register */
                <div className="rounded-lg border border-slate-200 bg-white shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Stand Number</th>
                                    <th className="px-4 py-3">Ward / Suburb</th>
                                    <th className="px-4 py-3">Category</th>
                                    <th className="px-4 py-3 text-right">Area (m²)</th>
                                    <th className="px-4 py-3">Servicing</th>
                                    <th className="px-4 py-3 text-right">Council Valuation</th>
                                    <th className="px-4 py-3">Cadastral Source</th>
                                    <th className="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {parcels.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="py-8 text-center text-slate-500">
                                            No cadastral stands match the specified filters.
                                        </td>
                                    </tr>
                                ) : (
                                    parcels.map((parcel) => (
                                        <tr key={parcel.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-4 py-2.5 font-mono font-bold text-slate-900">
                                                {parcel.parcel_number}
                                                <div className="text-[11px] font-normal text-slate-500">{parcel.description}</div>
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-700">
                                                Ward {parcel.ward} · {parcel.suburb}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <span className="capitalize">{parcel.stand_category.replace('_', ' ')}</span>
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-medium text-slate-800">
                                                {parcel.area_sqm.toLocaleString()} m²
                                            </td>
                                            <td className="px-4 py-2.5 capitalize text-slate-600">
                                                {parcel.servicing_status.replace('_', ' ')}
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-bold text-slate-900">
                                                {money(parcel.purchase_price_minor, parcel.currency_code)}
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-500 font-mono text-[11px]">
                                                {parcel.coordinate_source}
                                            </td>
                                            <td className="px-4 py-2.5">{statusBadge(parcel.allocation_status)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Modal: Add New Cadastral Stand */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                    <div className="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                            <h2 className="text-base font-bold text-slate-900">Capture New Cadastral Stand</h2>
                            <button
                                type="button"
                                onClick={() => setShowCreateModal(false)}
                                className="text-slate-400 hover:text-slate-600 font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="mt-4 space-y-3 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-semibold text-slate-700">Stand Number *</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. STD-5012-CHK"
                                        value={form.parcel_number}
                                        onChange={(e) => setForm({ ...form, parcel_number: e.target.value })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 font-mono text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Ward *</label>
                                    <input
                                        type="text"
                                        placeholder="e.g. W02"
                                        value={form.ward}
                                        onChange={(e) => setForm({ ...form, ward: e.target.value })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700">Description / Stand Title *</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Stand 5012 Chikanga Phase 3 Residential"
                                    value={form.description}
                                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                                    required
                                    className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                />
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block font-semibold text-slate-700">Suburb / Township *</label>
                                    <input
                                        type="text"
                                        value={form.suburb}
                                        onChange={(e) => setForm({ ...form, suburb: e.target.value })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Category *</label>
                                    <select
                                        value={form.stand_category}
                                        onChange={(e) => setForm({ ...form, stand_category: e.target.value })}
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    >
                                        <option value="high_density">High Density</option>
                                        <option value="medium_density">Medium Density</option>
                                        <option value="low_density">Low Density</option>
                                        <option value="commercial">Commercial</option>
                                        <option value="industrial">Industrial</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Area (m²) *</label>
                                    <input
                                        type="number"
                                        value={form.area_sqm}
                                        onChange={(e) => setForm({ ...form, area_sqm: Number(e.target.value) })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block font-semibold text-slate-700">Servicing *</label>
                                    <select
                                        value={form.servicing_status}
                                        onChange={(e) => setForm({ ...form, servicing_status: e.target.value })}
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    >
                                        <option value="serviced">Fully Serviced</option>
                                        <option value="partially_serviced">Partially Serviced</option>
                                        <option value="unserviced">Unserviced</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Valuation (USD) *</label>
                                    <input
                                        type="number"
                                        value={form.purchase_price_usd}
                                        onChange={(e) => setForm({ ...form, purchase_price_usd: Number(e.target.value) })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Deposit (USD) *</label>
                                    <input
                                        type="number"
                                        value={form.deposit_usd}
                                        onChange={(e) => setForm({ ...form, deposit_usd: Number(e.target.value) })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block font-semibold text-slate-700">Latitude (WGS84) *</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.latitude}
                                        onChange={(e) => setForm({ ...form, latitude: Number(e.target.value) })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 font-mono text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label className="block font-semibold text-slate-700">Longitude (WGS84) *</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={form.longitude}
                                        onChange={(e) => setForm({ ...form, longitude: Number(e.target.value) })}
                                        required
                                        className="mt-1 w-full rounded border border-slate-300 p-2 font-mono text-xs focus:border-teal-600 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-700">Surveyor General Cadastral Reference</label>
                                <input
                                    type="text"
                                    value={form.coordinate_source}
                                    onChange={(e) => setForm({ ...form, coordinate_source: e.target.value })}
                                    className="mt-1 w-full rounded border border-slate-300 p-2 text-xs focus:border-teal-600 focus:outline-none"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-200 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="rounded border border-slate-300 px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="rounded bg-teal-700 px-4 py-1.5 font-semibold text-white hover:bg-teal-800 disabled:opacity-50"
                                >
                                    {submitting ? 'Saving...' : 'Capture Stand'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

