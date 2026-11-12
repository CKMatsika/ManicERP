import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatStrip, { StatItem } from '../../Components/StatStrip';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import { Modal } from '../../Components/FormComponents';

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
    const [activeTab, setActiveTab] = useState<'inventory' | 'transactions' | 'reorder'>('inventory');

    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [showIssueModal, setShowIssueModal] = useState(false);
    const [showAddItemModal, setShowAddItemModal] = useState(false);
    const [selectedBinCardItem, setSelectedBinCardItem] = useState<StoresItem | null>(null);

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
            preserveScroll: true,
            onSuccess: () => setShowReceiveModal(false),
        });
    };

    const handleIssueSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/stores/issue', issueForm, {
            preserveScroll: true,
            onSuccess: () => setShowIssueModal(false),
        });
    };

    const handleAddItemSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/stores/items', {
            ...itemForm,
            average_unit_cost_minor: Math.round(parseFloat(itemForm.average_unit_cost_usd || '0') * 100),
        }, {
            preserveScroll: true,
            onSuccess: () => setShowAddItemModal(false),
        });
    };

    const lowStockItems = summary.items.filter((item) => item.is_low_stock || item.stock <= item.reorder_level);

    const stats: StatItem[] = [
        {
            label: 'Total stocked lines',
            value: summary.total_items_count,
            helper: 'Active SKU catalog',
            icon: 'Boxes',
            tone: 'default',
        },
        {
            label: 'Inventory valuation',
            value: `$${summary.total_inventory_valuation_usd}`,
            helper: 'Weighted average cost',
            icon: 'Landmark',
            tone: 'emerald',
        },
        {
            label: 'Reorder alerts',
            value: summary.low_stock_count,
            helper: 'At or below min threshold',
            icon: 'AlertCircle',
            tone: summary.low_stock_count > 0 ? 'amber' : 'default',
        },
        {
            label: 'Stock movements logged',
            value: summary.recent_transactions.length,
            helper: 'GRV receipts & departmental issues',
            icon: 'RotateCcw',
            tone: 'default',
        },
    ];

    const inventoryColumns: Column<StoresItem>[] = [
        {
            key: 'item_code',
            header: 'Item code',
            sortable: true,
            render: (item) => (
                <button
                    type="button"
                    onClick={() => setSelectedBinCardItem(item)}
                    className="font-mono text-xs font-semibold text-emerald-800 hover:underline"
                >
                    {item.item_code}
                </button>
            ),
        },
        {
            key: 'name',
            header: 'Item name & description',
            render: (item) => (
                <div>
                    <p className="font-medium text-slate-900">{item.name}</p>
                    <p className="text-xs text-slate-500">{item.category} {item.bin ? `· Bin: ${item.bin}` : ''}</p>
                </div>
            ),
        },
        {
            key: 'stock',
            header: 'Physical stock',
            align: 'right',
            sortable: true,
            render: (item) => (
                <span className={`font-semibold tabular-nums ${item.is_low_stock ? 'text-rose-700' : 'text-slate-900'}`}>
                    {item.stock} <span className="text-xs font-normal text-slate-500">{item.unit}</span>
                </span>
            ),
        },
        {
            key: 'reorder_level',
            header: 'Reorder min',
            align: 'right',
            render: (item) => (
                <span className="text-xs tabular-nums text-slate-600">
                    {item.reorder_level} {item.unit}
                </span>
            ),
        },
        {
            key: 'unit_cost_usd',
            header: 'Avg unit cost',
            align: 'right',
            render: (item) => (
                <span className="tabular-nums text-slate-800">
                    ${item.unit_cost_usd}
                </span>
            ),
        },
        {
            key: 'total_valuation_usd',
            header: 'Total valuation',
            align: 'right',
            sortable: true,
            render: (item) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    ${item.total_valuation_usd}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Stock status',
            render: (item) => (
                <StatusBadge
                    status={item.is_low_stock ? 'overdue' : 'active'}
                    label={item.is_low_stock ? 'Low stock' : 'Adequate stock'}
                />
            ),
        },
    ];

    const inventoryActions: RowAction<StoresItem>[] = [
        {
            label: 'View bin card & history',
            icon: 'FileText',
            onClick: (item) => setSelectedBinCardItem(item),
        },
        {
            label: 'Receive stock (GRV)',
            icon: 'Boxes',
            onClick: (item) => {
                setReceiveForm({ ...receiveForm, stores_item_id: item.id, unit_cost_usd: item.unit_cost_usd });
                setShowReceiveModal(true);
            },
        },
        {
            label: 'Issue to department',
            icon: 'ArrowRight',
            onClick: (item) => {
                setIssueForm({ ...issueForm, stores_item_id: item.id });
                setShowIssueModal(true);
            },
        },
    ];

    const transactionColumns: Column<StoresTx>[] = [
        {
            key: 'ref',
            header: 'Transaction ref',
            render: (tx) => <span className="font-mono text-xs font-semibold text-slate-800">{tx.ref}</span>,
        },
        {
            key: 'date',
            header: 'Date',
            render: (tx) => <span className="text-slate-600">{tx.date ?? 'Today'}</span>,
        },
        {
            key: 'type',
            header: 'Movement type',
            render: (tx) => (
                <StatusBadge
                    status={tx.type === 'RECEIVE' ? 'active' : 'slate'}
                    label={tx.type === 'RECEIVE' ? 'Goods received (GRV)' : 'Stores issue note'}
                />
            ),
        },
        {
            key: 'item_name',
            header: 'Stock item',
            render: (tx) => (
                <div>
                    <span className="font-medium text-slate-900">{tx.item_name ?? 'Item'}</span>
                    {tx.item_code && <span className="font-mono text-xs text-slate-400 ml-1.5">{tx.item_code}</span>}
                </div>
            ),
        },
        {
            key: 'quantity',
            header: 'Quantity',
            align: 'right',
            render: (tx) => (
                <span className={`font-semibold tabular-nums ${tx.type === 'RECEIVE' ? 'text-emerald-700' : 'text-slate-900'}`}>
                    {tx.type === 'RECEIVE' ? `+${tx.quantity}` : `-${tx.quantity}`}
                </span>
            ),
        },
        {
            key: 'total_usd',
            header: 'Valuation',
            align: 'right',
            render: (tx) => <span className="font-semibold tabular-nums text-slate-900">${tx.total_usd}</span>,
        },
        {
            key: 'department',
            header: 'Department / Work order',
            render: (tx) => (
                <span className="text-xs text-slate-600">
                    {tx.department || tx.work_order || 'Central Warehouse'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Central Stores & Inventory">
            <Head title="Central Stores & Inventory Management" />

            <PageHeader
                title="Central stores & inventory management"
                description="Warehouse inventory valuation, physical bin cards, goods received vouchers (GRV/GRN) and departmental stores issue notes."
                breadcrumbs={[
                    { label: 'Assets & supply chain', href: '/stores' },
                    { label: 'Central stores' },
                ]}
                primaryAction={{
                    label: 'New stock SKU item',
                    icon: 'Plus',
                    onClick: () => setShowAddItemModal(true),
                }}
                secondaryActions={[
                    {
                        label: 'Receive delivery (GRV)',
                        icon: 'Boxes',
                        onClick: () => setShowReceiveModal(true),
                    },
                    {
                        label: 'Issue stores note',
                        icon: 'ArrowRight',
                        onClick: () => setShowIssueModal(true),
                    },
                    {
                        label: 'Procurement (PRAZ)',
                        icon: 'ShoppingBag',
                        href: '/procurement',
                    },
                ]}
            />

            <StatStrip stats={stats} />

            {/* Navigation Tabs */}
            <div className="mb-6 border-b border-slate-200">
                <nav className="flex space-x-6">
                    <button
                        type="button"
                        onClick={() => setActiveTab('inventory')}
                        className={`flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors ${
                            activeTab === 'inventory'
                                ? 'border-emerald-700 text-emerald-800 font-semibold'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        }`}
                    >
                        <span>Stock inventory catalog</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs ${activeTab === 'inventory' ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-slate-100 text-slate-600'}`}>
                            {summary.items.length}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('transactions')}
                        className={`flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors ${
                            activeTab === 'transactions'
                                ? 'border-emerald-700 text-emerald-800 font-semibold'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        }`}
                    >
                        <span>Stores transaction ledger</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs ${activeTab === 'transactions' ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-slate-100 text-slate-600'}`}>
                            {summary.recent_transactions.length}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('reorder')}
                        className={`flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors ${
                            activeTab === 'reorder'
                                ? 'border-emerald-700 text-emerald-800 font-semibold'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        }`}
                    >
                        <span>Reorder replenishment list</span>
                        {lowStockItems.length > 0 && (
                            <span className="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-semibold">
                                {lowStockItems.length}
                            </span>
                        )}
                    </button>
                </nav>
            </div>

            {/* Tab 1: Inventory Catalog */}
            {activeTab === 'inventory' && (
                <div className="space-y-4">
                    <DataTable
                        columns={inventoryColumns}
                        data={summary.items}
                        keyField="id"
                        rowActions={inventoryActions}
                        onRowClick={(item) => setSelectedBinCardItem(item)}
                        emptyTitle="No stock items registered"
                        emptyHint="Register materials, spare parts, and fuel items to begin warehouse tracking."
                    />
                </div>
            )}

            {/* Tab 2: Transactions Ledger */}
            {activeTab === 'transactions' && (
                <div className="space-y-4">
                    <DataTable
                        columns={transactionColumns}
                        data={summary.recent_transactions}
                        keyField="id"
                        emptyTitle="No stores movements recorded"
                        emptyHint="Goods receipts and departmental issues will appear here."
                    />
                </div>
            )}

            {/* Tab 3: Reorder List */}
            {activeTab === 'reorder' && (
                <div className="space-y-4">
                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Icon name="AlertCircle" className="h-5 w-5 text-amber-700" />
                            <div>
                                <h4 className="text-sm font-semibold text-amber-900">Reorder Threshold Alerts</h4>
                                <p className="text-xs text-amber-700">
                                    The following stock lines have dropped to or below minimum safety stock levels. Raise purchase requisitions to replenish municipal inventory.
                                </p>
                            </div>
                        </div>
                        <a
                            href="/procurement"
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <Icon name="Plus" className="h-4 w-4" />
                            <span>Raise procurement requisition</span>
                        </a>
                    </div>

                    <DataTable
                        columns={inventoryColumns}
                        data={lowStockItems}
                        keyField="id"
                        rowActions={inventoryActions}
                        emptyTitle="All stock levels are healthy"
                        emptyHint="No items are currently below reorder thresholds."
                    />
                </div>
            )}

            {/* MODAL: Bin Card Detail */}
            {selectedBinCardItem && (
                <Modal
                    isOpen={!!selectedBinCardItem}
                    onClose={() => setSelectedBinCardItem(null)}
                    title={`Bin Card: ${selectedBinCardItem.item_code} — ${selectedBinCardItem.name}`}
                    description={`Warehouse Location: ${selectedBinCardItem.bin || 'Unassigned'} · Category: ${selectedBinCardItem.category}`}
                    maxWidth="max-w-2xl"
                >
                    <div className="space-y-5">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-lg bg-slate-50 p-3 text-xs">
                            <div>
                                <span className="text-slate-500">Stock on hand</span>
                                <p className="text-base font-semibold text-slate-900">
                                    {selectedBinCardItem.stock} {selectedBinCardItem.unit}
                                </p>
                            </div>
                            <div>
                                <span className="text-slate-500">Reorder level</span>
                                <p className="text-base font-semibold text-slate-900">
                                    {selectedBinCardItem.reorder_level} {selectedBinCardItem.unit}
                                </p>
                            </div>
                            <div>
                                <span className="text-slate-500">Unit cost</span>
                                <p className="text-base font-semibold text-slate-900">
                                    ${selectedBinCardItem.unit_cost_usd}
                                </p>
                            </div>
                            <div>
                                <span className="text-slate-500">Total value</span>
                                <p className="text-base font-semibold text-emerald-800">
                                    ${selectedBinCardItem.total_valuation_usd}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                                Recent Bin Card Movements
                            </h4>
                            <div className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                                {summary.recent_transactions
                                    .filter((tx) => tx.item_code === selectedBinCardItem.item_code || tx.item_name === selectedBinCardItem.name)
                                    .map((tx) => (
                                        <div key={tx.id} className="flex items-center justify-between p-3 text-xs">
                                            <div>
                                                <span className="font-mono font-semibold text-slate-800">{tx.ref}</span>
                                                <p className="text-slate-500">{tx.department || tx.work_order || 'Warehouse'}</p>
                                            </div>
                                            <div className="text-right">
                                                <span className={`font-semibold tabular-nums ${tx.type === 'RECEIVE' ? 'text-emerald-700' : 'text-slate-900'}`}>
                                                    {tx.type === 'RECEIVE' ? `+${tx.quantity}` : `-${tx.quantity}`} {selectedBinCardItem.unit}
                                                </span>
                                                <p className="text-slate-400">${tx.total_usd}</p>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </div>

                        <div className="flex justify-between items-center pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => {
                                    setSelectedBinCardItem(null);
                                    setReceiveForm({ ...receiveForm, stores_item_id: selectedBinCardItem.id, unit_cost_usd: selectedBinCardItem.unit_cost_usd });
                                    setShowReceiveModal(true);
                                }}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <Icon name="Boxes" className="h-3.5 w-3.5" />
                                <span>Receive goods into bin</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedBinCardItem(null)}
                                className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                Close bin card
                            </button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* MODAL: Receive Goods (GRV) */}
            {showReceiveModal && (
                <Modal
                    isOpen={showReceiveModal}
                    onClose={() => setShowReceiveModal(false)}
                    title="Receive Goods into Central Stores (GRV)"
                    description="Record physical stock arrival from supplier delivery or purchase order."
                >
                    <form onSubmit={handleReceiveSubmit} className="space-y-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Stock Item <span className="text-rose-600">*</span>
                            </label>
                            <select
                                value={receiveForm.stores_item_id}
                                onChange={(e) => setReceiveForm({ ...receiveForm, stores_item_id: e.target.value })}
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                {summary.items.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.item_code} — {item.name} (Current: {item.stock} {item.unit})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Quantity Received <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={receiveForm.quantity}
                                    onChange={(e) => setReceiveForm({ ...receiveForm, quantity: e.target.value })}
                                    required
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Unit Cost (USD) <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={receiveForm.unit_cost_usd}
                                    onChange={(e) => setReceiveForm({ ...receiveForm, unit_cost_usd: e.target.value })}
                                    required
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Purchase Order / Delivery Note Reference <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                value={receiveForm.work_order_ref}
                                onChange={(e) => setReceiveForm({ ...receiveForm, work_order_ref: e.target.value })}
                                required
                                placeholder="PO-2026-MUT-0042 / DN-9843"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Authorized Receiving Officer
                            </label>
                            <input
                                type="text"
                                value={receiveForm.authorized_by}
                                onChange={(e) => setReceiveForm({ ...receiveForm, authorized_by: e.target.value })}
                                placeholder="Stores Controller / Quality Inspector"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowReceiveModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Post GRV & update bin card
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Issue Stock */}
            {showIssueModal && (
                <Modal
                    isOpen={showIssueModal}
                    onClose={() => setShowIssueModal(false)}
                    title="Stores Issue Note (SIN)"
                    description="Disburse stock from warehouse inventory to a municipal operating department."
                >
                    <form onSubmit={handleIssueSubmit} className="space-y-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Stock Item <span className="text-rose-600">*</span>
                            </label>
                            <select
                                value={issueForm.stores_item_id}
                                onChange={(e) => setIssueForm({ ...issueForm, stores_item_id: e.target.value })}
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                {summary.items.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.item_code} — {item.name} (Available: {item.stock} {item.unit})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Quantity to Issue <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                value={issueForm.quantity}
                                onChange={(e) => setIssueForm({ ...issueForm, quantity: e.target.value })}
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Recipient Municipal Department <span className="text-rose-600">*</span>
                            </label>
                            <select
                                value={issueForm.department_recipient}
                                onChange={(e) => setIssueForm({ ...issueForm, department_recipient: e.target.value })}
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                <option value="Engineering - Water & Sewer Reticulation">Engineering - Water & Sewer Reticulation</option>
                                <option value="Engineering - Roads & Bridges">Engineering - Roads & Bridges</option>
                                <option value="Housing & Community Services">Housing & Community Services</option>
                                <option value="Health & Environmental Services">Health & Environmental Services</option>
                                <option value="Emergency Services - Fire Brigade">Emergency Services - Fire Brigade</option>
                                <option value="Fleet Management Workshop">Fleet Management Workshop</option>
                                <option value="Central Administration">Central Administration</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Work Order / Service Requisition Ref <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                value={issueForm.work_order_ref}
                                onChange={(e) => setIssueForm({ ...issueForm, work_order_ref: e.target.value })}
                                required
                                placeholder="WO-2026-BURST-104"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Issued by (Stores Officer)
                                </label>
                                <input
                                    type="text"
                                    value={issueForm.issued_by}
                                    onChange={(e) => setIssueForm({ ...issueForm, issued_by: e.target.value })}
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Received by (Artisan / Supervisor)
                                </label>
                                <input
                                    type="text"
                                    value={issueForm.received_by}
                                    onChange={(e) => setIssueForm({ ...issueForm, received_by: e.target.value })}
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowIssueModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Issue stock & deduct inventory
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: New SKU Item */}
            {showAddItemModal && (
                <Modal
                    isOpen={showAddItemModal}
                    onClose={() => setShowAddItemModal(false)}
                    title="Register New Central Stores SKU"
                    description="Create a new tracked inventory item code, unit of measure, and safety reorder parameters."
                >
                    <form onSubmit={handleAddItemSubmit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Item Code (SKU) <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={itemForm.item_code}
                                    onChange={(e) => setItemForm({ ...itemForm, item_code: e.target.value })}
                                    required
                                    placeholder="STK-WTR-015"
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-mono focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Category <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    value={itemForm.category}
                                    onChange={(e) => setItemForm({ ...itemForm, category: e.target.value })}
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                >
                                    <option value="Water & Reticulation">Water & Reticulation</option>
                                    <option value="Roads & Civil Materials">Roads & Civil Materials</option>
                                    <option value="Electrical & Street Lighting">Electrical & Street Lighting</option>
                                    <option value="Vehicle & Plant Spares">Vehicle & Plant Spares</option>
                                    <option value="Fuel, Oils & Lubricants">Fuel, Oils & Lubricants</option>
                                    <option value="Building & Maintenance">Building & Maintenance</option>
                                    <option value="Stationery & Office Supplies">Stationery & Office Supplies</option>
                                    <option value="Protective Clothing (PPE)">Protective Clothing (PPE)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Item Name <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                value={itemForm.name}
                                onChange={(e) => setItemForm({ ...itemForm, name: e.target.value })}
                                required
                                placeholder="e.g. Sluice Gate Valve 110mm Cast Iron"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Unit of Measure <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    value={itemForm.unit_of_measure}
                                    onChange={(e) => setItemForm({ ...itemForm, unit_of_measure: e.target.value })}
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                >
                                    <option value="unit">unit</option>
                                    <option value="meter">meter</option>
                                    <option value="liter">liter</option>
                                    <option value="bag">bag</option>
                                    <option value="tonne">tonne</option>
                                    <option value="kg">kg</option>
                                    <option value="box">box</option>
                                    <option value="drum">drum</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Reorder Level <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={itemForm.reorder_level}
                                    onChange={(e) => setItemForm({ ...itemForm, reorder_level: parseInt(e.target.value) || 1 })}
                                    required
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Avg Unit Cost ($) <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={itemForm.average_unit_cost_usd}
                                    onChange={(e) => setItemForm({ ...itemForm, average_unit_cost_usd: e.target.value })}
                                    required
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Physical Bin Location
                            </label>
                            <input
                                type="text"
                                value={itemForm.bin_location}
                                onChange={(e) => setItemForm({ ...itemForm, bin_location: e.target.value })}
                                placeholder="Bay 3 Shelf B Bin 12"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowAddItemModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Add item to inventory
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </AppLayout>
    );
}
