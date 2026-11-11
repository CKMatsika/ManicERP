import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import DataTable, { Column } from '../../Components/DataTable';
import { Modal, ConfirmDialog } from '../../Components/FormComponents';
import { formatDateTime, money, type SharedProps } from '../../lib';

type PaymentRow = {
    number: string;
    receipt: string | null;
    account: string | null;
    amount_minor: number;
    method: string;
    received_at: string | null;
};

const PAYMENT_METHODS = [
    { id: 'cash', label: 'Cash', icon: 'Banknote', shortcut: '1' },
    { id: 'mobile_money', label: 'EcoCash / Paynow', icon: 'Coins', shortcut: '2' },
    { id: 'card', label: 'Debit / Credit Card', icon: 'CreditCard', shortcut: '3' },
    { id: 'bank_transfer', label: 'Bank Transfer', icon: 'Landmark', shortcut: '4' },
];

export default function Cashier({
    session,
    payments = [],
    cashTotalMinor = 0,
}: {
    session: { id: string; number: string; opened_at: string | null; opening_float_minor: number } | null;
    payments: PaymentRow[];
    cashTotalMinor: number;
}) {
    const { council, auth, displayTimezone, flash } = usePage<SharedProps>().props;

    const [accountSearch, setAccountSearch] = useState('');
    const [tenderedAmount, setTenderedAmount] = useState('');
    const [showCloseModal, setShowCloseModal] = useState(false);
    const [printedReceipt, setPrintedReceipt] = useState<PaymentRow | null>(null);

    const accountInputRef = useRef<HTMLInputElement>(null);
    const amountInputRef = useRef<HTMLInputElement>(null);

    // Inertia forms
    const openForm = useForm({ opening_float: '0.00' });
    const captureForm = useForm({
        account_number: '',
        amount: '',
        method: 'cash',
        reference: '',
    });
    const closeForm = useForm({ counted_cash: '' });

    // Focus management on mount
    useEffect(() => {
        if (session && accountInputRef.current) {
            accountInputRef.current.focus();
        }
    }, [session]);

    // Handle last receipt trigger from flash success message
    useEffect(() => {
        if (flash?.success && flash.success.includes('Receipt') && payments.length > 0) {
            setPrintedReceipt(payments[0]);
        }
    }, [flash]);

    // Calculate change due
    const numAmount = parseFloat(captureForm.data.amount) || 0;
    const numTendered = parseFloat(tenderedAmount) || 0;
    const changeDue = Math.max(0, numTendered - numAmount);
    const changeDueMinor = Math.round(changeDue * 100);

    // Calculate shift breakdown
    const totalCollectedMinor = payments.reduce((acc, p) => acc + (p.amount_minor || 0), 0);
    const nonCashTotalMinor = totalCollectedMinor - cashTotalMinor;

    function handleAccountSelect(accNo: string) {
        captureForm.setData('account_number', accNo);
        setAccountSearch(accNo);
        if (amountInputRef.current) {
            amountInputRef.current.focus();
        }
    }

    function handleCapture(e: React.FormEvent) {
        e.preventDefault();
        if (!captureForm.data.account_number || !captureForm.data.amount) return;

        captureForm.post('/revenue/cashier/capture', {
            onSuccess: () => {
                captureForm.reset('amount', 'reference');
                setTenderedAmount('');
                if (accountInputRef.current) accountInputRef.current.focus();
            },
        });
    }

    const paymentColumns: Column<PaymentRow>[] = [
        {
            key: 'receipt',
            header: 'Receipt #',
            render: (p) => <span className="font-semibold text-slate-900">{p.receipt ?? p.number}</span>,
        },
        {
            key: 'account',
            header: 'Account',
            render: (p) => <span className="font-medium text-emerald-800">{p.account ?? '—'}</span>,
        },
        {
            key: 'method',
            header: 'Method',
            render: (p) => (
                <span className="capitalize text-slate-700">{p.method.replace('_', ' ')}</span>
            ),
        },
        {
            key: 'received_at',
            header: 'Time',
            render: (p) => <span className="text-slate-500">{formatDateTime(p.received_at, displayTimezone)}</span>,
        },
        {
            key: 'amount_minor',
            header: 'Amount',
            align: 'right',
            render: (p) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(p.amount_minor, council?.currency)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (p) => (
                <button
                    type="button"
                    onClick={() => setPrintedReceipt(p)}
                    className="inline-flex items-center gap-1 text-xs text-emerald-700 hover:underline"
                >
                    <Icon name="Printer" className="h-3.5 w-3.5" />
                    <span>Receipt</span>
                </button>
            ),
        },
    ];

    return (
        <AppLayout title="Cashier Point of Sale">
            <Head title="Cashier point of sale" />

            <PageHeader
                title="Cashier desk & receipting"
                description={`Teller workstation for ${auth?.name ?? 'Revenue Clerk'} · Council Point of Sale`}
                breadcrumbs={[{ label: 'Revenue & billing', href: '/revenue/cashier' }, { label: 'Cashier' }]}
                badge={
                    session ? (
                        <StatusBadge status="active" label={`Shift: ${session.number}`} />
                    ) : (
                        <StatusBadge status="draft" label="Session closed" />
                    )
                }
                primaryAction={
                    session
                        ? {
                              label: 'Close shift & reconcile',
                              icon: 'Check',
                              onClick: () => setShowCloseModal(true),
                          }
                        : undefined
                }
            />

            {/* Session not open banner */}
            {!session && (
                <div className="mb-6 rounded-xl border border-slate-200 bg-white p-6 max-w-md mx-auto text-center shadow-sm">
                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                        <Icon name="Landmark" className="h-6 w-6" />
                    </div>
                    <h2 className="mt-3 text-lg font-semibold text-slate-900">Open cashier session</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Declare your opening cash float to begin receipting customer payments.
                    </p>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            openForm.post('/revenue/cashier/open');
                        }}
                        className="mt-5 space-y-4 text-left"
                    >
                        <div>
                            <label htmlFor="opening_float" className="block text-xs font-medium text-slate-700">
                                Opening cash drawer float ({council?.currency ?? 'USD'})
                            </label>
                            <input
                                type="text"
                                id="opening_float"
                                inputMode="decimal"
                                value={openForm.data.opening_float}
                                onChange={(e) => openForm.setData('opening_float', e.target.value)}
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={openForm.processing}
                            className="w-full rounded-lg bg-emerald-700 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                        >
                            {openForm.processing ? 'Opening shift...' : 'Open session & start shift'}
                        </button>
                    </form>
                </div>
            )}

            {session && (
                <>
                    {/* Shift Stats Bar */}
                    <div className="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
                        <div>
                            <span className="text-xs font-medium text-slate-500">Opening float</span>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-slate-800">
                                {money(session.opening_float_minor, council?.currency)}
                            </p>
                            <span className="text-xs text-slate-400">Cash drawer starting balance</span>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-500">Cash collected</span>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-emerald-700">
                                {money(cashTotalMinor, council?.currency)}
                            </p>
                            <span className="text-xs text-slate-400">In drawer today</span>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-500">Digital / card payments</span>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-slate-800">
                                {money(nonCashTotalMinor, council?.currency)}
                            </p>
                            <span className="text-xs text-slate-400">POS & bank receipts</span>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-500">Shift receipts count</span>
                            <p className="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                                {payments.length}
                            </p>
                            <span className="text-xs text-slate-400">Transactions processed</span>
                        </div>
                    </div>

                    {/* High-Speed 2-Panel Cashier Layout */}
                    <div className="grid gap-6 lg:grid-cols-12">
                        {/* Left Panel: Account Search & Recents */}
                        <div className="lg:col-span-5 space-y-5">
                            <div className="rounded-xl border border-slate-200 bg-white p-5">
                                <h3 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">
                                    Customer Account Lookup
                                </h3>

                                <div className="mt-4">
                                    <label htmlFor="account_search" className="block text-xs font-medium text-slate-700">
                                        Scan / enter account or stand number
                                    </label>
                                    <div className="relative mt-1">
                                        <Icon name="Search" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            ref={accountInputRef}
                                            type="text"
                                            id="account_search"
                                            value={accountSearch}
                                            onChange={(e) => {
                                                setAccountSearch(e.target.value);
                                                captureForm.setData('account_number', e.target.value);
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    if (amountInputRef.current) amountInputRef.current.focus();
                                                }
                                            }}
                                            placeholder="ACC-0000001, Stand 1044..."
                                            className="h-11 w-full rounded-lg border border-slate-300 pl-9 pr-3 text-sm font-medium text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                        />
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-400">Press Enter after scanning barcode to focus amount.</p>
                                </div>

                                {/* Quick Select Recent Accounts from this session */}
                                <div className="mt-5 border-t border-slate-100 pt-4">
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        Recent shift accounts
                                    </h4>
                                    <div className="mt-2 space-y-1.5 max-h-48 overflow-y-auto">
                                        {Array.from(new Set(payments.map((p) => p.account).filter(Boolean))).map((acc) => (
                                            <button
                                                key={acc}
                                                type="button"
                                                onClick={() => handleAccountSelect(acc!)}
                                                className="flex w-full items-center justify-between rounded-lg p-2 text-left text-xs hover:bg-slate-50 transition-colors"
                                            >
                                                <span className="font-mono font-medium text-emerald-800">{acc}</span>
                                                <span className="text-[11px] text-slate-400">Select</span>
                                            </button>
                                        ))}
                                        {payments.length === 0 && (
                                            <p className="text-xs text-slate-400 py-2">No previous accounts in this shift.</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Right Panel: Payment Capture & Tender */}
                        <div className="lg:col-span-7">
                            <form onSubmit={handleCapture} className="rounded-xl border border-slate-200 bg-white p-5 space-y-5">
                                <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <h3 className="text-base font-semibold text-slate-900">
                                        Payment Tender & Receipt
                                    </h3>
                                    <span className="text-xs text-slate-500 font-medium">
                                        Account: <strong className="text-emerald-800 font-mono">{captureForm.data.account_number || 'None selected'}</strong>
                                    </span>
                                </div>

                                {/* Tender Method Segmented Buttons */}
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-2">
                                        Payment method
                                    </label>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {PAYMENT_METHODS.map((method) => {
                                            const isSelected = captureForm.data.method === method.id;
                                            return (
                                                <button
                                                    key={method.id}
                                                    type="button"
                                                    onClick={() => captureForm.setData('method', method.id)}
                                                    className={`flex flex-col items-center justify-center rounded-xl border p-3 transition-all ${
                                                        isSelected
                                                            ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold ring-2 ring-emerald-600/20'
                                                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                                    }`}
                                                >
                                                    <Icon
                                                        name={method.icon}
                                                        className={`h-5 w-5 mb-1 ${isSelected ? 'text-emerald-700' : 'text-slate-400'}`}
                                                    />
                                                    <span className="text-xs">{method.label}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Amount Inputs Row */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="bill_amount" className="block text-xs font-medium text-slate-700">
                                            Bill amount to pay ({council?.currency ?? 'USD'}) <span className="text-rose-600">*</span>
                                        </label>
                                        <input
                                            ref={amountInputRef}
                                            type="text"
                                            id="bill_amount"
                                            inputMode="decimal"
                                            value={captureForm.data.amount}
                                            onChange={(e) => captureForm.setData('amount', e.target.value)}
                                            required
                                            placeholder="0.00"
                                            className="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-right text-base font-semibold tabular-nums text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                        />
                                        {captureForm.errors.amount && (
                                            <p className="mt-1 text-xs text-rose-600">{captureForm.errors.amount}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="tendered" className="block text-xs font-medium text-slate-700">
                                            Amount tendered by customer ({council?.currency ?? 'USD'})
                                        </label>
                                        <input
                                            type="text"
                                            id="tendered"
                                            inputMode="decimal"
                                            value={tenderedAmount}
                                            onChange={(e) => setTenderedAmount(e.target.value)}
                                            placeholder={captureForm.data.amount || '0.00'}
                                            className="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-right text-base font-semibold tabular-nums text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                        />
                                    </div>
                                </div>

                                {/* External Reference for EcoCash / Cards */}
                                {captureForm.data.method !== 'cash' && (
                                    <div>
                                        <label htmlFor="ref" className="block text-xs font-medium text-slate-700">
                                            Transaction reference / Authorization code
                                        </label>
                                        <input
                                            type="text"
                                            id="ref"
                                            value={captureForm.data.reference}
                                            onChange={(e) => captureForm.setData('reference', e.target.value)}
                                            placeholder="Approval code, EcoCash transaction ID, or bank reference"
                                            className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                        />
                                    </div>
                                )}

                                {/* Change Due Highlight Box */}
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 flex items-center justify-between">
                                    <div>
                                        <span className="text-xs font-medium text-slate-500">Change due to customer</span>
                                        <p className="text-xs text-slate-400">Calculated from tendered cash</p>
                                    </div>
                                    <div className="text-right">
                                        <span className="text-3xl font-semibold tabular-nums text-slate-900">
                                            {money(changeDueMinor, council?.currency)}
                                        </span>
                                    </div>
                                </div>

                                {/* Large Primary Action Button */}
                                <div>
                                    <button
                                        type="submit"
                                        disabled={captureForm.processing || !captureForm.data.account_number || numAmount <= 0}
                                        className="flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 text-base font-semibold text-white shadow-sm transition-all hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 disabled:opacity-50"
                                    >
                                        {captureForm.processing ? (
                                            <span>Recording payment...</span>
                                        ) : (
                                            <>
                                                <Icon name="Check" className="h-5 w-5" />
                                                <span>
                                                    Receive {money(Math.round(numAmount * 100), council?.currency)} & Issue Receipt ↵
                                                </span>
                                            </>
                                        )}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Shift Payments History Table */}
                    <div className="mt-8">
                        <h3 className="mb-3 text-base font-semibold text-slate-900">
                            Payments issued in this cashier shift
                        </h3>
                        <DataTable
                            columns={paymentColumns}
                            data={payments}
                            keyField="number"
                            emptyTitle="No payments taken yet"
                            emptyHint="Receipts captured in this shift will appear here with instant reprint capabilities."
                        />
                    </div>
                </>
            )}

            {/* Shift Close Reconciliation Modal */}
            {showCloseModal && session && (
                <Modal
                    isOpen={showCloseModal}
                    onClose={() => setShowCloseModal(false)}
                    title="Close Cashier Shift & Reconcile"
                    description={`Session ${session.number} · Opened ${formatDateTime(session.opened_at, displayTimezone)}`}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            closeForm.post('/revenue/cashier/close', {
                                onSuccess: () => setShowCloseModal(false),
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="rounded-lg bg-slate-50 p-3 text-xs space-y-1.5 text-slate-600">
                            <div className="flex justify-between">
                                <span>Opening float:</span>
                                <strong className="tabular-nums">{money(session.opening_float_minor, council?.currency)}</strong>
                            </div>
                            <div className="flex justify-between">
                                <span>Total cash received:</span>
                                <strong className="tabular-nums">{money(cashTotalMinor, council?.currency)}</strong>
                            </div>
                            <div className="flex justify-between border-t border-slate-200 pt-1 text-slate-900 font-semibold">
                                <span>Expected cash in drawer:</span>
                                <strong className="tabular-nums">
                                    {money(session.opening_float_minor + cashTotalMinor, council?.currency)}
                                </strong>
                            </div>
                        </div>

                        <div>
                            <label htmlFor="counted_cash" className="block text-sm font-medium text-slate-700">
                                Physically counted cash in drawer ({council?.currency ?? 'USD'}) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                id="counted_cash"
                                inputMode="decimal"
                                value={closeForm.data.counted_cash}
                                onChange={(e) => closeForm.setData('counted_cash', e.target.value)}
                                required
                                placeholder="0.00"
                                className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            />
                            {closeForm.errors.counted_cash && (
                                <p className="mt-1 text-xs text-rose-600">{closeForm.errors.counted_cash}</p>
                            )}
                        </div>

                        <div className="flex justify-end gap-2.5 pt-3">
                            <button
                                type="button"
                                onClick={() => setShowCloseModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={closeForm.processing}
                                className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50"
                            >
                                {closeForm.processing ? 'Closing session...' : 'Reconcile and close shift'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Official Printable Receipt Modal (A4 & 80mm Thermal compatible) */}
            {printedReceipt && (
                <Modal
                    isOpen={!!printedReceipt}
                    onClose={() => setPrintedReceipt(null)}
                    title="Municipal Payment Receipt"
                    maxWidth="max-w-md"
                >
                    <div className="receipt-80mm text-slate-900 font-sans text-xs">
                        <div className="text-center border-b border-slate-300 pb-3">
                            <h3 className="font-bold text-sm uppercase tracking-wider">{council?.name ?? 'City of Harare'}</h3>
                            <p className="text-[11px] text-slate-600">Official Municipal Revenue Receipt</p>
                            <p className="mt-1 font-mono font-semibold">{printedReceipt.receipt ?? printedReceipt.number}</p>
                        </div>

                        <div className="py-3 space-y-1 border-b border-slate-200">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Date/Time:</span>
                                <span>{formatDateTime(printedReceipt.received_at, displayTimezone)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Cashier:</span>
                                <span>{auth?.name ?? 'Desk'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Account:</span>
                                <span className="font-semibold">{printedReceipt.account}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Payment method:</span>
                                <span className="capitalize">{printedReceipt.method.replace('_', ' ')}</span>
                            </div>
                        </div>

                        <div className="py-3 border-b border-slate-200">
                            <div className="flex justify-between font-bold text-sm">
                                <span>Amount paid:</span>
                                <span className="tabular-nums">{money(printedReceipt.amount_minor, council?.currency)}</span>
                            </div>
                        </div>

                        <div className="pt-3 text-center text-[10px] text-slate-500">
                            <p>Thank you for your municipal service payment.</p>
                            <p>Retain this receipt for audit and proof of rates payment.</p>
                        </div>
                    </div>

                    <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-3">
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            <Icon name="Printer" className="h-4 w-4" />
                            <span>Print receipt (A4 / 80mm)</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setPrintedReceipt(null)}
                            className="rounded-lg border border-slate-300 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Close
                        </button>
                    </div>
                </Modal>
            )}
        </AppLayout>
    );
}
