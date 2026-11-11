import React, { useState, useMemo } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatStrip, { StatItem } from '../../Components/StatStrip';
import FilterBar from '../../Components/FilterBar';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import { Combobox, ConfirmDialog, Modal } from '../../Components/FormComponents';
import { formatDate, money, type SharedProps } from '../../lib';

type Entry = {
    id: string;
    entry_number: string;
    entry_date: string | null;
    description: string;
    journal_type: string;
    status: string;
    total_debit_minor: number;
    currency: string;
    period: string | null;
    reversed: boolean;
};

type Account = { code: string; name: string; class: string };

type JournalLine = {
    account_code: string;
    debit: string;
    credit: string;
    memo: string;
};

export default function Journals({
    entries,
    accounts,
}: {
    entries: {
        data: Entry[];
        total?: number;
        from?: number;
        to?: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    accounts: Account[];
    dimensions: any[];
    periods: any[];
}) {
    const { council } = usePage<SharedProps>().props;
    const [viewMode, setViewMode] = useState<'list' | 'create'>('list');
    const [reversingEntry, setReversingEntry] = useState<Entry | null>(null);
    const [viewingEntry, setViewingEntry] = useState<Entry | null>(null);

    // Reversal form
    const reverseForm = useForm({
        reason: '',
        reversal_date: new Date().toISOString().slice(0, 10),
    });

    // New Journal form
    const [lines, setLines] = useState<JournalLine[]>([
        { account_code: '', debit: '', credit: '', memo: '' },
        { account_code: '', debit: '', credit: '', memo: '' },
    ]);

    const form = useForm({
        entry_date: new Date().toISOString().slice(0, 10),
        description: '',
        lines: lines,
    });

    // Compute live totals
    const { totalDebits, totalCredits, difference, isBalanced, hasPositiveAmount } = useMemo(() => {
        let debits = 0;
        let credits = 0;
        lines.forEach((l) => {
            const d = parseFloat(l.debit) || 0;
            const c = parseFloat(l.credit) || 0;
            debits += d;
            credits += c;
        });

        // Convert to cents to avoid floating point issues
        const debitsMinor = Math.round(debits * 100);
        const creditsMinor = Math.round(credits * 100);
        const diffMinor = Math.abs(debitsMinor - creditsMinor);

        return {
            totalDebits: debitsMinor,
            totalCredits: creditsMinor,
            difference: diffMinor,
            isBalanced: debitsMinor === creditsMinor && debitsMinor > 0,
            hasPositiveAmount: debitsMinor > 0,
        };
    }, [lines]);

    function updateLine(index: number, key: keyof JournalLine, value: string) {
        const next = lines.map((line, i) => {
            if (i !== index) return line;
            if (key === 'debit' && value !== '') {
                return { ...line, debit: value, credit: '' };
            }
            if (key === 'credit' && value !== '') {
                return { ...line, credit: value, debit: '' };
            }
            return { ...line, [key]: value };
        });
        setLines(next);
        form.setData('lines', next);
    }

    function addLine() {
        const next = [...lines, { account_code: '', debit: '', credit: '', memo: '' }];
        setLines(next);
        form.setData('lines', next);
    }

    function removeLine(index: number) {
        if (lines.length <= 2) return;
        const next = lines.filter((_, i) => i !== index);
        setLines(next);
        form.setData('lines', next);
    }

    function handlePost(e: React.FormEvent) {
        e.preventDefault();
        if (!isBalanced) return;

        form.post('/accounting/journals', {
            onSuccess: () => {
                setLines([
                    { account_code: '', debit: '', credit: '', memo: '' },
                    { account_code: '', debit: '', credit: '', memo: '' },
                ]);
                form.reset('description');
                setViewMode('list');
            },
        });
    }

    function handleReverseConfirm() {
        if (!reversingEntry) return;
        reverseForm.post(`/accounting/journals/${reversingEntry.id}/reverse`, {
            onSuccess: () => setReversingEntry(null),
        });
    }

    // Stats
    const postedCount = entries.data.filter((e) => e.status === 'posted').length;
    const reversedCount = entries.data.filter((e) => e.reversed).length;
    const totalVolumeMinor = entries.data.reduce((acc, e) => acc + (e.total_debit_minor || 0), 0);

    const stats: StatItem[] = [
        {
            label: 'Total entries',
            value: entries.total ?? entries.data.length,
            icon: 'BookOpen',
            helper: 'General ledger vouchers',
        },
        {
            label: 'Posted & verified',
            value: postedCount,
            icon: 'CheckCircle2',
            tone: 'emerald',
        },
        {
            label: 'Reversed vouchers',
            value: reversedCount,
            icon: 'RotateCcw',
            tone: reversedCount > 0 ? 'slate' : 'default',
        },
        {
            label: 'Turnover sample',
            value: money(totalVolumeMinor, council?.currency),
            icon: 'Scale',
        },
    ];

    const columns: Column<Entry>[] = [
        {
            key: 'entry_number',
            header: 'Voucher number',
            sortable: true,
            render: (entry) => (
                <button
                    type="button"
                    onClick={() => setViewingEntry(entry)}
                    className="font-medium text-emerald-800 hover:text-emerald-950 hover:underline"
                >
                    {entry.entry_number}
                </button>
            ),
        },
        {
            key: 'entry_date',
            header: 'Date',
            sortable: true,
            render: (entry) => <span className="text-slate-600">{formatDate(entry.entry_date)}</span>,
        },
        {
            key: 'description',
            header: 'Narration & Description',
            render: (entry) => (
                <div>
                    <p className="font-medium text-slate-900">{entry.description}</p>
                    <p className="text-xs text-slate-500">
                        {entry.journal_type} {entry.period ? `· Period ${entry.period}` : ''}
                    </p>
                </div>
            ),
        },
        {
            key: 'total_debit_minor',
            header: 'Voucher amount',
            align: 'right',
            sortable: true,
            render: (entry) => (
                <span className="font-medium tabular-nums text-slate-900">
                    {money(entry.total_debit_minor, entry.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (entry) => (
                <div className="flex flex-col gap-1 items-start">
                    <StatusBadge status={entry.reversed ? 'reversed' : entry.status} />
                </div>
            ),
        },
    ];

    const rowActions: RowAction<Entry>[] = [
        {
            label: 'View audit details',
            icon: 'Eye',
            onClick: (entry) => setViewingEntry(entry),
        },
        {
            label: 'Reverse voucher',
            icon: 'RotateCcw',
            tone: 'danger',
            hidden: (entry) => entry.status !== 'posted' || entry.reversed || entry.journal_type === 'reversal',
            onClick: (entry) => setReversingEntry(entry),
        },
    ];

    const accountComboboxItems = accounts.map((acc) => ({
        code: acc.code,
        name: acc.name,
        subtitle: `Class: ${acc.class.replace('_', ' ')}`,
    }));

    return (
        <AppLayout title="Journal vouchers">
            <Head title="Journal vouchers" />

            <PageHeader
                title="Journal vouchers"
                description="Double-entry manual general ledger journals, period adjustments, and linked reversals."
                breadcrumbs={[{ label: 'Finance & accounting', href: '/accounting/journals' }, { label: 'Journals' }]}
                primaryAction={
                    viewMode === 'list'
                        ? {
                              label: 'New journal voucher',
                              icon: 'Plus',
                              onClick: () => setViewMode('create'),
                          }
                        : {
                              label: 'Back to voucher list',
                              icon: 'BookOpen',
                              onClick: () => setViewMode('list'),
                          }
                }
            />

            {viewMode === 'list' && (
                <>
                    <StatStrip stats={stats} />

                    <FilterBar
                        searchPlaceholder="Search journal vouchers, narrations, entry numbers..."
                        filters={[
                            {
                                key: 'status',
                                label: 'Status',
                                options: [
                                    { label: 'Posted', value: 'posted' },
                                    { label: 'Draft', value: 'draft' },
                                    { label: 'Reversed', value: 'reversed' },
                                ],
                            },
                        ]}
                        exportOptions={[
                            {
                                label: 'Export Journal List',
                                onClick: () => window.print(),
                            },
                        ]}
                    />

                    <DataTable
                        columns={columns}
                        data={entries.data}
                        keyField="id"
                        rowActions={rowActions}
                        onRowClick={(row) => setViewingEntry(row)}
                        emptyTitle="No journal vouchers found"
                        emptyHint="No journal vouchers recorded yet. Create your first double-entry voucher."
                        emptyAction={{
                            label: 'Create first journal',
                            onClick: () => setViewMode('create'),
                        }}
                        pagination={{
                            total: entries.total,
                            from: entries.from,
                            to: entries.to,
                            links: entries.links,
                            onPageChange: (url) => router.get(url, {}, { preserveState: true }),
                        }}
                    />
                </>
            )}

            {/* New Journal Voucher Creation Flow */}
            {viewMode === 'create' && (
                <div className="max-w-4xl space-y-6">
                    <form onSubmit={handlePost} className="space-y-6">
                        {/* Header Details Card */}
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">
                                Voucher Header & Accounting Period
                            </h3>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="entry_date" className="block text-sm font-medium text-slate-700">
                                        Posting date <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        id="entry_date"
                                        value={form.data.entry_date}
                                        onChange={(e) => form.setData('entry_date', e.target.value)}
                                        required
                                        className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="currency" className="block text-sm font-medium text-slate-700">
                                        Currency ledger
                                    </label>
                                    <input
                                        type="text"
                                        id="currency"
                                        disabled
                                        value={`${council?.currency ?? 'USD'} (Municipal base currency)`}
                                        className="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-500"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <label htmlFor="description" className="block text-sm font-medium text-slate-700">
                                        Narration & Description <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        required
                                        placeholder="e.g. Month-end depreciation adjustment for vehicle plant"
                                        className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                    />
                                    {form.errors.description && (
                                        <p className="mt-1 text-xs text-rose-600">{form.errors.description}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Line Items Table */}
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 className="text-base font-semibold text-slate-900">General Ledger Lines</h3>
                                    <p className="text-xs text-slate-500">Each line must assign an active Chart of Accounts code and debit or credit.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={addLine}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    <Icon name="Plus" className="h-3.5 w-3.5" />
                                    <span>Add row</span>
                                </button>
                            </div>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full text-left text-sm text-slate-700">
                                    <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2 w-10 text-center">#</th>
                                            <th className="px-3 py-2 min-w-[260px]">Account</th>
                                            <th className="px-3 py-2 min-w-[180px]">Memo</th>
                                            <th className="px-3 py-2 w-36 text-right">Debit</th>
                                            <th className="px-3 py-2 w-36 text-right">Credit</th>
                                            <th className="px-3 py-2 w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {lines.map((line, idx) => (
                                            <tr key={idx} className="h-12 hover:bg-slate-50/50">
                                                <td className="px-3 py-2 text-center text-xs text-slate-400">
                                                    {idx + 1}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Combobox
                                                        items={accountComboboxItems}
                                                        value={line.account_code}
                                                        onChange={(val) => updateLine(idx, 'account_code', val)}
                                                        placeholder="Select GL account..."
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="text"
                                                        value={line.memo}
                                                        onChange={(e) => updateLine(idx, 'memo', e.target.value)}
                                                        placeholder="Optional line memo"
                                                        className="h-9 w-full rounded-md border border-slate-300 px-2.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <input
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={line.debit}
                                                        onChange={(e) => updateLine(idx, 'debit', e.target.value)}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                e.preventDefault();
                                                                addLine();
                                                            }
                                                        }}
                                                        placeholder="0.00"
                                                        className="h-9 w-full rounded-md border border-slate-300 px-2.5 text-right text-xs tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <input
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={line.credit}
                                                        onChange={(e) => updateLine(idx, 'credit', e.target.value)}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                e.preventDefault();
                                                                addLine();
                                                            }
                                                        }}
                                                        placeholder="0.00"
                                                        className="h-9 w-full rounded-md border border-slate-300 px-2.5 text-right text-xs tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    {lines.length > 2 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeLine(idx)}
                                                            className="text-slate-400 hover:text-rose-600"
                                                            title="Remove line"
                                                        >
                                                            <Icon name="Trash2" className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {form.errors.lines && (
                                <p className="mt-3 text-xs text-rose-600">{form.errors.lines}</p>
                            )}
                        </div>

                        {/* Live Balance Summary Bar */}
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-6 text-sm">
                                    <div>
                                        <span className="text-xs text-slate-500">Total Debits</span>
                                        <p className="font-semibold tabular-nums text-slate-900">
                                            {money(totalDebits, council?.currency)}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-slate-500">Total Credits</span>
                                        <p className="font-semibold tabular-nums text-slate-900">
                                            {money(totalCredits, council?.currency)}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-slate-500">Difference</span>
                                        <p className={`font-semibold tabular-nums ${difference === 0 ? 'text-slate-700' : 'text-amber-700'}`}>
                                            {money(difference, council?.currency)}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    {isBalanced ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                            <Icon name="Check" className="h-3.5 w-3.5 text-emerald-600" />
                                            <span>Balanced</span>
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 border border-amber-200">
                                            <Icon name="AlertCircle" className="h-3.5 w-3.5 text-amber-600" />
                                            <span>
                                                {hasPositiveAmount
                                                    ? `Out of balance by ${money(difference, council?.currency)}`
                                                    : 'Enter debit and credit amounts'}
                                            </span>
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Sticky Action Footer */}
                        <div className="sticky bottom-4 z-20 flex items-center justify-between rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                            <button
                                type="button"
                                onClick={() => setViewMode('list')}
                                className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <div className="flex items-center gap-2.5">
                                <button
                                    type="button"
                                    onClick={() => window.alert('Draft voucher saved in session.')}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Save draft
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing || !isBalanced}
                                    title={!isBalanced ? 'Debits and credits must balance before posting.' : undefined}
                                    className="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50"
                                >
                                    {form.processing ? (
                                        <span>Posting...</span>
                                    ) : (
                                        <>
                                            <Icon name="Check" className="h-4 w-4" />
                                            <span>Post journal voucher</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            )}

            {/* Reversal Confirmation Dialog */}
            {reversingEntry && (
                <ConfirmDialog
                    isOpen={!!reversingEntry}
                    onClose={() => setReversingEntry(null)}
                    onConfirm={handleReverseConfirm}
                    title="Reverse Journal Voucher"
                    itemName={reversingEntry.entry_number}
                    consequence="Posting a reversal creates an equal and opposite linked journal voucher to correct this transaction in the general ledger. The original entry is retained in the permanent audit trail."
                    confirmLabel="Post reversal voucher"
                    tone="danger"
                    isProcessing={reverseForm.processing}
                />
            )}

            {/* Voucher Details Modal */}
            {viewingEntry && (
                <Modal
                    isOpen={!!viewingEntry}
                    onClose={() => setViewingEntry(null)}
                    title={`Voucher ${viewingEntry.entry_number}`}
                    description={`Recorded ${formatDate(viewingEntry.entry_date)} · Period: ${viewingEntry.period ?? 'Default'}`}
                >
                    <div className="space-y-4">
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <span className="text-xs text-slate-500">Narration</span>
                            <p className="mt-1 text-sm font-medium text-slate-900">{viewingEntry.description}</p>
                        </div>
                        <div className="flex justify-between items-center text-sm border-t border-slate-100 pt-3">
                            <span className="text-slate-600">Total amount</span>
                            <span className="font-semibold tabular-nums text-slate-900">
                                {money(viewingEntry.total_debit_minor, viewingEntry.currency)}
                            </span>
                        </div>
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-slate-600">Status</span>
                            <StatusBadge status={viewingEntry.reversed ? 'reversed' : viewingEntry.status} />
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setViewingEntry(null)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </AppLayout>
    );
}
