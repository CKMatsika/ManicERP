import React, { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import PageHeader from '../../Components/PageHeader';
import StatusBadge from '../../Components/StatusBadge';
import Icon from '../../Components/Icon';
import DataTable, { Column, RowAction } from '../../Components/DataTable';
import { ConfirmDialog, Modal } from '../../Components/FormComponents';
import { formatDate, money, type SharedProps } from '../../lib';

type Run = {
    id: string;
    number: string;
    service: string;
    period: string;
    status: string;
    totals: any;
};

type Tariff = {
    code: string;
    name: string;
    service: string;
    basis: string;
    status: string;
    effective_from: string | null;
    version: number;
    bands: any[];
};

export default function Billing({
    runs = [],
    tariffs = [],
}: {
    runs: Run[];
    tariffs: Tariff[];
}) {
    const { council, permissions } = usePage<SharedProps>().props;

    const [activeTab, setActiveTab] = useState<'wizard' | 'runs' | 'tariffs'>('runs');
    const [wizardStep, setWizardStep] = useState<1 | 2 | 3>(1);
    const [confirmRunModal, setConfirmRunModal] = useState<Run | null>(null);
    const [postingRunModal, setPostingRunModal] = useState<Run | null>(null);
    const [isSubmittingPreview, setIsSubmittingPreview] = useState(false);

    const previewForm = useForm({
        service_type: 'rates',
        period_start: new Date().toISOString().slice(0, 8) + '01',
        period_end: new Date().toISOString().slice(0, 10),
    });

    const canApprove = permissions.includes('*') || permissions.includes('revenue.billing.approve');

    function handleStartWizard() {
        setWizardStep(1);
        setActiveTab('wizard');
    }

    function handlePreviewSubmit() {
        setIsSubmittingPreview(true);
        previewForm.post('/revenue/billing/preview', {
            onSuccess: () => {
                setIsSubmittingPreview(false);
                setActiveTab('runs');
            },
            onError: () => {
                setIsSubmittingPreview(false);
            },
        });
    }

    function handleApprove(run: Run) {
        router.post(`/revenue/billing/${run.id}/approve`, {}, {
            onSuccess: () => setConfirmRunModal(null),
        });
    }

    function handlePost(run: Run) {
        router.post(`/revenue/billing/${run.id}/post`, {}, {
            onSuccess: () => setPostingRunModal(null),
        });
    }

    const runColumns: Column<Run>[] = [
        {
            key: 'number',
            header: 'Run identifier',
            sortable: true,
            render: (r) => <span className="font-semibold text-emerald-800">{r.number}</span>,
        },
        {
            key: 'service',
            header: 'Service',
            render: (r) => <span className="capitalize font-medium text-slate-800">{r.service}</span>,
        },
        {
            key: 'period',
            header: 'Period range',
            render: (r) => <span className="text-slate-600">{r.period}</span>,
        },
        {
            key: 'included',
            header: 'Scope',
            render: (r) => (
                <span className="text-xs text-slate-600">
                    <strong className="text-slate-800">{r.totals?.included ?? 0}</strong> included
                    {r.totals?.skipped ? ` · ${r.totals.skipped} skipped` : ''}
                </span>
            ),
        },
        {
            key: 'total_minor',
            header: 'Batch total',
            align: 'right',
            render: (r) => (
                <span className="font-semibold tabular-nums text-slate-900">
                    {money(r.totals?.total_minor ?? 0, council?.currency)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (r) => <StatusBadge status={r.status} />,
        },
    ];

    const runActions: RowAction<Run>[] = [
        {
            label: 'Approve control totals',
            icon: 'Check',
            hidden: (r) => r.status !== 'previewed' || !canApprove,
            onClick: (r) => setConfirmRunModal(r),
        },
        {
            label: 'Post bills and GL journals',
            icon: 'FileText',
            tone: 'default',
            hidden: (r) => r.status !== 'approved' || !canApprove,
            onClick: (r) => setPostingRunModal(r),
        },
    ];

    const tariffColumns: Column<Tariff>[] = [
        {
            key: 'code',
            header: 'Tariff code',
            render: (t) => <span className="font-mono text-xs font-semibold text-slate-800">{t.code}</span>,
        },
        {
            key: 'name',
            header: 'Tariff name',
            render: (t) => <span className="font-medium text-slate-900">{t.name}</span>,
        },
        {
            key: 'service',
            header: 'Service',
            render: (t) => <span className="capitalize text-slate-700">{t.service}</span>,
        },
        {
            key: 'basis',
            header: 'Charge basis',
            render: (t) => <span className="text-slate-600 capitalize">{t.basis?.replace('_', ' ')}</span>,
        },
        {
            key: 'effective_from',
            header: 'Effective date',
            render: (t) => <span className="text-slate-600">{formatDate(t.effective_from)}</span>,
        },
        {
            key: 'version',
            header: 'Version',
            render: (t) => <span className="text-xs text-slate-500 font-mono">v{t.version}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (t) => <StatusBadge status={t.status} />,
        },
    ];

    return (
        <AppLayout title="Billing runs">
            <Head title="Billing runs" />

            <PageHeader
                title="Municipal billing cycles & rating engine"
                description="Periodic assessment, tariff calculation, bill snapshots and immutable general ledger posting."
                breadcrumbs={[{ label: 'Revenue & billing', href: '/revenue/billing' }, { label: 'Billing runs' }]}
                primaryAction={{
                    label: 'New billing run wizard',
                    icon: 'Plus',
                    onClick: handleStartWizard,
                }}
            />

            {/* Navigation Tabs */}
            <div className="mb-6 border-b border-slate-200">
                <nav className="flex space-x-6">
                    <button
                        type="button"
                        onClick={() => setActiveTab('runs')}
                        className={`border-b-2 py-3 text-sm font-medium transition-colors ${
                            activeTab === 'runs'
                                ? 'border-emerald-700 text-emerald-800 font-semibold'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        }`}
                    >
                        <span>Recent billing runs</span>
                        <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                            {runs.length}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('tariffs')}
                        className={`border-b-2 py-3 text-sm font-medium transition-colors ${
                            activeTab === 'tariffs'
                                ? 'border-emerald-700 text-emerald-800 font-semibold'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
                        }`}
                    >
                        <span>Approved tariffs & rating schedules</span>
                        <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                            {tariffs.length}
                        </span>
                    </button>
                    {activeTab === 'wizard' && (
                        <button
                            type="button"
                            className="border-b-2 border-emerald-700 py-3 text-sm font-semibold text-emerald-800"
                        >
                            <span>3-Step Billing Run Wizard</span>
                        </button>
                    )}
                </nav>
            </div>

            {/* 3-Step Wizard View */}
            {activeTab === 'wizard' && (
                <div className="max-w-3xl space-y-6">
                    {/* Step Indicator */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between">
                            {[
                                { step: 1, title: 'Scope & Service' },
                                { step: 2, title: 'Review Parameters' },
                                { step: 3, title: 'Confirm & Execute' },
                            ].map((s) => (
                                <div key={s.step} className="flex items-center gap-2">
                                    <div
                                        className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                                            wizardStep === s.step
                                                ? 'bg-emerald-700 text-white'
                                                : wizardStep > s.step
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-400'
                                        }`}
                                    >
                                        {wizardStep > s.step ? <Icon name="Check" className="h-4 w-4" /> : s.step}
                                    </div>
                                    <span className={`text-xs font-medium ${wizardStep === s.step ? 'text-slate-900 font-semibold' : 'text-slate-500'}`}>
                                        {s.title}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Step 1: Select Scope */}
                    {wizardStep === 1 && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                            <h3 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">
                                Step 1: Select Service & Accounting Period
                            </h3>
                            <div>
                                <label htmlFor="srv" className="block text-sm font-medium text-slate-700">
                                    Tariff service class <span className="text-rose-600">*</span>
                                </label>
                                <select
                                    id="srv"
                                    value={previewForm.data.service_type}
                                    onChange={(e) => previewForm.setData('service_type', e.target.value)}
                                    className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                >
                                    <option value="rates">Property Rates & Valuation Assessment</option>
                                    <option value="water">Water Utility & Metered Consumption</option>
                                    <option value="refuse">Solid Waste & Refuse Collection</option>
                                    <option value="rental">Council Property Rental & Leases</option>
                                </select>
                                <p className="mt-1 text-xs text-slate-500">
                                    Determines the active tariff version and formula applied to all qualifying accounts.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="p_start" className="block text-sm font-medium text-slate-700">
                                        Period start date <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        id="p_start"
                                        value={previewForm.data.period_start}
                                        onChange={(e) => previewForm.setData('period_start', e.target.value)}
                                        className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="p_end" className="block text-sm font-medium text-slate-700">
                                        Period end date <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        id="p_end"
                                        value={previewForm.data.period_end}
                                        onChange={(e) => previewForm.setData('period_end', e.target.value)}
                                        className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('runs')}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setWizardStep(2)}
                                    className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                                >
                                    Continue to Review →
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Review Parameters */}
                    {wizardStep === 2 && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                            <h3 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">
                                Step 2: Review Calculation Parameters
                            </h3>
                            <div className="rounded-lg bg-slate-50 p-4 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Service:</span>
                                    <span className="font-semibold uppercase text-slate-900">{previewForm.data.service_type}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Cycle date range:</span>
                                    <span className="text-slate-900 font-medium">
                                        {previewForm.data.period_start} to {previewForm.data.period_end}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Council currency:</span>
                                    <span className="text-slate-900 font-medium">{council?.currency ?? 'USD'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Execution mode:</span>
                                    <span className="text-amber-700 font-medium">Dry-run Preview (No debit postings until approved)</span>
                                </div>
                            </div>

                            <div className="flex justify-between pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setWizardStep(1)}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    ← Back
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setWizardStep(3)}
                                    className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                                >
                                    Proceed to Confirmation →
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Explicit Confirmation */}
                    {wizardStep === 3 && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                            <h3 className="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">
                                Step 3: Run Billing Preview Engine
                            </h3>
                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 space-y-1.5">
                                <p className="font-semibold text-sm">Pre-Execution Notice</p>
                                <p>
                                    This will simulate charges across all active customer accounts matching the service scope.
                                    A control total report will be generated for treasury and town clerk approval.
                                </p>
                            </div>

                            <div className="flex justify-between pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setWizardStep(2)}
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    ← Back
                                </button>
                                <button
                                    type="button"
                                    onClick={handlePreviewSubmit}
                                    disabled={isSubmittingPreview}
                                    className="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                                >
                                    {isSubmittingPreview ? (
                                        <span>Calculating tariff snapshots...</span>
                                    ) : (
                                        <>
                                            <Icon name="Check" className="h-4 w-4" />
                                            <span>Start preview calculation</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Recent Billing Runs View */}
            {activeTab === 'runs' && (
                <div>
                    <DataTable
                        columns={runColumns}
                        data={runs}
                        keyField="id"
                        rowActions={runActions}
                        emptyTitle="No billing runs executed yet"
                        emptyHint="Use the New Billing Run wizard to initiate assessment runs for property rates, water, or refuse."
                    />
                </div>
            )}

            {/* Approved Tariffs View */}
            {activeTab === 'tariffs' && (
                <div>
                    <DataTable
                        columns={tariffColumns}
                        data={tariffs}
                        keyField="code"
                        emptyTitle="No active tariffs found"
                        emptyHint="Council tariffs are configured with effective dating and valuation bands."
                    />
                </div>
            )}

            {/* Approval Confirmation Dialog */}
            {confirmRunModal && (
                <ConfirmDialog
                    isOpen={!!confirmRunModal}
                    onClose={() => setConfirmRunModal(null)}
                    onConfirm={() => handleApprove(confirmRunModal)}
                    title="Approve Billing Control Totals"
                    itemName={confirmRunModal.number}
                    consequence={`Approving this run locks ${confirmRunModal.totals?.included ?? 0} customer bills totaling ${money(confirmRunModal.totals?.total_minor ?? 0, council?.currency)}. Once approved, bills may be posted to debtor accounts.`}
                    confirmLabel="Approve run"
                    tone="primary"
                />
            )}

            {/* Post Bills & Journals Irreversible Confirmation Dialog */}
            {postingRunModal && (
                <ConfirmDialog
                    isOpen={!!postingRunModal}
                    onClose={() => setPostingRunModal(null)}
                    onConfirm={() => handlePost(postingRunModal)}
                    title="Post Bills to General Ledger & Debtor Accounts"
                    itemName={postingRunModal.number}
                    consequence={`WARNING: Posting is irreversible. This action creates immutable debtor bills and debit/credit general ledger journal entries for ${postingRunModal.totals?.included ?? 0} customer accounts.`}
                    confirmLabel="Post bills and journals"
                    tone="danger"
                />
            )}
        </AppLayout>
    );
}
