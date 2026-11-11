import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

type AgingSummary = {
    currency: string;
    current_usd: string;
    thirty_days_usd: string;
    sixty_days_usd: string;
    ninety_plus_usd: string;
    total_arrears_usd: string;
    total_debtor_bills: number;
    active_plans_count: number;
    protected_accounts_count: number;
};

type PaymentPlanItem = {
    id: string;
    plan_number: string;
    account_number: string;
    total_arrears_usd: string;
    interest_waived_usd: string;
    net_payable_usd: string;
    monthly_installment_usd: string;
    installment_count: number;
    paid_installments: number;
    total_paid_usd: string;
    disconnection_immunity: boolean;
    resolution_ref: string;
    start_date: string;
    end_date: string;
    status: string;
    next_installment?: {
        installment_number: number;
        due_date: string;
        amount_usd: string;
        status: string;
    } | null;
};

type DebtorAccount = {
    id: string;
    account_number: string;
    property?: string;
    address?: string;
    outstanding_usd: string;
    currency: string;
    has_active_plan: boolean;
};

type Props = {
    aging: AgingSummary;
    plans: PaymentPlanItem[];
    debtors: DebtorAccount[];
};

export default function CreditControl({ aging, plans, debtors }: Props) {
    const [tab, setTab] = useState<'plans' | 'aging' | 'debtors'>('plans');

    return (
        <AppLayout title="Credit Control & Arrears">
            <Head title="Credit Control & Arrears" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-teal-700">
                            Revenue & Credit Control Division
                        </p>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Credit Control & Debt Recovery
                        </h1>
                        <p className="text-sm text-slate-600">
                            Enforcement under Section 279 of the Urban Councils Act [Chapter 29:15] &amp; Resolution FC/89/2026 Interest Waiver.
                        </p>
                    </div>

                    <div className="flex gap-2 text-xs">
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs">
                            <span className="text-slate-500">Immunity Protected:</span>{' '}
                            <span className="font-bold text-emerald-700">{aging.protected_accounts_count} Accounts</span>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs">
                            <span className="text-slate-500">Active Agreements:</span>{' '}
                            <span className="font-bold text-teal-800">{aging.active_plans_count} Plans</span>
                        </div>
                    </div>
                </header>

                {/* Aging Bands Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs">
                        <p className="text-xs font-semibold text-slate-500">Current (&lt; 30 Days)</p>
                        <p className="mt-1 text-lg font-bold text-slate-800">${aging.current_usd}</p>
                        <span className="inline-block mt-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 font-medium">
                            Standard
                        </span>
                    </div>

                    <div className="rounded-xl border border-amber-200 bg-amber-50/40 p-3.5 shadow-xs">
                        <p className="text-xs font-semibold text-amber-800">30 – 60 Days</p>
                        <p className="mt-1 text-lg font-bold text-amber-900">${aging.thirty_days_usd}</p>
                        <span className="inline-block mt-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800 font-medium">
                            Reminder Notice
                        </span>
                    </div>

                    <div className="rounded-xl border border-orange-200 bg-orange-50/40 p-3.5 shadow-xs">
                        <p className="text-xs font-semibold text-orange-800">60 – 90 Days</p>
                        <p className="mt-1 text-lg font-bold text-orange-900">${aging.sixty_days_usd}</p>
                        <span className="inline-block mt-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] text-orange-800 font-medium">
                            Final Demand
                        </span>
                    </div>

                    <div className="rounded-xl border border-rose-200 bg-rose-50/40 p-3.5 shadow-xs">
                        <p className="text-xs font-semibold text-rose-800">90+ Days (Critical)</p>
                        <p className="mt-1 text-lg font-bold text-rose-900">${aging.ninety_plus_usd}</p>
                        <span className="inline-block mt-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] text-rose-800 font-medium">
                            Section 279 Disconnect
                        </span>
                    </div>

                    <div className="col-span-2 md:col-span-1 rounded-xl border border-teal-200 bg-teal-50/50 p-3.5 shadow-xs">
                        <p className="text-xs font-semibold text-teal-800">Total Arrears</p>
                        <p className="mt-1 text-lg font-bold text-teal-900">${aging.total_arrears_usd}</p>
                        <span className="inline-block mt-1 rounded bg-teal-100 px-1.5 py-0.5 text-[10px] text-teal-800 font-medium">
                            {aging.total_debtor_bills} Bills Due
                        </span>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-slate-200 space-x-6 text-sm font-medium">
                    <button
                        onClick={() => setTab('plans')}
                        className={`pb-3 border-b-2 transition-colors ${
                            tab === 'plans'
                                ? 'border-teal-700 text-teal-800 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Active Payment Agreements ({plans.length})
                    </button>
                    <button
                        onClick={() => setTab('debtors')}
                        className={`pb-3 border-b-2 transition-colors ${
                            tab === 'debtors'
                                ? 'border-teal-700 text-teal-800 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Debtor Accounts ({debtors.length})
                    </button>
                </div>

                {/* Tab 1: Payment Plans */}
                {tab === 'plans' && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                        <div className="border-b border-slate-100 bg-slate-50/50 p-4 flex justify-between items-center">
                            <h2 className="font-semibold text-slate-800">
                                Structured Payment Agreements (Deeds of Settlement)
                            </h2>
                            <div className="text-xs text-slate-500">
                                Protected under Council Resolution <span className="font-mono font-semibold text-slate-700">FC/89/2026</span>
                            </div>
                        </div>

                        {plans.length === 0 ? (
                            <div className="p-8 text-center text-sm text-slate-500">
                                No active payment agreements currently established. Ratepayers can apply via the citizen portal or banking hall.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm text-slate-600">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3">Agreement Reference</th>
                                            <th className="px-4 py-3">Account Number</th>
                                            <th className="px-4 py-3">Original Arrears &amp; Waiver</th>
                                            <th className="px-4 py-3">Installment Progress</th>
                                            <th className="px-4 py-3">Disconnection Status</th>
                                            <th className="px-4 py-3">Next Due Date</th>
                                            <th className="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {plans.map((p) => (
                                            <tr key={p.id} className="hover:bg-slate-50/50">
                                                <td className="px-4 py-3 font-mono font-bold text-teal-800">
                                                    {p.plan_number}
                                                </td>
                                                <td className="px-4 py-3 font-medium text-slate-900">
                                                    {p.account_number}
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div className="text-slate-800">Arrears: ${p.total_arrears_usd}</div>
                                                    <div className="text-emerald-700 font-semibold">
                                                        50% Waiver: -${p.interest_waived_usd}
                                                    </div>
                                                    <div className="text-slate-500">Net: ${p.net_payable_usd}</div>
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    <div className="font-semibold text-slate-800">
                                                        {p.paid_installments} of {p.installment_count} installments
                                                    </div>
                                                    <div className="text-slate-500">${p.monthly_installment_usd}/month</div>
                                                    <div className="text-teal-700">Total Paid: ${p.total_paid_usd}</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {p.disconnection_immunity ? (
                                                        <span className="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                                            ✓ Protected
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800">
                                                            ⚠ Disconnect Pending
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    {p.next_installment ? (
                                                        <div>
                                                            <div className="font-semibold text-slate-800">
                                                                ${p.next_installment.amount_usd}
                                                            </div>
                                                            <div className="text-slate-500">
                                                                Due: {p.next_installment.due_date}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-400">All paid</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="rounded bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-800 uppercase">
                                                        {p.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 2: Debtor Accounts */}
                {tab === 'debtors' && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                        <div className="border-b border-slate-100 bg-slate-50/50 p-4">
                            <h2 className="font-semibold text-slate-800">
                                Customer Accounts with Overdue Balances
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Account Number</th>
                                        <th className="px-4 py-3">Property Parcel</th>
                                        <th className="px-4 py-3">Address</th>
                                        <th className="px-4 py-3">Outstanding Balance</th>
                                        <th className="px-4 py-3">Payment Plan Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {debtors.map((d) => (
                                        <tr key={d.id} className="hover:bg-slate-50/50">
                                            <td className="px-4 py-3 font-mono font-bold text-slate-900">
                                                {d.account_number}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-slate-800">
                                                {d.property ?? 'No property'}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {d.address ?? 'Unspecified'}
                                            </td>
                                            <td className="px-4 py-3 font-bold text-rose-700">
                                                ${d.outstanding_usd} {d.currency}
                                            </td>
                                            <td className="px-4 py-3">
                                                {d.has_active_plan ? (
                                                    <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                                                        Active Agreement
                                                    </span>
                                                ) : (
                                                    <span className="rounded bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                                                        No Agreement (Subject to Recovery)
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

