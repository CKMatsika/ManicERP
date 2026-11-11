import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import { formatDate, formatDateTime, money, reportMoney, type SharedProps } from '../../lib';
import Icon from '../../Components/Icon';

export default function Ledger({
    account,
    from,
    to,
    rows = [],
    balance_minor = 0,
}: {
    account: string;
    from: string;
    to: string;
    rows: any[];
    balance_minor: number;
}) {
    const { council, displayTimezone, auth } = usePage<SharedProps>().props;
    const { data, setData, get } = useForm({
        account: account,
        from: from,
        to: to,
    });

    function handleFilter(e: React.FormEvent) {
        e.preventDefault();
        get('/accounting/reports/ledger', { preserveState: true });
    }

    // Calculate running balance per row
    let runningTotal = 0;
    const computedRows = rows.map((row) => {
        const debit = row.debit_minor || 0;
        const credit = row.credit_minor || 0;
        runningTotal += debit - credit;
        return {
            ...row,
            running_minor: runningTotal,
        };
    });

    return (
        <AppLayout title={`General ledger ${account}`}>
            <Head title={`General ledger ${account}`} />

            {/* Print Header */}
            <div className="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-xl font-bold uppercase tracking-wider">{council?.name ?? 'City of Harare'}</h1>
                        <h2 className="text-base font-semibold">General Ledger Account Detail</h2>
                        <p className="text-xs text-slate-600">
                            Account: <strong className="font-mono">{account}</strong> · Period: {formatDate(from)} to {formatDate(to)}
                        </p>
                    </div>
                    <div className="text-right text-xs text-slate-500">
                        <p>Generated: {formatDateTime(new Date().toISOString(), displayTimezone)}</p>
                        <p>User: {auth?.name ?? 'System'}</p>
                    </div>
                </div>
            </div>

            <div className="print:hidden">
                <PageHeader
                    title={`General ledger: ${account}`}
                    description={`Detailed posting audit trail from ${formatDate(from)} to ${formatDate(to)}.`}
                    breadcrumbs={[
                        { label: 'Finance & accounting', href: '/accounting/journals' },
                        { label: 'Reports', href: '/accounting/reports/trial-balance' },
                        { label: 'General ledger' },
                    ]}
                    secondaryActions={[
                        {
                            label: 'Back to trial balance',
                            href: '/accounting/reports/trial-balance',
                            icon: 'Scale',
                        },
                        {
                            label: 'Print statement',
                            icon: 'Printer',
                            onClick: () => window.print(),
                        },
                    ]}
                />

                {/* Filter Toolbar */}
                <div className="mb-6 rounded-xl border border-slate-200 bg-white p-4">
                    <form onSubmit={handleFilter} className="flex flex-wrap items-center gap-4 text-xs text-slate-700">
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-slate-600">Account:</span>
                            <input
                                type="text"
                                value={data.account}
                                onChange={(e) => setData('account', e.target.value)}
                                placeholder="Account code"
                                className="h-9 w-32 rounded-lg border border-slate-300 px-3 text-sm font-mono focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-slate-600">Date from:</span>
                            <input
                                type="date"
                                value={data.from}
                                onChange={(e) => setData('from', e.target.value)}
                                className="h-9 rounded-lg border border-slate-300 px-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-slate-600">Date to:</span>
                            <input
                                type="date"
                                value={data.to}
                                onChange={(e) => setData('to', e.target.value)}
                                className="h-9 rounded-lg border border-slate-300 px-2.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>
                        <button
                            type="submit"
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <span>Filter ledger</span>
                        </button>
                    </form>
                </div>

                {/* Summary Strip */}
                <div className="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
                    <div>
                        <span className="text-xs font-medium text-slate-500">Closing balance</span>
                        <p className="mt-1 text-2xl font-semibold tabular-nums text-slate-900">
                            {reportMoney(balance_minor, council?.currency, true)}
                        </p>
                        <span className="text-xs text-slate-400">Net ending position</span>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-slate-500">Posted lines</span>
                        <p className="mt-1 text-2xl font-semibold tabular-nums text-slate-800">
                            {rows.length}
                        </p>
                        <span className="text-xs text-slate-400">Transactions in window</span>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-slate-500">Active period</span>
                        <p className="mt-1 text-base font-semibold text-slate-800">
                            {formatDate(from)} – {formatDate(to)}
                        </p>
                        <span className="text-xs text-slate-400">Inclusive date range</span>
                    </div>
                </div>
            </div>

            {/* Structured Table */}
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-800">
                        <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" className="w-28 px-4 py-3">Date</th>
                                <th scope="col" className="w-36 px-4 py-3">Voucher ref</th>
                                <th scope="col" className="px-4 py-3">Description & narration</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Debit</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Credit</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Running balance</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {computedRows.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-12 text-center text-xs text-slate-500">
                                        No posted journal lines for account {account} in this date range.
                                    </td>
                                </tr>
                            ) : (
                                computedRows.map((row, idx) => (
                                    <tr key={idx} className="h-12 hover:bg-slate-50/70 transition-colors">
                                        <td className="px-4 py-2 text-xs text-slate-600">{formatDate(row.entry_date)}</td>
                                        <td className="px-4 py-2 font-mono text-xs font-medium text-emerald-800">
                                            {row.entry_number}
                                        </td>
                                        <td className="px-4 py-2 font-medium text-slate-900">{row.description}</td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-800">
                                            {row.debit_minor ? money(row.debit_minor, council?.currency) : '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums text-slate-800">
                                            {row.credit_minor ? money(row.credit_minor, council?.currency) : '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums font-semibold text-slate-950">
                                            {reportMoney(row.running_minor, council?.currency, true)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
