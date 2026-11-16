import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import { Modal } from '../../Components/FormComponents';
import Icon from '../../Components/Icon';
import StatStrip, { StatItem } from '../../Components/StatStrip';

/* ------------------------------------------------------------------ */
/*  Types                                                               */
/* ------------------------------------------------------------------ */

interface DomainEntry {
    id: string;
    host: string;
    tenant_id: string;
}

interface CouncilItem {
    id: string;
    code: string;
    name: string;
    type: 'urban' | 'rural' | 'municipality' | 'city';
    currency_code: string;
    timezone: string;
    status: 'active' | 'suspended' | 'pending';
    provisioned_at?: string;
    created_at?: string;
    domains: DomainEntry[];
    members_count: number;
}

interface PageProps {
    councils: CouncilItem[];
    total_councils: number;
    active_councils: number;
    total_domains: number;
    flash?: { success?: string; error?: string };
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                             */
/* ------------------------------------------------------------------ */

const COUNCIL_TYPES = [
    { value: 'urban',       label: 'Urban Council' },
    { value: 'rural',       label: 'Rural District Council' },
    { value: 'municipality', label: 'Municipality' },
    { value: 'city',        label: 'City Council' },
];

const CURRENCIES = [
    { value: 'USD', label: 'USD – US Dollar' },
    { value: 'ZWG', label: 'ZWG – Zimbabwe Gold' },
    { value: 'ZAR', label: 'ZAR – South African Rand' },
    { value: 'BWP', label: 'BWP – Botswana Pula' },
];

const TIMEZONES = [
    { value: 'Africa/Harare',       label: 'Africa/Harare (CAT, UTC+2)' },
    { value: 'Africa/Johannesburg', label: 'Africa/Johannesburg (SAST, UTC+2)' },
    { value: 'UTC',                 label: 'UTC' },
];

function typeLabel(type: string): string {
    return COUNCIL_TYPES.find(t => t.value === type)?.label ?? type;
}

/* ------------------------------------------------------------------ */
/*  Main Component                                                      */
/* ------------------------------------------------------------------ */

export default function Councils({ councils, total_councils, active_councils, total_domains, flash }: PageProps) {

    /* ---- UI state ------------------------------------------------- */
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit,   setShowEdit]   = useState(false);
    const [editTarget, setEditTarget] = useState<CouncilItem | null>(null);
    const [search,     setSearch]     = useState('');
    const [filterType, setFilterType] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [flashMsg, setFlashMsg] = useState<{ type: 'success'|'error'; text: string } | null>(
        flash?.success ? { type: 'success', text: flash.success }
      : flash?.error   ? { type: 'error',   text: flash.error   }
      : null
    );

    /* ---- Create form ---------------------------------------------- */
    const createForm = useForm({
        code:          '',
        name:          '',
        type:          'urban' as string,
        currency_code: 'USD',
        timezone:      'Africa/Harare',
        domain:        '',
    });

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/councils', {
            onSuccess: (page) => {
                const err = (page.props as any)?.flash?.error;
                if (!err) {
                    setShowCreate(false);
                    createForm.reset();
                }
            },
        });
    }

    /* ---- Edit form ------------------------------------------------- */
    const editForm = useForm({
        name:          '',
        type:          'urban' as string,
        currency_code: 'USD',
        timezone:      'Africa/Harare',
        status:        'active' as string,
    });

    function openEdit(council: CouncilItem) {
        setEditTarget(council);
        editForm.setData({
            name:          council.name,
            type:          council.type,
            currency_code: council.currency_code,
            timezone:      council.timezone ?? 'Africa/Harare',
            status:        council.status,
        });
        setShowEdit(true);
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editTarget) return;
        editForm.patch(`/admin/councils/${editTarget.id}`, {
            onSuccess: () => {
                setShowEdit(false);
                setEditTarget(null);
                setFlashMsg({ type: 'success', text: 'Council updated successfully.' });
                setTimeout(() => setFlashMsg(null), 5000);
            },
            onError: () => {
                setFlashMsg({ type: 'error', text: 'Failed to update council.' });
            },
        });
    }

    /* ---- Filtered list -------------------------------------------- */
    const filtered = councils.filter(c => {
        const matchSearch = !search ||
            c.name.toLowerCase().includes(search.toLowerCase()) ||
            c.code.toLowerCase().includes(search.toLowerCase());
        const matchType   = !filterType   || c.type   === filterType;
        const matchStatus = !filterStatus || c.status === filterStatus;
        return matchSearch && matchType && matchStatus;
    });

    /* ---- Stat strip ------------------------------------------------ */
    const stats: StatItem[] = [
        { label: 'Total Councils',  value: total_councils },
        { label: 'Active Councils', value: active_councils },
        { label: 'Suspended',       value: total_councils - active_councils },
        { label: 'Domains',         value: total_domains },
    ];

    /* ---- DataTable columns ---------------------------------------- */
    const columns: Column<CouncilItem>[] = [
        {
            key: 'code',
            header: 'Code',
            sortable: true,
            render: (c) => (
                <span className="font-mono font-semibold text-emerald-700 text-xs tracking-wide">
                    {c.code}
                </span>
            ),
        },
        {
            key: 'name',
            header: 'Council Name',
            sortable: true,
            render: (c) => (
                <div>
                    <p className="text-xs font-medium text-gray-900">{c.name}</p>
                    <p className="text-xs text-gray-400">{typeLabel(c.type)}</p>
                </div>
            ),
        },
        {
            key: 'currency_code',
            header: 'Currency',
            render: (c) => (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                    {c.currency_code}
                </span>
            ),
        },
        {
            key: 'domains',
            header: 'Domain',
            render: (c) => {
                const domain = c.domains?.[0];
                return domain
                    ? <span className="text-xs text-gray-600 font-mono">{domain.host}</span>
                    : <span className="text-xs text-gray-400 italic">No domain</span>;
            },
        },
        {
            key: 'members_count',
            header: 'Members',
            sortable: true,
            render: (c) => (
                <span className="text-xs font-semibold text-gray-700">
                    {c.members_count ?? 0}
                </span>
            ),
        },
        {
            key: 'provisioned_at',
            header: 'Provisioned',
            render: (c) => (
                <span className="text-xs text-gray-500">
                    {c.provisioned_at
                        ? new Date(c.provisioned_at).toLocaleDateString()
                        : c.created_at
                            ? new Date(c.created_at).toLocaleDateString()
                            : '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (c) => <StatusBadge status={c.status} />,
        },
    ];

    const rowActions: RowAction<CouncilItem>[] = [
        {
            label: 'Retry Provisioning',
            icon: 'RefreshCw',
            hidden: (c) => c.status === 'active',
            onClick: (c) => {
                router.post(`/admin/councils/${c.id}/retry`);
            },
        },
        {
            label: 'Edit Council',
            icon:  'Edit',
            onClick: openEdit,
        },
    ];

    /* ---------------------------------------------------------------- */
    /*  Render                                                           */
    /* ---------------------------------------------------------------- */
    return (
        <AppLayout title="Council Directory & Setup">
            <Head title="Council Directory" />

            {/* Flash Banner */}
            {flashMsg && (
                <div className={`mx-6 mt-4 px-4 py-3 rounded-lg text-xs font-medium flex items-center gap-2 ${
                    flashMsg.type === 'success'
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-red-50 text-red-800 border border-red-200'
                }`}>
                    <Icon name={flashMsg.type === 'success' ? 'CheckCircle' : 'AlertCircle'} size={14} />
                    {flashMsg.text}
                    <button className="ml-auto" onClick={() => setFlashMsg(null)}>
                        <Icon name="X" size={12} />
                    </button>
                </div>
            )}

            <PageHeader
                title="Council Directory & Setup"
                description="Provision new councils and manage existing council configurations"
                primaryAction={{
                    label: 'Create Council',
                    icon: 'Plus',
                    onClick: () => setShowCreate(true),
                }}
            />

            <div className="space-y-4">

                {/* Stat Strip */}
                <StatStrip stats={stats} />

                {/* Filter Row */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative flex-1 min-w-48">
                        <Icon name="Search" size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search by name or code…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>

                    <select
                        value={filterType}
                        onChange={e => setFilterType(e.target.value)}
                        className="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">All Types</option>
                        {COUNCIL_TYPES.map(t => (
                            <option key={t.value} value={t.value}>{t.label}</option>
                        ))}
                    </select>

                    <select
                        value={filterStatus}
                        onChange={e => setFilterStatus(e.target.value)}
                        className="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                {/* Data Table */}
                <DataTable
                    data={filtered}
                    columns={columns}
                    rowActions={rowActions}
                    keyField="id"
                    emptyTitle="No councils found"
                    emptyHint="Create your first council to get started."
                />
            </div>

            {/* ======================================================= */}
            {/*  Create Council Modal                                     */}
            {/* ======================================================= */}
            <Modal
                isOpen={showCreate}
                onClose={() => { setShowCreate(false); createForm.reset(); }}
                title="Create New Council"
                maxWidth="max-w-2xl"
            >
                <form onSubmit={submitCreate} className="space-y-5">

                    {/* Info banner */}
                    <div className="flex items-start gap-2 p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-700">
                        <Icon name="Info" size={14} className="mt-0.5 shrink-0" />
                        <span>
                            Creating a council will provision a dedicated database and seed default roles.
                            The <strong>code</strong> is permanent and used by the mobile app to identify the council.
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        {/* Code */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1">
                                Council Code <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={createForm.data.code}
                                onChange={e => createForm.setData('code', e.target.value.toUpperCase())}
                                placeholder="e.g. MUTARE"
                                maxLength={20}
                                className="w-full px-3 py-2 text-xs font-mono border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase"
                            />
                            {createForm.errors.code && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.code}</p>
                            )}
                            <p className="text-xs text-gray-400 mt-1">Short unique code, uppercase, no spaces. Used in mobile app login.</p>
                        </div>

                        {/* Type */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1">
                                Council Type <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={createForm.data.type}
                                onChange={e => createForm.setData('type', e.target.value)}
                                className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                {COUNCIL_TYPES.map(t => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                            {createForm.errors.type && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.type}</p>
                            )}
                        </div>
                    </div>

                    {/* Name */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 mb-1">
                            Council Name <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={e => createForm.setData('name', e.target.value)}
                            placeholder="e.g. Mutare City Council"
                            className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        {createForm.errors.name && (
                            <p className="text-xs text-red-500 mt-1">{createForm.errors.name}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        {/* Currency */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1">
                                Default Currency <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={createForm.data.currency_code}
                                onChange={e => createForm.setData('currency_code', e.target.value)}
                                className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                {CURRENCIES.map(c => (
                                    <option key={c.value} value={c.value}>{c.label}</option>
                                ))}
                            </select>
                            {createForm.errors.currency_code && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.currency_code}</p>
                            )}
                        </div>

                        {/* Timezone */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1">Timezone</label>
                            <select
                                value={createForm.data.timezone}
                                onChange={e => createForm.setData('timezone', e.target.value)}
                                className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                {TIMEZONES.map(tz => (
                                    <option key={tz.value} value={tz.value}>{tz.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Domain */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-700 mb-1">Domain / Host (optional)</label>
                        <input
                            type="text"
                            value={createForm.data.domain}
                            onChange={e => createForm.setData('domain', e.target.value)}
                            placeholder="e.g. mutare.manicerp.co.zw  (leave blank to skip)"
                            className="w-full px-3 py-2 text-xs font-mono border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        {createForm.errors.domain && (
                            <p className="text-xs text-red-500 mt-1">{createForm.errors.domain}</p>
                        )}
                        <p className="text-xs text-gray-400 mt-1">The hostname this council will be accessible from. Can be set later.</p>
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <button
                            type="button"
                            onClick={() => { setShowCreate(false); createForm.reset(); }}
                            className="px-4 py-2 text-xs text-gray-600 hover:text-gray-800 font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={createForm.processing}
                            className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-700 text-white text-xs font-semibold rounded-lg hover:bg-emerald-800 disabled:opacity-60 transition-colors"
                        >
                            {createForm.processing
                                ? <><Icon name="Loader" size={12} className="animate-spin" /> Provisioning…</>
                                : <><Icon name="Building2" size={12} /> Provision Council</>
                            }
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ======================================================= */}
            {/*  Edit Council Modal                                       */}
            {/* ======================================================= */}
            <Modal
                isOpen={showEdit}
                onClose={() => { setShowEdit(false); setEditTarget(null); }}
                title={`Edit Council — ${editTarget?.code ?? ''}`}
                maxWidth="max-w-2xl"
            >
                {editTarget && (
                    <form onSubmit={submitEdit} className="space-y-5">

                        {/* Name */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 mb-1">
                                Council Name <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={editForm.data.name}
                                onChange={e => editForm.setData('name', e.target.value)}
                                className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                            {editForm.errors.name && (
                                <p className="text-xs text-red-500 mt-1">{editForm.errors.name}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Type */}
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1">Council Type</label>
                                <select
                                    value={editForm.data.type}
                                    onChange={e => editForm.setData('type', e.target.value)}
                                    className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    {COUNCIL_TYPES.map(t => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1">Status</label>
                                <select
                                    value={editForm.data.status}
                                    onChange={e => editForm.setData('status', e.target.value)}
                                    className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Currency */}
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1">Default Currency</label>
                                <select
                                    value={editForm.data.currency_code}
                                    onChange={e => editForm.setData('currency_code', e.target.value)}
                                    className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    {CURRENCIES.map(c => (
                                        <option key={c.value} value={c.value}>{c.label}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Timezone */}
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1">Timezone</label>
                                <select
                                    value={editForm.data.timezone}
                                    onChange={e => editForm.setData('timezone', e.target.value)}
                                    className="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                >
                                    {TIMEZONES.map(tz => (
                                        <option key={tz.value} value={tz.value}>{tz.label}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                            <button
                                type="button"
                                onClick={() => { setShowEdit(false); setEditTarget(null); }}
                                className="px-4 py-2 text-xs text-gray-600 hover:text-gray-800 font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-700 text-white text-xs font-semibold rounded-lg hover:bg-emerald-800 disabled:opacity-60 transition-colors"
                            >
                                {editForm.processing
                                    ? <><Icon name="Loader" size={12} className="animate-spin" /> Saving…</>
                                    : <><Icon name="Save" size={12} /> Save Changes</>
                                }
                            </button>
                        </div>
                    </form>
                )}
            </Modal>
        </AppLayout>
    );
}

