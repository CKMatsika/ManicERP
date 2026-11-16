import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import { Modal } from '../../Components/FormComponents';
import Icon from '../../Components/Icon';
import StatStrip, { StatItem } from '../../Components/StatStrip';

interface CouncilOption {
    id: string;
    code: string;
    name: string;
    currency_code: string;
}

interface RoleOption {
    id: string;
    council_id: string;
    code: string;
    name: string;
    description?: string;
}

interface UserMembership {
    id: string;
    council_id: string;
    council_code: string;
    council_name: string;
    role_id: string;
    role_code: string;
    role_name: string;
    member_type: 'staff' | 'citizen';
    job_title?: string;
    status: string;
}

interface UserItem {
    id: string;
    name: string;
    email: string;
    phone?: string;
    status: 'active' | 'suspended';
    is_platform_admin: boolean;
    mfa_enabled: boolean;
    last_login_at?: string;
    created_at?: string;
    memberships: UserMembership[];
}

interface Props {
    users: UserItem[];
    councils: CouncilOption[];
    roles: RoleOption[];
    currentCouncilId?: string;
}

export default function UsersIndex({ users, councils, roles, currentCouncilId }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCouncilFilter, setSelectedCouncilFilter] = useState('ALL');
    const [selectedTypeFilter, setSelectedTypeFilter] = useState('ALL');

    // Modals
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingUser, setEditingUser] = useState<UserItem | null>(null);
    const [resettingUser, setResettingUser] = useState<UserItem | null>(null);

    // Create User Form
    const createForm = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        council_id: currentCouncilId || (councils[0]?.id ?? ''),
        role_id: roles[0]?.id ?? '',
        member_type: 'staff' as 'staff' | 'citizen',
        job_title: '',
        is_platform_admin: false,
    });

    // Edit User Form
    const editForm = useForm({
        name: '',
        phone: '',
        status: 'active' as 'active' | 'suspended',
        is_platform_admin: false,
        council_id: '',
        role_id: '',
        job_title: '',
    });

    // Reset Password Form
    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    // Filter users
    const filteredUsers = users.filter((u) => {
        const matchesSearch =
            u.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            u.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (u.phone && u.phone.includes(searchTerm));

        const matchesCouncil =
            selectedCouncilFilter === 'ALL' ||
            u.memberships.some((m) => m.council_id === selectedCouncilFilter);

        const matchesType =
            selectedTypeFilter === 'ALL' ||
            (selectedTypeFilter === 'admin' && u.is_platform_admin) ||
            (selectedTypeFilter === 'staff' && u.memberships.some((m) => m.member_type === 'staff')) ||
            (selectedTypeFilter === 'citizen' && u.memberships.some((m) => m.member_type === 'citizen'));

        return matchesSearch && matchesCouncil && matchesType;
    });

    // Summary stats
    const totalUsers = users.length;
    const staffCount = users.filter((u) => u.memberships.some((m) => m.member_type === 'staff')).length;
    const citizenCount = users.filter((u) => u.memberships.some((m) => m.member_type === 'citizen')).length;
    const adminCount = users.filter((u) => u.is_platform_admin).length;

    const stats: StatItem[] = [
        {
            label: 'Total council users',
            value: totalUsers,
            helper: 'Active identity records',
            icon: 'Users',
            tone: 'default',
        },
        {
            label: 'Council staff & officers',
            value: staffCount,
            helper: 'Assigned municipal roles',
            icon: 'ShieldCheck',
            tone: 'emerald',
        },
        {
            label: 'Citizens & ratepayers',
            value: citizenCount,
            helper: 'Portal registered accounts',
            icon: 'Building2',
            tone: 'default',
        },
        {
            label: 'Super administrators',
            value: adminCount,
            helper: 'System-wide access',
            icon: 'Sparkles',
            tone: 'amber',
        },
    ];

    function handleOpenEdit(user: UserItem) {
        const primaryMembership = user.memberships[0];
        setEditingUser(user);
        editForm.setData({
            name: user.name,
            phone: user.phone || '',
            status: user.status,
            is_platform_admin: user.is_platform_admin,
            council_id: primaryMembership?.council_id || (councils[0]?.id ?? ''),
            role_id: primaryMembership?.role_id || (roles[0]?.id ?? ''),
            job_title: primaryMembership?.job_title || '',
        });
    }

    function handleCreateSubmit(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => {
                setShowCreateModal(false);
                createForm.reset();
            },
        });
    }

    function handleEditSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!editingUser) return;
        editForm.patch(`/admin/users/${editingUser.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUser(null);
            },
        });
    }

    function handlePasswordSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!resettingUser) return;
        passwordForm.post(`/admin/users/${resettingUser.id}/reset-password`, {
            preserveScroll: true,
            onSuccess: () => {
                setResettingUser(null);
                passwordForm.reset();
            },
        });
    }

    const columns: Column<UserItem>[] = [
        {
            key: 'name',
            header: 'User name & email',
            render: (user) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-slate-900">{user.name}</span>
                            {user.is_platform_admin && (
                                <span className="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                                    Super Admin
                                </span>
                            )}
                        </div>
                        <span className="font-mono text-xs text-slate-500">{user.email}</span>
                    </div>
                </div>
            ),
        },
        {
            key: 'memberships',
            header: 'Council & assigned role',
            render: (user) => {
                if (user.memberships.length === 0) {
                    return <span className="text-xs text-slate-400 italic">No council membership</span>;
                }
                return (
                    <div className="space-y-1">
                        {user.memberships.map((m) => (
                            <div key={m.id} className="text-xs">
                                <span className="font-semibold text-slate-800">{m.council_name}</span>{' '}
                                <span className="font-mono text-slate-400">({m.council_code})</span>
                                <div className="text-slate-500">
                                    <span className="capitalize">{m.role_name}</span>
                                    {m.job_title && <span> • {m.job_title}</span>}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            },
        },
        {
            key: 'member_type',
            header: 'Type',
            render: (user) => {
                const isStaff = user.memberships.some((m) => m.member_type === 'staff');
                const isCitizen = user.memberships.some((m) => m.member_type === 'citizen');
                return (
                    <div className="flex gap-1">
                        {isStaff && (
                            <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-200">
                                Staff
                            </span>
                        )}
                        {isCitizen && (
                            <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700 border border-sky-200">
                                Citizen
                            </span>
                        )}
                        {!isStaff && !isCitizen && (
                            <span className="rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500 border border-slate-200">
                                None
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'Status',
            render: (user) => (
                <StatusBadge
                    status={user.status === 'active' ? 'active' : 'inactive'}
                    label={user.status === 'active' ? 'Active' : 'Suspended'}
                />
            ),
        },
        {
            key: 'last_login_at',
            header: 'Last active',
            render: (user) => (
                <span className="text-xs text-slate-500">
                    {user.last_login_at ? new Date(user.last_login_at).toLocaleDateString('en-GB') : 'Never'}
                </span>
            ),
        },
    ];

    const rowActions: RowAction<UserItem>[] = [
        {
            label: 'Edit user & role',
            icon: 'Edit',
            onClick: (user) => handleOpenEdit(user),
        },
        {
            label: 'Reset password',
            icon: 'Key',
            onClick: (user) => {
                setResettingUser(user);
                passwordForm.reset();
            },
        },
    ];

    return (
        <AppLayout title="User & Access Management">
            <Head title="User Management - ManicERP" />

            <PageHeader
                title="User & Access Management"
                description="Manage council administration staff, finance approvers, revenue clerks, cashiers, and citizen mobile accounts."
                breadcrumbs={[
                    { label: 'Administration', href: '/admin/audit' },
                    { label: 'User & Access Management' },
                ]}
                primaryAction={{
                    label: 'Create new user',
                    icon: 'Plus',
                    onClick: () => {
                        createForm.reset();
                        setShowCreateModal(true);
                    },
                }}
            />

            <StatStrip stats={stats} />

            {/* Filter and Search Bar */}
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white p-3">
                <div className="flex flex-1 items-center gap-2">
                    <Icon name="Search" className="h-4 w-4 text-slate-400" />
                    <input
                        type="search"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        placeholder="Search by user name, email, or phone..."
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={selectedCouncilFilter}
                        onChange={(e) => setSelectedCouncilFilter(e.target.value)}
                        className="h-9 rounded-lg border border-slate-200 px-2.5 text-xs text-slate-700 focus:border-emerald-600 focus:outline-none"
                    >
                        <option value="ALL">All Councils</option>
                        {councils.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.code} — {c.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={selectedTypeFilter}
                        onChange={(e) => setSelectedTypeFilter(e.target.value)}
                        className="h-9 rounded-lg border border-slate-200 px-2.5 text-xs text-slate-700 focus:border-emerald-600 focus:outline-none"
                    >
                        <option value="ALL">All Account Types</option>
                        <option value="staff">Staff & Officers</option>
                        <option value="citizen">Citizens & Ratepayers</option>
                        <option value="admin">Super Administrators</option>
                    </select>
                </div>
            </div>

            {/* Users Table */}
            <DataTable
                columns={columns}
                data={filteredUsers}
                keyField="id"
                rowActions={rowActions}
                emptyTitle="No users found"
                emptyHint="Create users to grant staff or citizens access to the council ERP and mobile app."
                emptyAction={{
                    label: 'Create new user',
                    onClick: () => setShowCreateModal(true),
                }}
            />

            {/* MODAL: Create New User */}
            {showCreateModal && (
                <Modal
                    isOpen={showCreateModal}
                    onClose={() => setShowCreateModal(false)}
                    title="Create New User"
                    description="Add a new staff member or citizen account into the local authority database."
                >
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        {createForm.hasErrors && (
                            <div className="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 space-y-1">
                                {Object.values(createForm.errors).map((err, i) => (
                                    <p key={i}>• {err}</p>
                                ))}
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Full Name <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder="e.g. Tendai Moyo"
                                className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Email Address <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="email"
                                    required
                                    value={createForm.data.email}
                                    onChange={(e) => createForm.setData('email', e.target.value)}
                                    placeholder="user@example.com"
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">Phone Number</label>
                                <input
                                    type="text"
                                    value={createForm.data.phone}
                                    onChange={(e) => createForm.setData('phone', e.target.value)}
                                    placeholder="+263 77..."
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Initial Password (min. 8 characters) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="password"
                                required
                                minLength={8}
                                value={createForm.data.password}
                                onChange={(e) => createForm.setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Council Workspace <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    required
                                    value={createForm.data.council_id}
                                    onChange={(e) => createForm.setData('council_id', e.target.value)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    {councils.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.code} — {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Assigned Role <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    required
                                    value={createForm.data.role_id}
                                    onChange={(e) => createForm.setData('role_id', e.target.value)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    {roles
                                        .filter((r) => !createForm.data.council_id || r.council_id === createForm.data.council_id)
                                        .map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.name} ({r.code})
                                            </option>
                                        ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">
                                    Account Type <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    value={createForm.data.member_type}
                                    onChange={(e) => createForm.setData('member_type', e.target.value as any)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    <option value="staff">Council Staff / Officer</option>
                                    <option value="citizen">Citizen / Ratepayer</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700">Job Title / Position</label>
                                <input
                                    type="text"
                                    value={createForm.data.job_title}
                                    onChange={(e) => createForm.setData('job_title', e.target.value)}
                                    placeholder="e.g. Chief Accountant / Ratepayer"
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>

                        <div className="rounded-lg bg-slate-50 p-3 border border-slate-200">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={createForm.data.is_platform_admin}
                                    onChange={(e) => createForm.setData('is_platform_admin', e.target.checked)}
                                    className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                                />
                                <span className="text-xs font-semibold text-slate-900">
                                    Grant Super Administrator privileges
                                </span>
                            </label>
                            <p className="mt-1 text-[11px] text-slate-500">
                                Allows cross-council tenant management, system module activations, and full access.
                            </p>
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setShowCreateModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                            >
                                {createForm.processing ? 'Creating...' : 'Create User'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Edit User */}
            {editingUser && (
                <Modal
                    isOpen={Boolean(editingUser)}
                    onClose={() => setEditingUser(null)}
                    title={`Edit User: ${editingUser.name}`}
                    description="Update account profile, active status, and council roles."
                >
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        {editForm.hasErrors && (
                            <div className="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 space-y-1">
                                {Object.values(editForm.errors).map((err, i) => (
                                    <p key={i}>• {err}</p>
                                ))}
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-medium text-slate-700">Full Name</label>
                            <input
                                type="text"
                                required
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                                className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">Account Status</label>
                                <select
                                    value={editForm.data.status}
                                    onChange={(e) => editForm.setData('status', e.target.value as any)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended / Deactivated</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">Phone</label>
                                <input
                                    type="text"
                                    value={editForm.data.phone}
                                    onChange={(e) => editForm.setData('phone', e.target.value)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700">Council</label>
                                <select
                                    value={editForm.data.council_id}
                                    onChange={(e) => editForm.setData('council_id', e.target.value)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    {councils.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.code} — {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700">Role</label>
                                <select
                                    value={editForm.data.role_id}
                                    onChange={(e) => editForm.setData('role_id', e.target.value)}
                                    className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none"
                                >
                                    {roles
                                        .filter((r) => !editForm.data.council_id || r.council_id === editForm.data.council_id)
                                        .map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.name} ({r.code})
                                            </option>
                                        ))}
                                </select>
                            </div>
                        </div>

                        <div className="rounded-lg bg-slate-50 p-3 border border-slate-200">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={editForm.data.is_platform_admin}
                                    onChange={(e) => editForm.setData('is_platform_admin', e.target.checked)}
                                    className="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                                />
                                <span className="text-xs font-semibold text-slate-900">
                                    Super Administrator privileges
                                </span>
                            </label>
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setEditingUser(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                            >
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* MODAL: Reset Password */}
            {resettingUser && (
                <Modal
                    isOpen={Boolean(resettingUser)}
                    onClose={() => setResettingUser(null)}
                    title={`Reset Password: ${resettingUser.name}`}
                    description={`Set a new login password for ${resettingUser.email}.`}
                >
                    <form onSubmit={handlePasswordSubmit} className="space-y-4">
                        {passwordForm.hasErrors && (
                            <div className="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 space-y-1">
                                {Object.values(passwordForm.errors).map((err, i) => (
                                    <p key={i}>• {err}</p>
                                ))}
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-medium text-slate-700">New Password (min 8 chars)</label>
                            <input
                                type="password"
                                required
                                minLength={8}
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">Confirm New Password</label>
                            <input
                                type="password"
                                required
                                minLength={8}
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                placeholder="••••••••"
                                className="mt-1 h-9 w-full rounded-lg border border-slate-300 px-3 text-xs focus:border-emerald-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setResettingUser(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={passwordForm.processing}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                            >
                                {passwordForm.processing ? 'Resetting...' : 'Set Password'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </AppLayout>
    );
}

