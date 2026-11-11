import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type StoresItem = {
    id: string;
    item_code: string;
    name: string;
    category: string;
    unit: string;
    stock: number;
    reorder_level: number;
    reorder_qty: number;
    unit_cost_usd: string;
    total_valuation_usd: string;
    bin?: string;
    is_low_stock: boolean;
};

type StoresTx = {
    id: string;
    ref: string;
    type: string;
    item_name?: string;
    item_code?: string;
    quantity: number;
    total_usd: string;
    department?: string;
    work_order?: string;
    date?: string;
};

type Summary = {
    total_items_count: number;
    total_inventory_valuation_usd: string;
    low_stock_count: number;
    items: StoresItem[];
    recent_transactions: StoresTx[];
};

type Props = {
    summary: Summary;
};

export default function CentralStores({ summary }: Props) {
    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [showIssueModal, setShowIssueModal] = useState(false);
    const [showAddItemModal, setShowAddItemModal] = useState(false);

    const [selectedItem, setSelectedItem] = useState<StoresItem | null>(null);

    const [receiveForm, setReceiveForm] = useState({
        stores_item_id: summary.items[0]?.id || '',
        quantity: '50',
        unit_cost_usd: '45.00',
        work_order_ref: 'PO-2026-MUT-0042',
        authorized_by: 'Stores Controller',
    });

    const [issueForm, setIssueForm] = useState({
        stores_item_id: summary.items[0]?.id || '',
        quantity: '10',
        department_recipient: 'Engineering - Water & Sewer Reticulation',
        work_order_ref: 'WO-2026-BURST-104 (Dangamvura Main)',
        issued_by: 'C. Makoni (Stores Officer)',
        received_by: 'T. Moyo (Artisan Plumber)',
    });

    const [itemForm, setItemForm] = useState({
        item_code: 'STK-WTR-',
        name: '',
        category: 'Water & Reticulation',
        unit_of_measure: 'unit',
        reorder_level: 20,
        reorder_quantity: 50,
        quantity_on_hand: 0,
        average_unit_cost_usd: '20.00',
        bin_location: 'Bay 1 Rack A',
        description: '',
    });

    const handleReceiveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/stores/receive', {
            ...receiveForm,
            unit_cost_minor: Math.round(parseFloat(receiveForm.unit_cost_usd || '0') * 100),
        }, {
            onSuccess: () => setShowReceiveModal(false),
        });
    };

    const handleIssueSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/stores/issue', issueForm, {
            onSuccess: () => setShowIssueModal(false),
        });
    };

    const handleAddItemSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/stores/items', {
            ...itemForm,
            average_unit_cost_minor: Math.round(parseFloat(itemForm.average_unit_cost_usd || '0') * 100),
        }, {
            onSuccess: () => setShowAddItemModal(false),
        });
    };

    return (
        <AppLayout title="Central Stores & Inventory Management">
            <Head title="Central Stores & Inventory - ManicERP" />

            <div className="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                CENTRAL STORES INVENTORY
                            </span>
                            <span className="text-xs text-slate-500 font-medium">Weighted Average Cost (WAC)</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 mt-1">
                            Central Stores & Material Stock Control
                        </h1>
                        <p className="text-sm text-slate-600 mt-0.5">
                            Manage stock catalogs, Goods Received Vouchers (GRVs), and department Stores Issue Notes (SINs).
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setShowAddItemModal(true)}
                            className="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 shadow-sm"
                        >
                            <span>+</span>
                            <span>New Stock Item</span>
                        </button>
                        <button
                            onClick={() => {
                                setReceiveForm({ ...receiveForm, stores_item_id: summary.items[0]?.id || '' });
                                setShowReceiveModal(true);
                            }}
                            className="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm"
                        >
                            <span>📥</span>
                            <span>Receive Stock (GRV)</span>
                        </button>
                        <button
                            onClick={() => {
                                setIssueForm({ ...issueForm, stores_item_id: summary.items[0]?.id || '' });
                                setShowIssueModal(true);
                            }}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-sm font-medium shadow-sm"
                        >
                            <span>📤</span>
                            <span>Issue Stock (SIN)</span>
                        </button>
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Catalog Inventory Items</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{summary.total_items_count} items</div>
                        <div className="text-xs text-slate-500 mt-1">Active tracked store stock codes</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Central Stores Valuation</div>
                        <div className="mt-2 text-2xl font-bold text-emerald-700">USD ${summary.total_inventory_valuation_usd}</div>
                        <div className="text-xs text-slate-500 mt-1">Live Weighted Average Cost Balance</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Low Stock / Reorder Alerts</div>
                        <div className={`mt-2 text-2xl font-bold ${summary.low_stock_count > 0 ? 'text-amber-600' : 'text-slate-900'}`}>
                            {summary.low_stock_count} items
                        </div>
                        <div className="text-xs text-amber-600 mt-1 font-medium">Requisition needed below buffer</div>
                    </div>
                </div>

                {/* Main Stock Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">Stores Stock Catalog & Balances</h2>
                            <p className="text-xs text-slate-500 mt-0.5">Physical bin locations, available quantities, and reorder levels</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-slate-600 font-semibold text-left">
                                <tr>
                                    <th className="py-3 px-4">Stock Code</th>
                                    <th className="py-3 px-4">Item Name & Specs</th>
                                    <th className="py-3 px-4">Category</th>
                                    <th className="py-3 px-4 text-center">Bin Location</th>
                                    <th className="py-3 px-4 text-right">Available Qty</th>
                                    <th className="py-3 px-4 text-right">Unit WAC</th>
                                    <th className="py-3 px-4 text-right">Total Value</th>
                                    <th className="py-3 px-4 text-center">Stock Status</th>
                                    <th className="py-3 px-4 text-right">Quick Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {summary.items.map((item) => (
                                    <tr key={item.id} className="hover:bg-slate-50/75 transition">
                                        <td className="py-3.5 px-4 font-mono font-medium text-blue-900 whitespace-nowrap">
                                            {item.item_code}
                                        </td>
                                        <td className="py-3.5 px-4 max-w-xs">
                                            <div className="font-medium text-slate-900">{item.name}</div>
                                            <div className="text-xs text-slate-500">Unit: {item.unit} • Reorder: {item.reorder_level}</div>
                                        </td>
                                        <td className="py-3.5 px-4 text-slate-700 whitespace-nowrap">
                                            {item.category}
                                        </td>
                                        <td className="py-3.5 px-4 text-center text-xs font-mono text-slate-600 whitespace-nowrap">
                                            {item.bin || '—'}
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                            {item.stock} {item.unit}
                                        </td>
                                        <td className="py-3.5 px-4 text-right text-slate-600 whitespace-nowrap">
                                            ${item.unit_cost_usd}
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-semibold text-emerald-700 whitespace-nowrap">
                                            ${item.total_valuation_usd}
                                        </td>
                                        <td className="py-3.5 px-4 text-center whitespace-nowrap">
                                            {item.is_low_stock ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                                    ⚠️ LOW STOCK
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700">
                                                    Optimal
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            <button
                                                onClick={() => {
                                                    setIssueForm({ ...issueForm, stores_item_id: item.id });
                                                    setShowIssueModal(true);
                                                }}
                                                className="text-xs font-semibold text-emerald-700 hover:text-emerald-900 mr-3 underline"
                                            >
                                                Issue (SIN)
                                            </button>
                                            <button
                                                onClick={() => {
                                                    setReceiveForm({ ...receiveForm, stores_item_id: item.id });
                                                    setShowReceiveModal(true);
                                                }}
                                                className="text-xs font-semibold text-blue-700 hover:text-blue-900 underline"
                                            >
                                                Receive (GRV)
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Recent GRVs and Stores Issue Notes */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 className="text-base font-bold text-slate-900 mb-4">Recent Stores Movement (GRVs & Issue Vouchers)</h2>
                    <div className="divide-y divide-slate-100 text-sm">
                        {summary.recent_transactions.map((tx) => (
                            <div key={tx.id} className="py-3 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${
                                        tx.type === 'grv' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'
                                    }`}>
                                        {tx.type === 'grv' ? 'GRV' : 'SIN'}
                                    </span>
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {tx.ref} • {tx.item_name} ({tx.quantity} units)
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {tx.department ? `Department: ${tx.department}` : `Work Order / PO: ${tx.work_order}`} • {tx.date}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="font-semibold text-slate-900">USD ${tx.total_usd}</div>
                                    <div className="text-xs text-slate-500 uppercase">{tx.type}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Modal: Receive Stock GRV */}
            {showReceiveModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Create Goods Received Voucher (GRV)</h3>
                            <button onClick={() => setShowReceiveModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleReceiveSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Select Stock Item</label>
                                <select
                                    value={receiveForm.stores_item_id}
                                    onChange={(e) => setReceiveForm({ ...receiveForm, stores_item_id: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-blue-500"
                                >
                                    {summary.items.map((it) => (
                                        <option key={it.id} value={it.id}>{it.item_code} - {it.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Quantity Received</label>
                                    <input
                                        type="number"
                                        required
                                        step="any"
                                        value={receiveForm.quantity}
                                        onChange={(e) => setReceiveForm({ ...receiveForm, quantity: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Unit Cost (USD)</label>
                                    <input
                                        type="number"
                                        required
                                        step="any"
                                        value={receiveForm.unit_cost_usd}
                                        onChange={(e) => setReceiveForm({ ...receiveForm, unit_cost_usd: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-blue-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Purchase Order / Delivery Note Ref</label>
                                <input
                                    type="text"
                                    required
                                    value={receiveForm.work_order_ref}
                                    onChange={(e) => setReceiveForm({ ...receiveForm, work_order_ref: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-blue-500"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowReceiveModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                                >
                                    Record GRV & Add to Stock
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Issue Stock SIN */}
            {showIssueModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Create Stores Issue Note (SIN)</h3>
                            <button onClick={() => setShowIssueModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleIssueSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Select Stock Item</label>
                                <select
                                    value={issueForm.stores_item_id}
                                    onChange={(e) => setIssueForm({ ...issueForm, stores_item_id: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                >
                                    {summary.items.map((it) => (
                                        <option key={it.id} value={it.id}>{it.item_code} - {it.name} (Stock: {it.stock} {it.unit})</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Quantity to Issue</label>
                                <input
                                    type="number"
                                    required
                                    step="any"
                                    value={issueForm.quantity}
                                    onChange={(e) => setIssueForm({ ...issueForm, quantity: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Recipient Directorate / Section</label>
                                <select
                                    value={issueForm.department_recipient}
                                    onChange={(e) => setIssueForm({ ...issueForm, department_recipient: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                >
                                    <option value="Engineering - Water & Sewer Reticulation">Engineering - Water & Sewer Reticulation</option>
                                    <option value="Engineering - Roads & Stormwater Drainage">Engineering - Roads & Stormwater Drainage</option>
                                    <option value="Health - Cleansing & Solid Waste">Health - Cleansing & Solid Waste</option>
                                    <option value="Electrical & Street Lighting">Electrical & Street Lighting</option>
                                    <option value="Housing & Community Facilities">Housing & Community Facilities</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Work Order / Job Card Ref</label>
                                <input
                                    type="text"
                                    required
                                    value={issueForm.work_order_ref}
                                    onChange={(e) => setIssueForm({ ...issueForm, work_order_ref: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowIssueModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
                                >
                                    Issue Stock & Deduct Inventory
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Add New Stock Item */}
            {showAddItemModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Register New Stores Catalog Item</h3>
                            <button onClick={() => setShowAddItemModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleAddItemSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Stock Item Code</label>
                                    <input
                                        type="text"
                                        required
                                        value={itemForm.item_code}
                                        onChange={(e) => setItemForm({ ...itemForm, item_code: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Category</label>
                                    <select
                                        value={itemForm.category}
                                        onChange={(e) => setItemForm({ ...itemForm, category: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300"
                                    >
                                        <option value="Water & Reticulation">Water & Reticulation</option>
                                        <option value="Water Treatment Chemicals">Water Treatment Chemicals</option>
                                        <option value="Road Maintenance">Road Maintenance</option>
                                        <option value="Street Lighting & Electrical">Street Lighting & Electrical</option>
                                        <option value="Protective Clothing & Tools">Protective Clothing & Tools</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Item Name & Specs</label>
                                <input
                                    type="text"
                                    required
                                    value={itemForm.name}
                                    onChange={(e) => setItemForm({ ...itemForm, name: e.target.value })}
                                    placeholder="e.g. 50mm Brass Gate Valve Class 16"
                                    className="w-full text-sm rounded-lg border-slate-300"
                                />
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Unit of Measure</label>
                                    <input
                                        type="text"
                                        required
                                        value={itemForm.unit_of_measure}
                                        onChange={(e) => setItemForm({ ...itemForm, unit_of_measure: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Reorder Point</label>
                                    <input
                                        type="number"
                                        required
                                        value={itemForm.reorder_level}
                                        onChange={(e) => setItemForm({ ...itemForm, reorder_level: parseInt(e.target.value) })}
                                        className="w-full text-sm rounded-lg border-slate-300"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Est. Unit Cost ($)</label>
                                    <input
                                        type="number"
                                        step="any"
                                        value={itemForm.average_unit_cost_usd}
                                        onChange={(e) => setItemForm({ ...itemForm, average_unit_cost_usd: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 mb-1">Bin Location in Stores</label>
                                <input
                                    type="text"
                                    value={itemForm.bin_location}
                                    onChange={(e) => setItemForm({ ...itemForm, bin_location: e.target.value })}
                                    className="w-full text-sm rounded-lg border-slate-300"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowAddItemModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                                >
                                    Save Catalog Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

