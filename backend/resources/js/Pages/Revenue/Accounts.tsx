import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatStrip, { StatItem } from '../../Components/StatStrip';
import FilterBar from '../../Components/FilterBar';
import BulkActions from '../../Components/BulkActions';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import { money, type SharedProps } from '../../lib';

type Account = {
    id: string;
    account_number: string;
    party: string | null;
    property: string | null;
    ward: string | null;
    type: string;
    status: string;
    outstanding_minor: number;
};

export default function Accounts({
    accounts,
    search,
}: {
    accounts: {
        data: Account[];
        total?: number;
        from?: number;
        to?: number;
        per_page?: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    search: string;
}) {
    const { council } = usePage<SharedProps>().props;
    const [selectedRows, setSelectedRows] = useState<string[]>([]);

    // Compute stats from data
    const totalOutstandingMinor = accounts.data.reduce((acc, a) => acc + (a.outstanding_minor || 0), 0);
    const activeCount = accounts.data.filter((a) => a.status === 'active').length;
    const overdueCount = accounts.data.filter((a) => a.outstanding_minor > 0).length;

    const stats: StatItem[] = [
        {
            label: 'Total accounts',
            value: accounts.total ?? accounts.data.length,
            icon: 'Users',
            helper: 'Registered records',
        },
        {
            label: 'Active ledger accounts',
            value: activeCount,
            icon: 'CheckCircle2',
            tone: 'emerald',
        },
        {
            label: 'Accounts with balance',
            value: overdueCount,
            icon: 'AlertCircle',
            tone: overdueCount > 0 ? 'amber' : 'default',
        },
        {
            label: 'Sample outstanding',
            value: money(totalOutstandingMinor, council?.currency),
            icon: 'Landmark',
            tone: totalOutstandingMinor > 0 ? 'rose' : 'default',
        },
    ];

    const columns: Column<Account>[] = [
        {
            key: 'account_number',
            header: 'Account number',
            sortable: true,
            render: (row) => (
                <Link
                    href={`/revenue/accounts/${row.id}`}
                    className="font-medium text-emerald-800 hover:text-emerald-950 hover:underline"
                >
                    {row.account_number}
                </Link>
            ),
        },
        {
            key: 'party',
            header: 'Liable customer',
            render: (row) => (
                <div>
                    <span className="font-medium text-slate-900">{row.party ?? 'Unassigned customer'}</span>
                </div>
            ),
        },
        {
            key: 'property',
            header: 'Property / Stand',
            render: (row) => <span className="text-slate-600">{row.property ?? '—'}</span>,
        },
        {
            key: 'ward',
            header: 'Ward',
            render: (row) => <span className="text-slate-600">{row.ward ?? '—'}</span>,
        },
        {
            key: 'type',
            header: 'Tariff class',
            render: (row) => (
                <span className="capitalize text-slate-700">{row.type.replace('_', ' ')}</span>
            ),
        },
        {
            key: 'outstanding_minor',
            header: 'Outstanding balance',
            align: 'right',
            sortable: true,
            render: (row) => {
                const isOverdue = row.outstanding_minor > 0;
                return (
                    <span className={`font-medium ${isOverdue ? 'text-slate-900' : 'text-slate-600'}`}>
                        {money(row.outstanding_minor, council?.currency)}
                    </span>
                );
            },
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const rowActions: RowAction<Account>[] = [
        {
            label: 'View statement & bills',
            icon: 'Eye',
            onClick: (row) => router.get(`/revenue/accounts/${row.id}`),
        },
        {
            label: 'Capture cashier payment',
            icon: 'Banknote',
            onClick: (row) => router.get(`/revenue/cashier?account=${row.account_number}`),
        },
    ];

    return (
        <AppLayout title="Customer accounts">
            <Head title="Customer accounts" />

            <PageHeader
                title="Customer accounts"
                description="Master registry of rateable customer accounts, premises, and outstanding balances."
                breadcrumbs={[{ label: 'Revenue & billing', href: '/revenue/accounts' }, { label: 'Accounts' }]}
                primaryAction={{
                    label: 'New customer account',
                    icon: 'Plus',
                    onClick: () => {
                        window.alert('Account creation is managed via property registration and customer onboarding.');
                    },
                }}
            />

            <StatStrip stats={stats} />

            <FilterBar
                searchValue={search}
                searchPlaceholder="Search accounts, customer names, or parcel IDs..."
                filters={[
                    {
                        key: 'status',
                        label: 'Status',
                        options: [
                            { label: 'Active', value: 'active' },
                            { label: 'Draft', value: 'draft' },
                            { label: 'Suspended', value: 'suspended' },
                            { label: 'Closed', value: 'closed' },
                        ],
                    },
                    {
                        key: 'type',
                        label: 'Tariff class',
                        options: [
                            { label: 'Residential', value: 'residential' },
                            { label: 'Commercial', value: 'commercial' },
                            { label: 'Industrial', value: 'industrial' },
                            { label: 'Institutional', value: 'institutional' },
                        ],
                    },
                ]}
                exportOptions={[
                    {
                        label: 'Export CSV',
                        onClick: () => window.print(),
                    },
                    {
                        label: 'Export PDF Summary',
                        onClick: () => window.print(),
                    },
                ]}
            />

            <BulkActions
                selectedCount={selectedRows.length}
                totalCount={accounts.data.length}
                onClearSelection={() => setSelectedRows([])}
                actions={[
                    {
                        label: 'Export statements',
                        icon: 'Download',
                        onClick: () => window.alert(`Exporting statements for ${selectedRows.length} accounts`),
                    },
                    {
                        label: 'Print demand notices',
                        icon: 'Printer',
                        onClick: () => window.alert(`Printing demand notices for ${selectedRows.length} accounts`),
                    },
                ]}
            />

            <DataTable
                columns={columns}
                data={accounts.data}
                keyField="id"
                selectedRows={selectedRows}
                onSelectRows={setSelectedRows}
                rowActions={rowActions}
                onRowClick={(row) => router.get(`/revenue/accounts/${row.id}`)}
                emptyTitle="No customer accounts match your search"
                emptyHint="Try clearing your filters or search terms. New accounts are created when premises are registered."
                pagination={{
                    total: accounts.total,
                    from: accounts.from,
                    to: accounts.to,
                    links: accounts.links,
                    onPageChange: (url) => router.get(url, {}, { preserveState: true }),
                }}
            />
        </AppLayout>
    );
}
