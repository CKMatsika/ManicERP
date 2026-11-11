import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import Icon from '../../Components/Icon';
import { formatDate, formatDateTime, money, reportMoney, type SharedProps } from '../../lib';

type Row = {
    code: string;
    name: string;
    class: string;
    normal_balance: string;
    debit_minor: number;
    credit_minor: number;
    balance_minor: number;
};

export default function TrialBalance({
    report,
    asOf,
    position,
}: {
    report: {
        rows: Row[];
        totals: { debit_minor: number; credit_minor: number; balanced: boolean };
        as_of: string;
    };
    asOf: string;
    position: { assets_minor: number; liabilities_minor: number; result_minor: number; check: number };
}) {
    const { council, displayTimezone, auth } = usePage<SharedProps>().props;
    const { data, setData, get } = useForm({ as_of: asOf });
    const [showComparative, setShowComparative] = useState(false);

    // Group rows by class for hierarchy
    const groupedRows = report.rows.reduce((acc, row) => {
        const cls = row.class || 'general';
        if (!acc[cls]) acc[cls] = [];
        acc[cls].push(row);
        return acc;
    }, {} as Record<string, Row[]>);

    const classOrder = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    function handleRun(e: React.FormEvent) {
        e.preventDefault();
        get('/accounting/reports/trial-balance', { preserveState: true });
    }

    return (
        <AppLayout title="Trial balance report">
            <Head title="Trial balance report" />

            {/* Print Header (Visible only when printing) */}
            <div className="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-xl font-bold uppercase tracking-wider">{council?.name ?? 'City of Harare'}</h1>
                        <h2 className="text-base font-semibold">General Ledger Trial Balance</h2>
                        <p className="text-xs text-slate-600">As at {formatDate(asOf)} · Municipal Financial Accounting</p>
                    </div>
                    <div className="text-right text-xs text-slate-500">
                        <p>Generated: {formatDateTime(new Date().toISOString(), displayTimezone)}</p>
                        <p>User: {auth?.name ?? 'System'}</p>
                    </div>
                </div>
            </div>

            <div className="print:hidden">
                <PageHeader
                    title="Trial balance"
                    description={`Statement of debit and credit balances as at ${formatDate(asOf)}.`}
                    breadcrumbs={[
                        { label: 'Finance & accounting', href: '/accounting/journals' },
                        { label: 'Reports', href: '/accounting/reports/trial-balance' },
                        { label: 'Trial balance' },
                    ]}
                    secondaryActions={[
                        {
                            label: 'Print report',
                            icon: 'Printer',
                            onClick: () => window.print(),
                        },
                        {
                            label: 'Export CSV',
                            icon: 'Download',
                            onClick: () => window.print(),
                        },
                    ]}
                />

                {/* Report Toolbar */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4">
                    <form onSubmit={handleRun} className="flex flex-wrap items-center gap-3">
                        <div>
                            <label htmlFor="as_of" className="sr-only">
                                Reporting date
                            </label>
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-medium text-slate-600">As at:</span>
                                <input
                                    id="as_of"
                                    type="date"
                                    value={data.as_of}
                                    onChange={(e) => setData('as_of', e.target.value)}
                                    className="h-9 rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <span>Run statement</span>
                        </button>
                    </form>

                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={showComparative}
                                onChange={(e) => setShowComparative(e.target.checked)}
                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span>Compare with prior period</span>
                        </label>

                        <div className="flex items-center gap-2 border-l border-slate-200 pl-4">
                            <span
                                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${
                                    report.totals.balanced
                                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                                }`}
                            >
                                <Icon
                                    name={report.totals.balanced ? 'Check' : 'AlertCircle'}
                                    className="h-3.5 w-3.5"
                                />
                                <span>{report.totals.balanced ? 'Debits equal credits' : 'Out of balance'}</span>
                            </span>
                        </div>
                    </div>
                </div>

                {/* Balance Sheet Equation Strip */}
                <div className="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
                    <div>
                        <span className="text-xs font-medium text-slate-500">Total assets</span>
                        <p className="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                            {money(position.assets_minor, council?.currency)}
                        </p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-slate-500">Total liabilities</span>
                        <p className="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                            {money(position.liabilities_minor, council?.currency)}
                        </p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-slate-500">Operating result / surplus</span>
                        <p className={`mt-1 text-xl font-semibold tabular-nums ${position.result_minor >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                            {reportMoney(position.result_minor, council?.currency, true)}
                        </p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-slate-500">Accounting check (A - L - E)</span>
                        <p className="mt-1 text-xl font-semibold tabular-nums text-slate-700">
                            {money(position.check, council?.currency)}
                        </p>
                    </div>
                </div>
            </div>

            {/* Structured Financial Report Table */}
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm text-slate-800">
                        <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" className="w-28 px-4 py-3">Account code</th>
                                <th scope="col" className="px-4 py-3">Account name & classification</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Debit balance</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Credit balance</th>
                                <th scope="col" className="w-36 px-4 py-3 text-right">Net balance</th>
                                <th scope="col" className="w-20 px-4 py-3 text-right print:hidden"></th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {classOrder.map((clsKey) => {
                                const classRows = groupedRows[clsKey];
                                if (!classRows || classRows.length === 0) return null;

                                const subDebit = classRows.reduce((acc, r) => acc + (r.debit_minor || 0), 0);
                                const subCredit = classRows.reduce((acc, r) => acc + (r.credit_minor || 0), 0);
                                const subBalance = classRows.reduce((acc, r) => acc + (r.balance_minor || 0), 0);

                                return (
                                    <React.Fragment key={clsKey}>
                                        {/* Class Hierarchy Section Header */}
                                        <tr className="bg-slate-50/70">
                                            <td colSpan={6} className="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-700">
                                                {clsKey.replace('_', ' ')} accounts
                                            </td>
                                        </tr>

                                        {/* Individual Account Rows with Indentation */}
                                        {classRows.map((row) => (
                                            <tr key={row.code} className="h-10 hover:bg-slate-50/80 transition-colors">
                                                <td className="px-4 py-2 font-mono text-xs font-medium text-emerald-800">
                                                    <Link
                                                        href={`/accounting/reports/ledger?account=${encodeURIComponent(row.code)}`}
                                                        className="hover:underline"
                                                    >
                                                        {row.code}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-2 pl-6">
                                                    <span className="font-medium text-slate-900">{row.name}</span>
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums text-slate-800">
                                                    {row.debit_minor ? money(row.debit_minor, council?.currency) : '—'}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums text-slate-800">
                                                    {row.credit_minor ? money(row.credit_minor, council?.currency) : '—'}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums font-medium text-slate-900">
                                                    {reportMoney(row.balance_minor, council?.currency, true)}
                                                </td>
                                                <td className="px-4 py-2 text-right print:hidden">
                                                    <Link
                                                        href={`/accounting/reports/ledger?account=${encodeURIComponent(row.code)}`}
                                                        className="text-xs text-emerald-700 hover:underline"
                                                    >
                                                        Ledger
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}

                                        {/* Class Subtotal Row */}
                                        <tr className="border-t border-slate-300 bg-slate-50/40 font-semibold text-xs text-slate-900">
                                            <td className="px-4 py-2"></td>
                                            <td className="px-4 py-2 uppercase">Subtotal: {clsKey}</td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {money(subDebit, council?.currency)}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {money(subCredit, council?.currency)}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {reportMoney(subBalance, council?.currency, true)}
                                            </td>
                                            <td className="print:hidden"></td>
                                        </tr>
                                    </React.Fragment>
                                );
                            })}

                            {/* Grand Totals with Double Rule */}
                            <tr className="border-t-2 border-slate-400 border-b-4 border-double border-slate-900 bg-slate-100 font-bold text-sm text-slate-950">
                                <td className="px-4 py-3 uppercase">Totals</td>
                                <td className="px-4 py-3">Total debits and credits</td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {money(report.totals.debit_minor, council?.currency)}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {money(report.totals.credit_minor, council?.currency)}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {money(report.totals.debit_minor - report.totals.credit_minor, council?.currency)}
                                </td>
                                <td className="print:hidden"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
