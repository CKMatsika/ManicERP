import React, { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatStrip, { StatItem } from '../../Components/StatStrip';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import { Modal, ConfirmDialog } from '../../Components/FormComponents';
import { formatDate, formatDateTime, money, type SharedProps } from '../../lib';

export default function ProcurementIndex(props: any) {
    const page = usePage<SharedProps>();
    const { council, permissions } = page.props;
    const url = page.url;

    const [activeTab, setActiveTab] = useState<'requisitions' | 'sourcing' | 'orders' | 'receiving' | 'invoices' | 'payments' | 'suppliers'>(() => {
        try {
            const search = window.location.search;
            const params = new URLSearchParams(search);
            const tab = params.get('tab');
            if (tab && ['requisitions', 'sourcing', 'quotations', 'orders', 'receiving', 'invoices', 'payments', 'suppliers'].includes(tab)) {
                return (tab === 'quotations' ? 'sourcing' : tab) as any;
            }
        } catch { /* fallback */ }
        return 'requisitions';
    });

    // Synchronize activeTab whenever the URL changes (e.g. from sidebar clicks)
    useEffect(() => {
        try {
            const search = url.includes('?') ? url.substring(url.indexOf('?')) : window.location.search;
            const params = new URLSearchParams(search);
            const tab = params.get('tab');
            if (tab) {
                const mappedTab = tab === 'quotations' ? 'sourcing' : tab;
                if (['requisitions', 'sourcing', 'orders', 'receiving', 'invoices', 'payments', 'suppliers'].includes(mappedTab)) {
                    setActiveTab(mappedTab as any);
                }
            } else if (url.split('?')[0] === '/procurement') {
                setActiveTab('requisitions');
            }
        } catch { /* ignore */ }
    }, [url]);

    function handleTabChange(tabId: 'requisitions' | 'sourcing' | 'orders' | 'receiving' | 'invoices' | 'payments' | 'suppliers') {
        setActiveTab(tabId);
        const queryTab = tabId === 'sourcing' ? 'quotations' : tabId;
        window.history.replaceState(null, '', `/procurement?tab=${queryTab}`);
    }

    // Modals state
    const [showRequisitionModal, setShowRequisitionModal] = useState(false);
    const [showQuoteModal, setShowQuoteModal] = useState<any | null>(null);
    const [showAwardModal, setShowAwardModal] = useState<any | null>(null);
    const [showReceiveModal, setShowReceiveModal] = useState<{ po: any; line: any } | null>(null);
    const [showInvoiceModal, setShowInvoiceModal] = useState<{ po: any; line: any } | null>(null);
    const [showPaymentModal, setShowPaymentModal] = useState<any | null>(null);
    const [showSupplierModal, setShowSupplierModal] = useState(false);
    const [showBankModal, setShowBankModal] = useState<any | null>(null);

    // Form states
    const actionForm = useForm<any>({});

    function executeAction(operation: string, data: Record<string, any>, onSuccess?: () => void) {
        actionForm.transform(() => ({ operation, ...data }));
        actionForm.post('/procurement/actions', {
            preserveScroll: true,
            onSuccess: () => {
                if (onSuccess) onSuccess();
            },
        });
    }

    // Calculations for summary stat strip
    const approvedBudgets = props.budgets.filter((b: any) => b.status === 'approved');
    const allBudgetLines = approvedBudgets.flatMap((b: any) =>
        b.lines.map((l: any) => ({ ...l, budget_code: b.code }))
    );

    const pendingRequisitions = props.requisitions.filter((r: any) => ['draft', 'submitted'].includes(r.status));
    const activeOrders = props.purchaseOrders.filter((po: any) => po.status === 'approved');
    const totalOrderCommitmentMinor = props.purchaseOrders.reduce((acc: number, po: any) => acc + (Number(po.total_minor) || 0), 0);
    const pendingInvoices = props.invoices.filter((i: any) => ['matched', 'exception_pending', 'approved', 'part_paid'].includes(i.status));
    const totalInvoicesDueMinor = props.invoices.reduce((acc: number, i: any) => acc + (Number(i.amount_minor) - Number(i.paid_minor) - Number(i.credited_minor)), 0);

    const stats: StatItem[] = [
        {
            label: 'Requisitions in workflow',
            value: props.requisitions.length,
            helper: `${pendingRequisitions.length} pending review`,
            icon: 'FileText',
            tone: pendingRequisitions.length > 0 ? 'amber' : 'default',
        },
        {
            label: 'Purchase orders committed',
            value: money(totalOrderCommitmentMinor, council?.currency),
            helper: `${activeOrders.length} active contracts`,
            icon: 'ShoppingBag',
            tone: 'emerald',
        },
        {
            label: 'Goods receiving & stores',
            value: props.orderLines.filter((l: any) => Number(l.ordered_quantity) > Number(l.received_quantity)).length,
            helper: 'Lines awaiting warehouse delivery',
            icon: 'Boxes',
            tone: 'default',
        },
        {
            label: 'Accounts payable due',
            value: money(totalInvoicesDueMinor, council?.currency),
            helper: `${pendingInvoices.length} supplier invoices`,
            icon: 'CreditCard',
            tone: totalInvoicesDueMinor > 0 ? 'rose' : 'default',
        },
    ];

    // Requisitions Table Columns
    const requisitionColumns: Column<any>[] = [
        {
            key: 'reference',
            header: 'Requisition ref',
            sortable: true,
            render: (r) => <span className="font-semibold text-emerald-800">{r.reference}</span>,
        },
        {
            key: 'title',
            header: 'Need & justification',
            render: (r) => (
                <div>
                    <p className="font-medium text-slate-900">{r.title}</p>
                    <p className="text-xs text-slate-500 line-clamp-1">{r.justification}</p>
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Date logged',
            render: (r) => <span className="text-slate-600">{formatDate(r.created_at)}</span>,
        },
        {
            key: 'estimated_minor',
            header: 'Estimated cost',
            align: 'right',
            sortable: true,
            render: (r) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(r.estimated_minor, council?.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Approval status',
            render: (r) => <StatusBadge status={r.status} />,
        },
    ];

    const requisitionActions: RowAction<any>[] = [
        {
            label: 'Submit for departmental approval',
            icon: 'ArrowRight',
            hidden: (r) => r.status !== 'draft',
            onClick: (r) => executeAction('requisition.submit', { requisition_id: r.id }),
        },
        {
            label: 'Approve requisition & commit budget',
            icon: 'Check',
            tone: 'default',
            hidden: (r) => r.status !== 'submitted',
            onClick: (r) => executeAction('requisition.approve', { requisition_id: r.id }),
        },
        {
            label: 'Record PRAZ supplier quotation',
            icon: 'Plus',
            hidden: (r) => r.status !== 'approved',
            onClick: (r) => setShowQuoteModal(r),
        },
    ];

    // Purchase Orders Table Columns
    const orderColumns: Column<any>[] = [
        {
            key: 'reference',
            header: 'PO number',
            sortable: true,
            render: (po) => (
                <div>
                    <span className="font-semibold text-emerald-800">{po.reference}</span>
                    <span className="ml-1.5 text-[11px] text-slate-400">v{po.current_version}</span>
                </div>
            ),
        },
        {
            key: 'supplier_name',
            header: 'Contracted supplier',
            render: (po) => (
                <div>
                    <p className="font-medium text-slate-900">{po.supplier_name}</p>
                    <p className="font-mono text-xs text-slate-400">{po.supplier_number}</p>
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Order date',
            render: (po) => <span className="text-slate-600">{formatDate(po.created_at)}</span>,
        },
        {
            key: 'total_minor',
            header: 'Order value',
            align: 'right',
            render: (po) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(po.total_minor, council?.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'PO status',
            render: (po) => <StatusBadge status={po.status} />,
        },
    ];

    // Sourcing / Quotations Columns
    const quoteColumns: Column<any>[] = [
        {
            key: 'quote_reference',
            header: 'Quote reference',
            render: (q) => <span className="font-semibold text-slate-900">{q.quote_reference}</span>,
        },
        {
            key: 'supplier_number',
            header: 'Supplier code',
            render: (q) => <span className="font-mono text-xs text-slate-700">{q.supplier_number}</span>,
        },
        {
            key: 'quoted_minor',
            header: 'Quoted amount',
            align: 'right',
            render: (q) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(q.quoted_minor, council?.currency)}
                </span>
            ),
        },
        {
            key: 'evaluation_evidence',
            header: 'PRAZ compliance evidence',
            render: (q) => <span className="text-xs text-slate-600 line-clamp-1">{q.evaluation_evidence}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (q) => <StatusBadge status={q.status} />,
        },
    ];

    const quoteActions: RowAction<any>[] = [
        {
            label: 'Prepare procurement award',
            icon: 'Check',
            hidden: (q) => q.status !== 'captured',
            onClick: (q) => setShowAwardModal(q),
        },
    ];

    // Invoices Table Columns
    const invoiceColumns: Column<any>[] = [
        {
            key: 'supplier_invoice_number',
            header: 'Supplier invoice #',
            render: (i) => <span className="font-semibold text-slate-900">{i.supplier_invoice_number}</span>,
        },
        {
            key: 'supplier',
            header: 'Supplier',
            render: (i) => <span className="font-medium text-slate-800">{i.supplier}</span>,
        },
        {
            key: 'due_date',
            header: 'Due date',
            render: (i) => <span className="text-slate-600">{formatDate(i.due_date)}</span>,
        },
        {
            key: 'amount_minor',
            header: 'Invoice total',
            align: 'right',
            render: (i) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(i.amount_minor, council?.currency)}
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Balance due',
            align: 'right',
            render: (i) => {
                const bal = i.amount_minor - i.paid_minor - i.credited_minor;
                return (
                    <span className={`font-semibold tabular-nums ${bal > 0 ? 'text-rose-700' : 'text-slate-500'}`}>
                        {money(bal, council?.currency)}
                    </span>
                );
            },
        },
        {
            key: 'status',
            header: '3-way match',
            render: (i) => (
                <div>
                    <StatusBadge status={i.status} />
                    {i.match_exception_reason && (
                        <p className="mt-1 text-[11px] text-rose-600 line-clamp-1">{i.match_exception_reason}</p>
                    )}
                </div>
            ),
        },
    ];

    const invoiceActions: RowAction<any>[] = [
        {
            label: 'Approve & post to General Ledger',
            icon: 'Check',
            hidden: (i) => i.status !== 'matched',
            onClick: (i) => executeAction('invoice.approve', { invoice_id: i.id }),
        },
        {
            label: 'Prepare payment proposal',
            icon: 'CreditCard',
            hidden: (i) => !['approved', 'part_paid'].includes(i.status) || (i.amount_minor - i.paid_minor - i.credited_minor) <= 0,
            onClick: (i) => setShowPaymentModal(i),
        },
    ];

    // Suppliers Columns
    const supplierColumns: Column<any>[] = [
        {
            key: 'supplier_number',
            header: 'Supplier code',
            render: (s) => <span className="font-mono text-xs font-semibold text-emerald-800">{s.supplier_number}</span>,
        },
        {
            key: 'name',
            header: 'Registered entity name',
            render: (s) => <span className="font-medium text-slate-900">{s.name}</span>,
        },
        {
            key: 'bank_change_count',
            header: 'Banking profile',
            render: (s) => (
                <span className="text-xs text-slate-600">
                    {s.bank_change_count > 0 ? `${s.bank_change_count} bank account(s) on file` : 'No verified bank details'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'PRAZ onboarding status',
            render: (s) => <StatusBadge status={s.status} />,
        },
    ];

    const supplierActions: RowAction<any>[] = [
        {
            label: 'Approve supplier registration',
            icon: 'Check',
            hidden: (s) => s.status !== 'pending',
            onClick: (s) => executeAction('supplier.approve', { supplier_id: s.id }),
        },
        {
            label: 'Request bank account change',
            icon: 'Landmark',
            hidden: (s) => s.status !== 'approved',
            onClick: (s) => setShowBankModal(s),
        },
    ];

    return (
        <AppLayout title="Procurement & Supply Chain (PRAZ)">
            <Head title="Procurement & Supply Chain" />

            <PageHeader
                title="Public procurement & supply chain (PRAZ)"
                description="End-to-end municipal procurement cycle: budget votes, requisitions, PRAZ sourcing, purchase orders, central stores receiving (GRN), 3-way matching, and accounts payable."
                breadcrumbs={[
                    { label: 'Procurement & supply chain', href: '/procurement' },
                    { label: 'Procurement cycle (PRAZ)' },
                ]}
                primaryAction={{
                    label: 'New purchase requisition',
                    icon: 'Plus',
                    onClick: () => setShowRequisitionModal(true),
                }}
                secondaryActions={[
                    {
                        label: 'Onboard supplier',
                        icon: 'Users',
                        onClick: () => setShowSupplierModal(true),
                    },
                    {
                        label: 'View central stores inventory',
                        icon: 'Boxes',
                        href: '/stores',
                    },
                ]}
            />

            <StatStrip stats={stats} />

            {/* Procurement Lifecycle Navigation Tabs */}
            <div className="mb-6 border-b border-slate-200">
                <nav className="flex space-x-6 overflow-x-auto">
                    {[
                        { id: 'requisitions', label: '1. Purchase requisitions', count: props.requisitions.length },
                        { id: 'sourcing', label: '2. Quotations & awards (PRAZ)', count: props.quotations.length },
                        { id: 'orders', label: '3. Purchase orders', count: props.purchaseOrders.length },
                        { id: 'receiving', label: '4. Stores receiving (GRN)', count: props.orderLines.length },
                        { id: 'invoices', label: '5. Invoices & 3-way match', count: props.invoices.length },
                        { id: 'payments', label: '6. Payment settlement', count: props.proposals.length },
                        { id: 'suppliers', label: '7. Approved suppliers', count: props.suppliers.length },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => handleTabChange(tab.id as any)}
                            className={`flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-sm font-medium transition-colors ${
                                activeTab === tab.id
                                    ? 'border-emerald-700 text-emerald-800 font-semibold'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                            }`}
                        >
                            <span>{tab.label}</span>
                            <span className={`rounded-full px-2 py-0.5 text-xs ${activeTab === tab.id ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-slate-100 text-slate-600'}`}>
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </nav>
            </div>

            {/* Tab 1: Requisitions */}
            {activeTab === 'requisitions' && (
                <div className="space-y-4">
                    <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                <Icon name="FileText" className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">Internal Departmental Requisitions</h3>
                                <p className="text-xs text-slate-500">Every requisition requires budget vote verification before committing municipal funds.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowRequisitionModal(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <Icon name="Plus" className="h-4 w-4" />
                            <span>Create requisition</span>
                        </button>
                    </div>

                    <DataTable
                        columns={requisitionColumns}
                        data={props.requisitions}
                        keyField="id"
                        rowActions={requisitionActions}
                        emptyTitle="No purchase requisitions found"
                        emptyHint="Departmental officers can raise requisitions linked to active council budget votes."
                    />
                </div>
            )}

            {/* Tab 2: Quotations & Sourcing */}
            {activeTab === 'sourcing' && (
                <div className="space-y-6">
                    <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-4 text-xs text-amber-900">
                        <p className="font-semibold text-sm">PRAZ Statutory Sourcing Thresholds</p>
                        <p className="mt-0.5">
                            Under the Public Procurement and Disposal of Public Assets Act (PPDPA), competitive quotations must be obtained from approved registered suppliers with valid tax clearances.
                        </p>
                    </div>

                    <div>
                        <h3 className="mb-3 text-base font-semibold text-slate-900">Supplier Quotations Received</h3>
                        <DataTable
                            columns={quoteColumns}
                            data={props.quotations}
                            keyField="id"
                            rowActions={quoteActions}
                            emptyTitle="No quotations recorded yet"
                            emptyHint="Request quotations from vetted suppliers for approved requisitions."
                        />
                    </div>

                    {props.awards.length > 0 && (
                        <div className="mt-6">
                            <h3 className="mb-3 text-base font-semibold text-slate-900">Procurement Awards Awaiting PO Issuance</h3>
                            <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                                {props.awards.map((a: any) => (
                                    <div key={a.id} className="flex flex-wrap items-center justify-between p-4 gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">Requisition: {a.requisition_reference}</p>
                                            <p className="text-xs text-slate-500">{a.evaluation_summary}</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <StatusBadge status={a.status} />
                                            {a.status === 'pending_approval' && (
                                                <button
                                                    type="button"
                                                    onClick={() => executeAction('award.approve', { award_id: a.id })}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                                                >
                                                    <Icon name="Check" className="h-3.5 w-3.5" />
                                                    <span>Approve & generate PO</span>
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Tab 3: Purchase Orders */}
            {activeTab === 'orders' && (
                <div className="space-y-6">
                    <DataTable
                        columns={orderColumns}
                        data={props.purchaseOrders}
                        keyField="id"
                        emptyTitle="No purchase orders issued yet"
                        emptyHint="Approved awards automatically generate formal purchase orders."
                    />

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 className="text-base font-semibold text-slate-900 mb-2">Purchase Order Line Items & Delivery Status</h3>
                        <p className="text-xs text-slate-500 mb-4">Tracking quantities ordered, received into warehouse stores, and invoiced.</p>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-700">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2.5">PO Ref</th>
                                        <th className="px-3 py-2.5">Description</th>
                                        <th className="px-3 py-2.5 text-right">Ordered</th>
                                        <th className="px-3 py-2.5 text-right">Received</th>
                                        <th className="px-3 py-2.5 text-right">Invoiced</th>
                                        <th className="px-3 py-2.5 text-right">Unit Price</th>
                                        <th className="px-3 py-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {props.orderLines.map((line: any) => {
                                        const po = props.purchaseOrders.find((p: any) => p.id === line.purchase_order_id);
                                        const unfulfilled = Number(line.ordered_quantity) - Number(line.received_quantity);

                                        return (
                                            <tr key={line.id} className="h-12 hover:bg-slate-50/60">
                                                <td className="px-3 py-2 font-mono text-xs font-semibold text-emerald-800">
                                                    {line.purchase_order_reference}
                                                </td>
                                                <td className="px-3 py-2 font-medium text-slate-900">
                                                    {line.description}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-slate-700">
                                                    {line.ordered_quantity}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-emerald-800 font-semibold">
                                                    {line.received_quantity}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-slate-600">
                                                    {line.invoiced_quantity}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-slate-800">
                                                    {money(line.unit_price_minor, council?.currency)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {po && po.status === 'approved' && unfulfilled > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setShowReceiveModal({ po, line })}
                                                            className="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 border border-emerald-200"
                                                        >
                                                            <Icon name="Boxes" className="h-3 w-3" />
                                                            <span>Receive into stores</span>
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* Tab 4: Goods Receiving & Central Stores */}
            {activeTab === 'receiving' && (
                <div className="space-y-6">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Central Stores Receiving Dock (GRN)</h3>
                            <p className="mt-1 text-xs text-slate-500">
                                When suppliers physically deliver materials to council workshops or warehouses, record an official Goods Receiving Note (GRN) to update stock levels.
                            </p>
                        </div>
                        <a
                            href="/stores"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <Icon name="Boxes" className="h-4 w-4 text-slate-500" />
                            <span>Go to Central Stores</span>
                        </a>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {props.orderLines.map((line: any) => {
                            const po = props.purchaseOrders.find((p: any) => p.id === line.purchase_order_id);
                            const unfulfilled = Number(line.ordered_quantity) - Number(line.received_quantity);

                            return (
                                <div key={line.id} className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                        <span className="font-mono text-xs font-semibold text-emerald-800">
                                            {line.purchase_order_reference}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            Ordered: {line.ordered_quantity} units
                                        </span>
                                    </div>

                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{line.description}</p>
                                        <div className="mt-2 flex items-center justify-between text-xs text-slate-600">
                                            <span>Already received: <strong className="text-emerald-800">{line.received_quantity}</strong></span>
                                            <span>Outstanding: <strong className={unfulfilled > 0 ? 'text-amber-700' : 'text-slate-400'}>{unfulfilled}</strong></span>
                                        </div>
                                    </div>

                                    <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-500">
                                            Unit cost: {money(line.unit_price_minor, council?.currency)}
                                        </span>

                                        {po && po.status === 'approved' && unfulfilled > 0 ? (
                                            <button
                                                type="button"
                                                onClick={() => setShowReceiveModal({ po, line })}
                                                className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                                            >
                                                <Icon name="Boxes" className="h-3.5 w-3.5" />
                                                <span>Receive {unfulfilled} into stores</span>
                                            </button>
                                        ) : (
                                            <span className="text-xs font-medium text-emerald-700">Fulfilled</span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Tab 5: Invoices & 3-Way Match */}
            {activeTab === 'invoices' && (
                <div className="space-y-6">
                    <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Supplier Invoices & 3-Way Matching</h3>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Accounts payable automatically matches Purchase Order quantities against Stores Goods Receipts (GRN) and Vendor Invoices.
                            </p>
                        </div>
                    </div>

                    <DataTable
                        columns={invoiceColumns}
                        data={props.invoices}
                        keyField="id"
                        rowActions={invoiceActions}
                        emptyTitle="No supplier invoices recorded yet"
                        emptyHint="Record incoming invoices against received purchase orders to perform 3-way matching."
                    />
                </div>
            )}

            {/* Tab 6: Payments & Settlement */}
            {activeTab === 'payments' && (
                <div className="space-y-4">
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <h3 className="text-base font-semibold text-slate-900">Treasury Payment Proposals & Disbursements</h3>
                        <p className="text-xs text-slate-500">Segregated authorization required before releasing council electronic funds transfers.</p>
                    </div>

                    <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                        {props.proposals.map((p: any) => (
                            <div key={p.id} className="flex flex-wrap items-center justify-between p-4 gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900">
                                        Proposal for Invoice: {p.supplier_invoice_number}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Disbursement amount: <strong className="text-slate-800">{money(p.amount_minor, council?.currency)}</strong>
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <StatusBadge status={p.status} />
                                    {p.status === 'pending_approval' && (
                                        <button
                                            type="button"
                                            onClick={() => executeAction('payment.approve', { proposal_id: p.id })}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                                        >
                                            <Icon name="Check" className="h-3.5 w-3.5" />
                                            <span>Authorize payment</span>
                                        </button>
                                    )}
                                    {p.status === 'approved' && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const ref = window.prompt('Enter Bank Electronic Funds Transfer Settlement Reference:');
                                                if (ref) {
                                                    executeAction('payment.settle', { proposal_id: p.id, settlement_reference: ref });
                                                }
                                            }}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            <Icon name="Landmark" className="h-3.5 w-3.5" />
                                            <span>Record bank settlement</span>
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {props.proposals.length === 0 && (
                            <div className="p-8 text-center text-xs text-slate-500">
                                No active payment proposals. Approved supplier invoices can be prepared for treasury payment runs.
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Tab 7: Approved Suppliers */}
            {activeTab === 'suppliers' && (
                <div className="space-y-6">
                    <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">Vetted Council Suppliers & Contractors</h3>
                            <p className="mt-0.5 text-xs text-slate-500">PRAZ-registered vendors with dual-control bank account security.</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowSupplierModal(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <Icon name="Plus" className="h-4 w-4" />
                            <span>Onboard new supplier</span>
                        </button>
                    </div>

                    <DataTable
                        columns={supplierColumns}
                        data={props.suppliers}
                        keyField="id"
                        rowActions={supplierActions}
                        emptyTitle="No registered suppliers"
                        emptyHint="Onboard qualified vendors with valid tax clearances and PRAZ certifications."
                    />

                    {/* Dual-Control Bank Account Changes */}
                    {props.bankChanges.length > 0 && (
                        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5">
                            <h4 className="text-sm font-semibold text-slate-900 mb-3">
                                Dual-Control Supplier Bank Account Change Requests
                            </h4>
                            <div className="divide-y divide-slate-100">
                                {props.bankChanges.map((b: any) => (
                                    <div key={b.id} className="flex flex-wrap items-center justify-between py-3 gap-3">
                                        <div>
                                            <p className="text-sm font-medium text-slate-900">{b.display_name} ({b.supplier_number})</p>
                                            <p className="text-xs text-slate-500">Bank: {b.bank_name} · Account name: {b.account_name}</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <StatusBadge status={b.status} />
                                            {b.status === 'pending_approval' && (
                                                <button
                                                    type="button"
                                                    onClick={() => executeAction('bank.approve', { bank_change_id: b.id })}
                                                    className="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                                                >
                                                    Verify & Approve Bank
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* MODAL: Create Purchase Requisition */}
            {showRequisitionModal && (
                <Modal
                    isOpen={showRequisitionModal}
                    onClose={() => setShowRequisitionModal(false)}
                    title="New Purchase Requisition"
                    description="Initiate an internal request for goods or services linked to an approved budget vote."
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('requisition.create', {
                                budget_line_id: form.budget_line_id.value,
                                title: form.title.value,
                                justification: form.justification.value,
                                estimate_minor: Math.round(parseFloat(form.estimate_usd.value || '0') * 100),
                            }, () => setShowRequisitionModal(false));
                        }}
                        className="space-y-4"
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Approved Council Budget Vote Line <span className="text-rose-600">*</span>
                            </label>
                            <select
                                name="budget_line_id"
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                <option value="">Select budget line...</option>
                                {allBudgetLines.map((l: any) => (
                                    <option key={l.id} value={l.id}>
                                        {l.budget_code} / {l.account_code} — Available: {money(l.available_minor, council?.currency)}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Title / Departmental Need <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="title"
                                required
                                placeholder="e.g. 50x Heavy Duty Water Reticulation Valves (DN100)"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Estimated Cost ({council?.currency ?? 'USD'}) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="estimate_usd"
                                inputMode="decimal"
                                required
                                placeholder="0.00"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Operational Justification <span className="text-rose-600">*</span>
                            </label>
                            <textarea
                                name="justification"
                                required
                                rows={3}
                                placeholder="Explain why this procurement is required for municipal service delivery..."
                                className="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowRequisitionModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Create requisition
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Record Goods Receiving Note (GRN into Stores) */}
            {showReceiveModal && (
                <Modal
                    isOpen={!!showReceiveModal}
                    onClose={() => setShowReceiveModal(null)}
                    title="Stores Receiving Dock: Goods Received Note (GRN)"
                    description={`Receiving items for ${showReceiveModal.po.reference} · ${showReceiveModal.line.description}`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            const qty = parseFloat(form.quantity.value || '1');
                            executeAction('receipt.accept', {
                                purchase_order_id: showReceiveModal.po.id,
                                purchase_order_line_id: showReceiveModal.line.id,
                                quantity_milli: Math.round(qty * 1000),
                                idempotency_key: `GRN-${Date.now()}`,
                                note: form.note.value,
                            }, () => setShowReceiveModal(null));
                        }}
                        className="space-y-4"
                    >
                        <div className="rounded-lg bg-slate-50 p-3 text-xs space-y-1 text-slate-600">
                            <p><strong>Supplier:</strong> {showReceiveModal.po.supplier_name}</p>
                            <p><strong>Ordered quantity:</strong> {showReceiveModal.line.ordered_quantity}</p>
                            <p><strong>Previously received:</strong> {showReceiveModal.line.received_quantity}</p>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Quantity Received into Stores <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                defaultValue={Number(showReceiveModal.line.ordered_quantity) - Number(showReceiveModal.line.received_quantity)}
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Physical Inspection & Acceptance Findings <span className="text-rose-600">*</span>
                            </label>
                            <textarea
                                name="note"
                                required
                                rows={2}
                                defaultValue="Materials inspected by stores officer. Conforms to council specification and undamaged."
                                className="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowReceiveModal(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Accept delivery & issue GRN
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Record PRAZ Quotation */}
            {showQuoteModal && (
                <Modal
                    isOpen={!!showQuoteModal}
                    onClose={() => setShowQuoteModal(null)}
                    title="Record Supplier Quotation (PRAZ RFQ)"
                    description={`Quotation for Requisition ${showQuoteModal.reference} · ${showQuoteModal.title}`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('quote.submit', {
                                requisition_id: showQuoteModal.id,
                                supplier_id: form.supplier_id.value,
                                quote_reference: form.quote_reference.value,
                                amount_minor: Math.round(parseFloat(form.amount_usd.value || '0') * 100),
                                evaluation_evidence: form.evaluation_evidence.value,
                            }, () => setShowQuoteModal(null));
                        }}
                        className="space-y-4"
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Approved Supplier <span className="text-rose-600">*</span>
                            </label>
                            <select
                                name="supplier_id"
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                <option value="">Select supplier...</option>
                                {props.suppliers.filter((s: any) => s.status === 'approved').map((s: any) => (
                                    <option key={s.id} value={s.id}>
                                        {s.supplier_number} — {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Supplier Quotation Reference Number <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="quote_reference"
                                required
                                placeholder="e.g. QTE-2026-904"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Quoted Price ({council?.currency ?? 'USD'}) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="amount_usd"
                                inputMode="decimal"
                                required
                                placeholder="0.00"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                PRAZ Evaluation Compliance Evidence <span className="text-rose-600">*</span>
                            </label>
                            <textarea
                                name="evaluation_evidence"
                                required
                                rows={2}
                                placeholder="Tax clearance valid, PRAZ category registered, delivery lead time verified..."
                                className="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowQuoteModal(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Record quote
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Prepare Award */}
            {showAwardModal && (
                <Modal
                    isOpen={!!showAwardModal}
                    onClose={() => setShowAwardModal(null)}
                    title="Prepare Procurement Award"
                    description={`Award contract for Quote ${showAwardModal.quote_reference}`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('award.prepare', {
                                quotation_id: showAwardModal.id,
                                evaluation_summary: form.evaluation_summary.value,
                            }, () => setShowAwardModal(null));
                        }}
                        className="space-y-4"
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Evaluation & Tender Board Summary <span className="text-rose-600">*</span>
                            </label>
                            <textarea
                                name="evaluation_summary"
                                required
                                rows={3}
                                defaultValue="Recommended by procurement committee as lowest compliant responsive tender."
                                className="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowAwardModal(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Prepare award for approval
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Onboard Supplier */}
            {showSupplierModal && (
                <Modal
                    isOpen={showSupplierModal}
                    onClose={() => setShowSupplierModal(false)}
                    title="Onboard New Supplier / Contractor"
                    description="Register an accredited organisation into council supply chain master records."
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('supplier.create', {
                                party_id: form.party_id.value,
                                supplier_number: form.supplier_number.value,
                            }, () => setShowSupplierModal(false));
                        }}
                        className="space-y-4"
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Registered Entity / Organisation <span className="text-rose-600">*</span>
                            </label>
                            <select
                                name="party_id"
                                required
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            >
                                <option value="">Select registered organisation...</option>
                                {props.parties.map((p: any) => (
                                    <option key={p.id} value={p.id}>
                                        {p.display_name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Council Supplier Code <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="supplier_number"
                                required
                                placeholder="SUP-2026-001"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-mono focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowSupplierModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Submit supplier for approval
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Request Bank Change */}
            {showBankModal && (
                <Modal
                    isOpen={!!showBankModal}
                    onClose={() => setShowBankModal(null)}
                    title="Supplier Banking Profile Update"
                    description={`Update banking settlement details for ${showBankModal.name} (${showBankModal.supplier_number})`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('bank.request', {
                                supplier_id: showBankModal.id,
                                bank_name: form.bank_name.value,
                                account_name: form.account_name.value,
                                account_number: form.account_number.value,
                            }, () => setShowBankModal(null));
                        }}
                        className="space-y-4"
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Banking Institution <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="bank_name"
                                required
                                placeholder="e.g. Stanbic Bank Zimbabwe, CBZ, Ecobank"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Account Name (Must match registered entity) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="account_name"
                                required
                                defaultValue={showBankModal.name}
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Account Number <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="account_number"
                                required
                                placeholder="Account number"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-mono focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowBankModal(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Submit bank verification request
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Prepare Payment Proposal */}
            {showPaymentModal && (
                <Modal
                    isOpen={!!showPaymentModal}
                    onClose={() => setShowPaymentModal(null)}
                    title="Prepare Treasury Payment Proposal"
                    description={`Schedule payment for invoice ${showPaymentModal.supplier_invoice_number}`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.target as any;
                            executeAction('payment.create', {
                                invoice_id: showPaymentModal.id,
                                amount_minor: Math.round(parseFloat(form.amount_usd.value || '0') * 100),
                                idempotency_key: `PAY-${Date.now()}`,
                            }, () => setShowPaymentModal(null));
                        }}
                        className="space-y-4"
                    >
                        <div className="rounded-lg bg-slate-50 p-3 text-xs space-y-1 text-slate-600">
                            <p><strong>Supplier:</strong> {showPaymentModal.supplier}</p>
                            <p><strong>Invoice Total:</strong> {money(showPaymentModal.amount_minor, council?.currency)}</p>
                            <p><strong>Remaining Balance:</strong> {money(showPaymentModal.amount_minor - showPaymentModal.paid_minor - showPaymentModal.credited_minor, council?.currency)}</p>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Disbursement Amount ({council?.currency ?? 'USD'}) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                name="amount_usd"
                                inputMode="decimal"
                                required
                                defaultValue={((showPaymentModal.amount_minor - showPaymentModal.paid_minor - showPaymentModal.credited_minor) / 100).toFixed(2)}
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowPaymentModal(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                            >
                                Prepare payment proposal
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </AppLayout>
    );
}
