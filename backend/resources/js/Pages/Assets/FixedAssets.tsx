import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Category = {
    id: string;
    code: string;
    name: string;
    depreciation_method: string;
    useful_life_years: number;
    gl_asset_account: string;
};

type Asset = {
    id: string;
    asset_number: string;
    name: string;
    category?: string;
    department: string;
    location: string;
    cost_usd: string;
    nbv_usd: string;
    condition: string;
    status: string;
    last_audit?: string;
    qr_code?: string;
};

type Summary = {
    total_assets_count: number;
    total_historical_cost_usd: string;
    total_carrying_amount_usd: string;
    total_accumulated_depreciation_usd: string;
    by_category: Array<{
        category_name: string;
        category_code: string;
        count: number;
        carrying_amount_usd: string;
    }>;
    by_department: Array<{
        department: string;
        count: number;
        carrying_amount_usd: string;
    }>;
    recent_assets: Asset[];
};

type Props = {
    summary: Summary;
    categories: Category[];
};

export default function FixedAssets({ summary, categories }: Props) {
    const [showRegisterModal, setShowRegisterModal] = useState(false);
    const [showDepreciationModal, setShowDepreciationModal] = useState(false);
    const [selectedAssetForAudit, setSelectedAssetForAudit] = useState<Asset | null>(null);

    const [form, setForm] = useState({
        asset_category_id: categories[0]?.id || '',
        name: '',
        description: '',
        serial_or_vin: '',
        department: 'Engineering & Works',
        custodian_name: '',
        location_name: 'Central Mechanical Workshop',
        acquisition_date: new Date().toISOString().split('T')[0],
        purchase_cost_usd: '25000',
        residual_value_usd: '2500',
        condition: 'good',
    });

    const [auditForm, setAuditForm] = useState({
        condition: 'good',
        notes: 'Annual physical verification inspection.',
    });

    const [depreciationPeriod, setDepreciationPeriod] = useState(
        new Date().toISOString().slice(0, 7) // YYYY-MM
    );

    const handleRegisterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/assets/register', {
            ...form,
            purchase_cost_minor: Math.round(parseFloat(form.purchase_cost_usd || '0') * 100),
            residual_value_minor: Math.round(parseFloat(form.residual_value_usd || '0') * 100),
        }, {
            onSuccess: () => setShowRegisterModal(false),
        });
    };

    const handleAuditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedAssetForAudit) return;
        router.post(`/assets/${selectedAssetForAudit.id}/audit`, auditForm, {
            onSuccess: () => setSelectedAssetForAudit(null),
        });
    };

    const handleDepreciateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/assets/depreciate', {
            financial_period: depreciationPeriod,
        }, {
            onSuccess: () => setShowDepreciationModal(false),
        });
    };

    return (
        <AppLayout title="Fixed Assets & Infrastructure (IPSAS 17)">
            <Head title="Fixed Assets & Infrastructure (IPSAS 17) - ManicERP" />

            <div className="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                IPSAS 17 COMPLIANT
                            </span>
                            <span className="text-xs text-slate-500 font-medium">Urban Councils Act [Cap 29:15]</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 mt-1">
                            Fixed Asset Register & Infrastructure Capitalization
                        </h1>
                        <p className="text-sm text-slate-600 mt-0.5">
                            Comprehensive asset tracking, GPS tagging, physical verification audits, and monthly depreciation schedules.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setShowDepreciationModal(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 shadow-sm"
                        >
                            <span>⚡</span>
                            <span>Run Monthly Depreciation</span>
                        </button>
                        <button
                            onClick={() => setShowRegisterModal(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-sm font-medium shadow-sm transition"
                        >
                            <span>+</span>
                            <span>Register New Asset</span>
                        </button>
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Capitalized Assets</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{summary.total_assets_count} items</div>
                        <div className="text-xs text-slate-500 mt-1">Across all municipal directorates</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Historical Cost (Acquisition)</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">USD ${summary.total_historical_cost_usd}</div>
                        <div className="text-xs text-emerald-600 font-medium mt-1">Capitalized PPE Register</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Net Book Value (Carrying NBV)</div>
                        <div className="mt-2 text-2xl font-bold text-emerald-700">USD ${summary.total_carrying_amount_usd}</div>
                        <div className="text-xs text-slate-500 mt-1">Current Statement of Financial Position</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Accumulated Depreciation</div>
                        <div className="mt-2 text-2xl font-bold text-amber-700">USD ${summary.total_accumulated_depreciation_usd}</div>
                        <div className="text-xs text-slate-500 mt-1">Contra-asset written down to date</div>
                    </div>
                </div>

                {/* Category & Directorate Breakdown */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <h2 className="text-base font-bold text-slate-900 mb-3">Portfolio by IPSAS 17 Asset Class</h2>
                        <div className="divide-y divide-slate-100">
                            {summary.by_category.map((cat, idx) => (
                                <div key={idx} className="py-2.5 flex items-center justify-between text-sm">
                                    <div>
                                        <div className="font-medium text-slate-900">{cat.category_name}</div>
                                        <div className="text-xs text-slate-500">Code: {cat.category_code} • {cat.count} asset(s)</div>
                                    </div>
                                    <div className="font-semibold text-slate-900">USD ${cat.carrying_amount_usd}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <h2 className="text-base font-bold text-slate-900 mb-3">Directorate / Custodian Distribution</h2>
                        <div className="divide-y divide-slate-100">
                            {summary.by_department.map((dept, idx) => (
                                <div key={idx} className="py-2.5 flex items-center justify-between text-sm">
                                    <div className="font-medium text-slate-900">{dept.department}</div>
                                    <div className="text-right">
                                        <div className="font-semibold text-slate-900">USD ${dept.carrying_amount_usd}</div>
                                        <div className="text-xs text-slate-500">{dept.count} asset(s)</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Asset Register Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">Municipal Asset Register</h2>
                            <p className="text-xs text-slate-500 mt-0.5">Showing registered plant, vehicles, infrastructure & buildings</p>
                        </div>
                        <span className="text-xs font-medium text-slate-600 bg-white px-2.5 py-1 rounded border border-slate-200">
                            {summary.recent_assets.length} Assets Registered
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-slate-600 font-semibold text-left">
                                <tr>
                                    <th className="py-3 px-4">Tag / Asset No.</th>
                                    <th className="py-3 px-4">Asset Details & Specs</th>
                                    <th className="py-3 px-4">Directorate / Location</th>
                                    <th className="py-3 px-4 text-right">Cost (USD)</th>
                                    <th className="py-3 px-4 text-right">Net Book Value</th>
                                    <th className="py-3 px-4 text-center">Condition</th>
                                    <th className="py-3 px-4 text-center">Status</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {summary.recent_assets.map((asset) => (
                                    <tr key={asset.id} className="hover:bg-slate-50/75 transition">
                                        <td className="py-3.5 px-4 font-mono font-medium text-emerald-800 whitespace-nowrap">
                                            {asset.asset_number}
                                        </td>
                                        <td className="py-3.5 px-4 max-w-xs">
                                            <div className="font-medium text-slate-900">{asset.name}</div>
                                            <div className="text-xs text-slate-500 truncate">{asset.category}</div>
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <div className="text-slate-900">{asset.department}</div>
                                            <div className="text-xs text-slate-500">{asset.location}</div>
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-medium text-slate-700 whitespace-nowrap">
                                            ${asset.cost_usd}
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                            ${asset.nbv_usd}
                                        </td>
                                        <td className="py-3.5 px-4 text-center whitespace-nowrap">
                                            <span className={`inline-flex px-2 py-0.5 rounded text-xs font-semibold uppercase ${
                                                asset.condition === 'excellent' ? 'bg-emerald-100 text-emerald-800' :
                                                asset.condition === 'good' ? 'bg-blue-100 text-blue-800' :
                                                asset.condition === 'fair' ? 'bg-amber-100 text-amber-800' :
                                                'bg-rose-100 text-rose-800'
                                            }`}>
                                                {asset.condition}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-center whitespace-nowrap">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                In Service
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            <button
                                                onClick={() => {
                                                    setSelectedAssetForAudit(asset);
                                                    setAuditForm({ condition: asset.condition, notes: 'Physical verification audit.' });
                                                }}
                                                className="text-xs font-medium text-emerald-700 hover:text-emerald-900 underline"
                                            >
                                                Verify / Audit
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal: Register Asset */}
            {showRegisterModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Capitalize & Register Municipal Asset</h3>
                            <button onClick={() => setShowRegisterModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleRegisterSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Asset Category (IPSAS 17)</label>
                                    <select
                                        value={form.asset_category_id}
                                        onChange={(e) => setForm({ ...form, asset_category_id: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    >
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name} ({c.useful_life_years} yrs life)</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Directorate / Department</label>
                                    <select
                                        value={form.department}
                                        onChange={(e) => setForm({ ...form, department: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    >
                                        <option value="Engineering & Works">Engineering & Works</option>
                                        <option value="Health & Environmental Services">Health & Environmental Services</option>
                                        <option value="Housing & Community Services">Housing & Community Services</option>
                                        <option value="Chamber Secretary">Chamber Secretary</option>
                                        <option value="Treasury">Treasury</option>
                                    </select>
                                </div>

                                <div className="md:col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Asset Name / Title</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.name}
                                        onChange={(e) => setForm({ ...form, name: e.target.value })}
                                        placeholder="e.g. Caterpillar 428F Backhoe Loader"
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Serial No. / Engine / VIN</label>
                                    <input
                                        type="text"
                                        value={form.serial_or_vin}
                                        onChange={(e) => setForm({ ...form, serial_or_vin: e.target.value })}
                                        placeholder="e.g. CAT-428F-99482"
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Station / Location Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={form.location_name}
                                        onChange={(e) => setForm({ ...form, location_name: e.target.value })}
                                        placeholder="e.g. Town Yard Roads Depot"
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Acquisition Cost (USD)</label>
                                    <input
                                        type="number"
                                        required
                                        value={form.purchase_cost_usd}
                                        onChange={(e) => setForm({ ...form, purchase_cost_usd: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Estimated Scrap / Residual (USD)</label>
                                    <input
                                        type="number"
                                        value={form.residual_value_usd}
                                        onChange={(e) => setForm({ ...form, residual_value_usd: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-4 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowRegisterModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
                                >
                                    Save to Asset Register
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Depreciation Run */}
            {showDepreciationModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Execute Monthly Depreciation</h3>
                            <button onClick={() => setShowDepreciationModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>
                        <p className="text-xs text-slate-600">
                            Executes straight-line monthly write-downs across all active capitalized municipal PPE per IPSAS 17 useful life schedules.
                        </p>
                        <form onSubmit={handleDepreciateSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Financial Accounting Period (YYYY-MM)</label>
                                <input
                                    type="month"
                                    required
                                    value={depreciationPeriod}
                                    onChange={(e) => setDepreciationPeriod(e.target.value)}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                />
                            </div>
                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowDepreciationModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
                                >
                                    Execute Depreciation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Physical Audit */}
            {selectedAssetForAudit && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Physical Verification Audit</h3>
                            <button onClick={() => setSelectedAssetForAudit(null)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>
                        <div className="text-xs text-slate-600">
                            Verifying: <span className="font-semibold text-slate-900">{selectedAssetForAudit.name}</span> ({selectedAssetForAudit.asset_number})
                        </div>
                        <form onSubmit={handleAuditSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Assessed Physical Condition</label>
                                <select
                                    value={auditForm.condition}
                                    onChange={(e) => setAuditForm({ ...auditForm, condition: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                >
                                    <option value="excellent">Excellent - Pristine Operational Condition</option>
                                    <option value="good">Good - Normal Operational Wear</option>
                                    <option value="fair">Fair - Requires Preventative Maintenance</option>
                                    <option value="poor">Poor - Impaired / Critical Repairs Required</option>
                                    <option value="scrap">Scrap - Recommended for Board of Survey Disposal</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Auditor Verification Notes</label>
                                <textarea
                                    rows={2}
                                    value={auditForm.notes}
                                    onChange={(e) => setAuditForm({ ...auditForm, notes: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setSelectedAssetForAudit(null)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
                                >
                                    Confirm Audit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

