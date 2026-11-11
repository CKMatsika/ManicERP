import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import DataTable, { Column } from '../../Components/DataTable';
import { formatDate, formatDateTime, money, type SharedProps } from '../../lib';

type Bill = {
    id: string;
    number: string;
    service: string;
    period: string;
    total_minor: number;
    paid_minor: number;
    balance_minor: number;
    status: string;
    due_date: string | null;
    snapshot: any;
};

type Payment = {
    id: string;
    number: string;
    receipt: string | null;
    amount_minor: number;
    method: string;
    status: string;
    source: string | null;
    received_at: string | null;
};

export default function AccountShow({
    account,
    bills = [],
    payments = [],
}: {
    account: any;
    bills: Bill[];
    payments: Payment[];
}) {
    const { council, displayTimezone } = usePage<SharedProps>().props;
    const [activeTab, setActiveTab] = useState<'overview' | 'bills' | 'payments' | 'connections' | 'activity'>('overview');

    const lastPayment = payments.length > 0 ? payments[0] : null;

    const billColumns: Column<Bill>[] = [
        {
            key: 'number',
            header: 'Bill number',
            sortable: true,
            render: (b) => <span className="font-medium text-slate-900">{b.number}</span>,
        },
        {
            key: 'service',
            header: 'Service',
            render: (b) => <span className="capitalize font-medium text-slate-800">{b.service}</span>,
        },
        {
            key: 'period',
            header: 'Billing period',
            render: (b) => <span className="text-slate-600">{b.period}</span>,
        },
        {
            key: 'due_date',
            header: 'Due date',
            render: (b) => <span className="text-slate-600">{formatDate(b.due_date)}</span>,
        },
        {
            key: 'total_minor',
            header: 'Billed amount',
            align: 'right',
            render: (b) => <span>{money(b.total_minor, account.currency)}</span>,
        },
        {
            key: 'balance_minor',
            header: 'Balance',
            align: 'right',
            render: (b) => (
                <span className={`font-medium ${b.balance_minor > 0 ? 'text-slate-900' : 'text-slate-500'}`}>
                    {money(b.balance_minor, account.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (b) => <StatusBadge status={b.status} />,
        },
    ];

    const paymentColumns: Column<Payment>[] = [
        {
            key: 'receipt',
            header: 'Receipt number',
            render: (p) => <span className="font-medium text-slate-900">{p.receipt ?? p.number}</span>,
        },
        {
            key: 'received_at',
            header: 'Received at',
            render: (p) => <span className="text-slate-600">{formatDateTime(p.received_at, displayTimezone)}</span>,
        },
        {
            key: 'method',
            header: 'Tender method',
            render: (p) => <span className="capitalize text-slate-700">{p.method.replace('_', ' ')}</span>,
        },
        {
            key: 'source',
            header: 'Channel / Source',
            render: (p) => <span className="text-slate-600">{p.source ?? 'Cashier counter'}</span>,
        },
        {
            key: 'amount_minor',
            header: 'Amount tendered',
            align: 'right',
            render: (p) => (
                <span className="font-semibold text-emerald-800">
                    {money(p.amount_minor, account.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (p) => <StatusBadge status={p.status} />,
        },
    ];

    return (
        <AppLayout title={`Account ${account.account_number}`}>
            <Head title={`Account ${account.account_number}`} />

            <PageHeader
                title={`Account ${account.account_number}`}
                description={`${account.party?.name ?? 'Liable Customer'} · ${account.property?.address ?? 'Unassigned Property'}`}
                breadcrumbs={[
                    { label: 'Revenue & billing', href: '/revenue/accounts' },
                    { label: 'Accounts', href: '/revenue/accounts' },
                    { label: account.account_number },
                ]}
                badge={<StatusBadge status={account.status} />}
                primaryAction={{
                    label: 'Capture payment',
                    icon: 'Banknote',
                    href: `/revenue/cashier?account=${account.account_number}`,
                }}
                secondaryActions={[
                    {
                        label: 'Print statement',
                        icon: 'Printer',
                        onClick: () => window.print(),
                    },
                ]}
            />

            {/* Account Summary Strip */}
            <div className="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
                <div>
                    <span className="text-xs font-medium text-slate-500">Current outstanding</span>
                    <p className={`mt-1 text-2xl font-semibold tabular-nums ${account.outstanding_minor > 0 ? 'text-slate-900' : 'text-emerald-700'}`}>
                        {money(account.outstanding_minor, account.currency)}
                    </p>
                    <span className="text-xs text-slate-400">All services included</span>
                </div>
                <div>
                    <span className="text-xs font-medium text-slate-500">Last payment</span>
                    <p className="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
                        {lastPayment ? money(lastPayment.amount_minor, account.currency) : 'US$0.00'}
                    </p>
                    <span className="text-xs text-slate-400">
                        {lastPayment ? formatDate(lastPayment.received_at) : 'No recorded payment'}
                    </span>
                </div>
                <div>
                    <span className="text-xs font-medium text-slate-500">Tariff class</span>
                    <p className="mt-1 text-lg font-semibold capitalize text-slate-800">
                        {account.type?.replace('_', ' ') ?? 'Standard'}
                    </p>
                    <span className="text-xs text-slate-400">Rateable category</span>
                </div>
                <div>
                    <span className="text-xs font-medium text-slate-500">Account opened</span>
                    <p className="mt-1 text-lg font-semibold text-slate-800">
                        {formatDate(account.opened_on)}
                    </p>
                    <span className="text-xs text-slate-400">Active tenure</span>
                </div>
            </div>

            {/* Tab Navigation */}
            <div className="mb-5 border-b border-slate-200">
                <nav className="flex space-x-6">
                    {[
                        { id: 'overview', label: 'Overview & Premises', count: null },
                        { id: 'bills', label: 'Bills & calculation snapshots', count: bills.length },
                        { id: 'payments', label: 'Payment history', count: payments.length },
                        { id: 'connections', label: 'Meters & connections', count: (account.connections ?? []).length },
                        { id: 'activity', label: 'Audit & activity trail', count: null },
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveTab(tab.id as any)}
                            className={`flex items-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors ${
                                activeTab === tab.id
                                    ? 'border-emerald-700 text-emerald-800 font-semibold'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                            }`}
                        >
                            <span>{tab.label}</span>
                            {tab.count !== null && (
                                <span className={`rounded-full px-2 py-0.5 text-xs ${activeTab === tab.id ? 'bg-emerald-100 text-emerald-800 font-semibold' : 'bg-slate-100 text-slate-600'}`}>
                                    {tab.count}
                                </span>
                            )}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Tab Contents */}
            {activeTab === 'overview' && (
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Customer Profile Card */}
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">Liable Customer</h3>
                            <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 capitalize">
                                {account.party?.type ?? 'Individual'}
                            </span>
                        </div>
                        <div className="mt-4 space-y-3 text-sm">
                            <div>
                                <span className="text-xs text-slate-500">Full legal name</span>
                                <p className="font-medium text-slate-900">{account.party?.name ?? '—'}</p>
                            </div>
                            <div>
                                <span className="text-xs text-slate-500">Customer reference code</span>
                                <p className="font-mono text-xs text-slate-700">{account.party?.code ?? '—'}</p>
                            </div>
                            <div>
                                <span className="text-xs text-slate-500">Registered contact endpoints</span>
                                <ul className="mt-1 space-y-1 text-xs text-slate-700">
                                    {(account.party?.contacts ?? []).map((c: any, i: number) => (
                                        <li key={i} className="flex items-center gap-2">
                                            <span className="capitalize font-medium text-slate-600">{c.type}:</span>
                                            <span>{c.value}</span>
                                            {c.verified && (
                                                <span className="rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] text-emerald-700">Verified</span>
                                            )}
                                        </li>
                                    ))}
                                    {(account.party?.contacts ?? []).length === 0 && (
                                        <li className="text-slate-400">No registered phone or email contacts.</li>
                                    )}
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Property / Stand Card */}
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">Premises & Stand Cadastre</h3>
                            <Link href="/land/gis" className="text-xs text-emerald-700 hover:underline">
                                View in GIS Cadastre
                            </Link>
                        </div>
                        <div className="mt-4 space-y-3 text-sm">
                            <div>
                                <span className="text-xs text-slate-500">Stand parcel identifier</span>
                                <p className="font-semibold text-slate-900">{account.property?.parcel ?? '—'}</p>
                            </div>
                            <div>
                                <span className="text-xs text-slate-500">Civic street address</span>
                                <p className="text-slate-800">{account.property?.address ?? '—'}</p>
                                <p className="text-xs text-slate-500">{account.property?.suburb} · Ward {account.property?.ward}</p>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                                <p className="font-medium text-slate-800">Billing Notice Delivery</p>
                                <p className="mt-0.5">Physical demand notes are addressed to the resident liable occupier at this stand.</p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {activeTab === 'bills' && (
                <div>
                    <DataTable
                        columns={billColumns}
                        data={bills}
                        keyField="id"
                        emptyTitle="No bills generated yet"
                        emptyHint="Run and approve a monthly billing cycle to post tariffs and service charges."
                    />
                </div>
            )}

            {activeTab === 'payments' && (
                <div>
                    <DataTable
                        columns={paymentColumns}
                        data={payments}
                        keyField="id"
                        emptyTitle="No payment records found"
                        emptyHint="Receipts captured at cashier desks or through mobile payments will appear here."
                    />
                </div>
            )}

            {activeTab === 'connections' && (
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 className="text-sm font-semibold text-slate-900">Utility meters & service connections</h3>
                    <p className="mt-0.5 text-xs text-slate-500">Water and service meters associated with account {account.account_number}.</p>
                    <div className="mt-4 divide-y divide-slate-100">
                        {(account.connections ?? []).map((conn: any) => (
                            <div key={conn.number} className="py-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Icon name="Droplets" className="h-4 w-4 text-emerald-700" />
                                        <span className="font-medium capitalize text-slate-900">{conn.service} connection</span>
                                        <span className="text-xs text-slate-400">#{conn.number}</span>
                                    </div>
                                    <StatusBadge status={conn.status} />
                                </div>
                                <div className="mt-2 pl-6">
                                    <p className="text-xs text-slate-500">Meters linked:</p>
                                    {(conn.meters ?? []).map((meter: any) => (
                                        <p key={meter.serial} className="text-xs font-mono text-slate-700">
                                            Serial: {meter.serial} ({meter.status})
                                        </p>
                                    ))}
                                    {(conn.meters ?? []).length === 0 && (
                                        <p className="text-xs text-slate-400">Direct unmetered connection or standard flat tariff.</p>
                                    )}
                                </div>
                            </div>
                        ))}
                        {(account.connections ?? []).length === 0 && (
                            <p className="py-4 text-center text-xs text-slate-500">No active physical utility connections linked.</p>
                        )}
                    </div>
                </div>
            )}

            {activeTab === 'activity' && (
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 className="text-sm font-semibold text-slate-900">Account Audit Trail & Activity</h3>
                    <p className="mt-0.5 text-xs text-slate-500">Immutable ledger events and administrative adjustments.</p>
                    <div className="mt-4 flow-root">
                        <ul className="-mb-8">
                            <li className="relative pb-8">
                                <span className="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true" />
                                <div className="relative flex space-x-3">
                                    <div>
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 ring-8 ring-white">
                                            <Icon name="Check" className="h-4 w-4 text-emerald-700" />
                                        </span>
                                    </div>
                                    <div className="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                        <div>
                                            <p className="text-xs font-medium text-slate-900">Account status confirmed Active</p>
                                            <p className="text-xs text-slate-500">Tariff classification applied: {account.type}</p>
                                        </div>
                                        <div className="whitespace-nowrap text-right text-xs text-slate-500">
                                            {formatDate(account.opened_on)}
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li className="relative pb-8">
                                <div className="relative flex space-x-3">
                                    <div>
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 ring-8 ring-white">
                                            <Icon name="FileText" className="h-4 w-4 text-slate-600" />
                                        </span>
                                    </div>
                                    <div className="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                        <div>
                                            <p className="text-xs font-medium text-slate-900">Premises registration recorded</p>
                                            <p className="text-xs text-slate-500">Parcel {account.property?.parcel ?? 'unspecified'}</p>
                                        </div>
                                        <div className="whitespace-nowrap text-right text-xs text-slate-500">
                                            System record
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
