import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Employee = {
    id: string;
    ec_number: string;
    name: string;
    national_id?: string;
    department: string;
    designation: string;
    grade: string;
    basic_salary_usd: string;
    gross_salary_usd: string;
    bank: string;
    account?: string;
    status: string;
};

type PayrollRun = {
    id: string;
    ref: string;
    period: string;
    gross_usd: string;
    deductions_usd: string;
    net_usd: string;
    status: string;
};

type Payslip = {
    employee_id: string;
    employee_number: string;
    full_name: string;
    national_id?: string;
    designation: string;
    grade: string;
    department: string;
    bank_name: string;
    account_number?: string;
    nssa_number?: string;
    lapf_number?: string;
    gross_usd: string;
    deductions_usd: string;
    net_usd: string;
    earnings: Array<{ name: string; amount_usd: string }>;
    deductions_list: Array<{ name: string; amount_usd: string }>;
};

type Summary = {
    total_staff_count: number;
    monthly_gross_wage_usd: string;
    employees: Employee[];
    runs: PayrollRun[];
    latest_payslips: Payslip[];
};

type Props = {
    summary: Summary;
};

export default function Payroll({ summary }: Props) {
    const [showProcessModal, setShowProcessModal] = useState(false);
    const [selectedPayslip, setSelectedPayslip] = useState<Payslip | null>(
        summary.latest_payslips[0] || null
    );

    const [activeTab, setActiveTab] = useState<'employees' | 'runs' | 'payslips'>('employees');

    const [processForm, setProcessForm] = useState({
        period_start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
        period_end: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
    });

    const handleProcessSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/hr/payroll/process', processForm, {
            onSuccess: () => setShowProcessModal(false),
        });
    };

    return (
        <AppLayout title="Municipal HR & Statutory Payroll">
            <Head title="Municipal HR & Payroll - ManicERP" />

            <div className="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                ZIMBABWE STATUTORY COMPLIANT
                            </span>
                            <span className="text-xs text-slate-500 font-medium">ZIMRA PAYE • NSSA • LAPF • AIDS LEVY</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 mt-1">
                            Municipal Human Resources & Payroll
                        </h1>
                        <p className="text-sm text-slate-600 mt-0.5">
                            Automated statutory tax deduction engine, electronic bank batch disbursements, and council pay advice slips.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setShowProcessModal(true)}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-sm font-medium shadow-sm"
                        >
                            <span>⚡</span>
                            <span>Process Monthly Payroll Run</span>
                        </button>
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Municipal Workforce</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{summary.total_staff_count} Staff Members</div>
                        <div className="text-xs text-slate-500 mt-1">Across 6 operational directorates</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gross Monthly Wage Commitment</div>
                        <div className="mt-2 text-2xl font-bold text-emerald-700">USD ${summary.monthly_gross_wage_usd}</div>
                        <div className="text-xs text-slate-500 mt-1">Basic salary + Housing & Transport</div>
                    </div>

                    <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Latest Net Salary Disbursement</div>
                        <div className="mt-2 text-2xl font-bold text-blue-700">
                            USD ${summary.runs[0]?.net_usd || '0.00'}
                        </div>
                        <div className="text-xs text-slate-500 mt-1">After statutory ZIMRA, NSSA & LAPF deductions</div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="border-b border-slate-200">
                    <nav className="flex space-x-6">
                        <button
                            onClick={() => setActiveTab('employees')}
                            className={`py-3 px-1 border-b-2 text-sm font-medium transition ${
                                activeTab === 'employees'
                                    ? 'border-emerald-600 text-emerald-700 font-bold'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Employee Master Register ({summary.employees.length})
                        </button>
                        <button
                            onClick={() => setActiveTab('runs')}
                            className={`py-3 px-1 border-b-2 text-sm font-medium transition ${
                                activeTab === 'runs'
                                    ? 'border-emerald-600 text-emerald-700 font-bold'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Payroll Runs & Bank Exports ({summary.runs.length})
                        </button>
                        <button
                            onClick={() => setActiveTab('payslips')}
                            className={`py-3 px-1 border-b-2 text-sm font-medium transition ${
                                activeTab === 'payslips'
                                    ? 'border-emerald-600 text-emerald-700 font-bold'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Electronic Payslips & Pay Advice ({summary.latest_payslips.length})
                        </button>
                    </nav>
                </div>

                {/* Tab 1: Employees */}
                {activeTab === 'employees' && (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-slate-600 font-semibold text-left">
                                    <tr>
                                        <th className="py-3 px-4">EC Number</th>
                                        <th className="py-3 px-4">Employee Name</th>
                                        <th className="py-3 px-4">Designation & Grade</th>
                                        <th className="py-3 px-4">Directorate</th>
                                        <th className="py-3 px-4 text-right">Basic (USD)</th>
                                        <th className="py-3 px-4 text-right">Gross (USD)</th>
                                        <th className="py-3 px-4">Bank Disbursement</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {summary.employees.map((emp) => (
                                        <tr key={emp.id} className="hover:bg-slate-50/75 transition">
                                            <td className="py-3.5 px-4 font-mono font-medium text-emerald-800 whitespace-nowrap">
                                                {emp.ec_number}
                                            </td>
                                            <td className="py-3.5 px-4 font-medium text-slate-900">
                                                <div>{emp.name}</div>
                                                <div className="text-xs text-slate-400 font-mono">ID: {emp.national_id}</div>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <div className="text-slate-900 font-medium">{emp.designation}</div>
                                                <div className="text-xs text-slate-500">{emp.grade}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-700">{emp.department}</td>
                                            <td className="py-3.5 px-4 text-right font-medium text-slate-700 whitespace-nowrap">
                                                ${emp.basic_salary_usd}
                                            </td>
                                            <td className="py-3.5 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                                ${emp.gross_salary_usd}
                                            </td>
                                            <td className="py-3.5 px-4 text-xs text-slate-600">
                                                <div>{emp.bank}</div>
                                                <div className="font-mono text-slate-400">{emp.account || '—'}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                    ACTIVE
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 2: Payroll Runs */}
                {activeTab === 'runs' && (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-slate-600 font-semibold text-left">
                                    <tr>
                                        <th className="py-3 px-4">Run Reference</th>
                                        <th className="py-3 px-4">Pay Period</th>
                                        <th className="py-3 px-4 text-right">Total Gross Wage</th>
                                        <th className="py-3 px-4 text-right">Statutory Deductions</th>
                                        <th className="py-3 px-4 text-right">Net Disbursement</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                        <th className="py-3 px-4 text-right">Bank File Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {summary.runs.map((run) => (
                                        <tr key={run.id} className="hover:bg-slate-50/75 transition">
                                            <td className="py-3.5 px-4 font-mono font-bold text-emerald-800">
                                                {run.ref}
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-700">{run.period}</td>
                                            <td className="py-3.5 px-4 text-right font-medium text-slate-700 whitespace-nowrap">
                                                ${run.gross_usd}
                                            </td>
                                            <td className="py-3.5 px-4 text-right font-medium text-amber-700 whitespace-nowrap">
                                                -${run.deductions_usd}
                                            </td>
                                            <td className="py-3.5 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                                ${run.net_usd}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 uppercase">
                                                    {run.status}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <a
                                                    href={`/hr/payroll/${run.id}/bank-export`}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded text-xs font-semibold transition"
                                                >
                                                    <span>📥</span>
                                                    <span>Export Bank CSV</span>
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 3: Payslips */}
                {activeTab === 'payslips' && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Selector List */}
                        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
                            <h3 className="font-bold text-slate-900 pb-3 text-sm">Select Employee Pay Advice</h3>
                            {summary.latest_payslips.map((ps) => (
                                <button
                                    key={ps.employee_id}
                                    onClick={() => setSelectedPayslip(ps)}
                                    className={`w-full text-left py-3 px-2 rounded-lg transition ${
                                        selectedPayslip?.employee_id === ps.employee_id
                                            ? 'bg-emerald-50 border border-emerald-200'
                                            : 'hover:bg-slate-50'
                                    }`}
                                >
                                    <div className="font-semibold text-slate-900 text-sm">{ps.full_name}</div>
                                    <div className="text-xs text-slate-500">{ps.employee_number} • {ps.designation}</div>
                                    <div className="text-xs font-bold text-emerald-700 mt-1">Net: USD ${ps.net_usd}</div>
                                </button>
                            ))}
                        </div>

                        {/* Interactive Payslip Card */}
                        <div className="md:col-span-2">
                            {selectedPayslip ? (
                                <div className="bg-white rounded-xl border-2 border-emerald-700 shadow-md p-6 space-y-5">
                                    <div className="flex items-center justify-between border-b pb-4">
                                        <div>
                                            <div className="text-xs font-bold uppercase tracking-wider text-emerald-800">Demo City Council</div>
                                            <h2 className="text-xl font-bold text-slate-900">Official Municipal Pay Advice Slip</h2>
                                            <div className="text-xs text-slate-500">Urban Councils Act [Cap 29:15] Statutory Pay Advice</div>
                                        </div>
                                        <div className="text-right font-mono text-xs text-slate-500">
                                            <div>EC: <span className="font-bold text-slate-900">{selectedPayslip.employee_number}</span></div>
                                            <div>NSSA: {selectedPayslip.nssa_number || 'N/A'}</div>
                                            <div>LAPF: {selectedPayslip.lapf_number || 'N/A'}</div>
                                        </div>
                                    </div>

                                    {/* Employee Meta */}
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50 p-3 rounded-lg text-xs">
                                        <div>
                                            <span className="text-slate-400 block">Employee:</span>
                                            <span className="font-bold text-slate-900">{selectedPayslip.full_name}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block">Designation:</span>
                                            <span className="font-bold text-slate-900">{selectedPayslip.designation}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block">Directorate:</span>
                                            <span className="font-bold text-slate-900">{selectedPayslip.department}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block">Grade / Band:</span>
                                            <span className="font-bold text-slate-900">{selectedPayslip.grade}</span>
                                        </div>
                                    </div>

                                    {/* Earnings and Deductions */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                        <div className="space-y-2">
                                            <h4 className="font-bold text-emerald-800 border-b pb-1">Earnings (USD)</h4>
                                            {selectedPayslip.earnings.map((e, idx) => (
                                                <div key={idx} className="flex justify-between py-1 text-xs">
                                                    <span className="text-slate-700">{e.name}</span>
                                                    <span className="font-semibold text-slate-900">${e.amount_usd}</span>
                                                </div>
                                            ))}
                                            <div className="flex justify-between pt-2 border-t font-bold text-sm">
                                                <span>Gross Earnings:</span>
                                                <span className="text-emerald-700">${selectedPayslip.gross_usd}</span>
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <h4 className="font-bold text-amber-800 border-b pb-1">Statutory & Other Deductions</h4>
                                            {selectedPayslip.deductions_list.map((d, idx) => (
                                                <div key={idx} className="flex justify-between py-1 text-xs">
                                                    <span className="text-slate-700">{d.name}</span>
                                                    <span className="font-semibold text-amber-700">-${d.amount_usd}</span>
                                                </div>
                                            ))}
                                            <div className="flex justify-between pt-2 border-t font-bold text-sm">
                                                <span>Total Deductions:</span>
                                                <span className="text-amber-700">-${selectedPayslip.deductions_usd}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Net Pay Callout */}
                                    <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center justify-between">
                                        <div>
                                            <div className="text-xs font-semibold text-emerald-800 uppercase">Net Take-Home Pay (Bank Electronic Transfer)</div>
                                            <div className="text-xs text-slate-500">Payable to: {selectedPayslip.bank_name} ({selectedPayslip.account_number || 'Direct Account'})</div>
                                        </div>
                                        <div className="text-2xl font-black text-emerald-800">
                                            USD ${selectedPayslip.net_usd}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">
                                    No payslips generated yet. Run monthly payroll to generate employee pay advice slips.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal: Process Payroll Run */}
            {showProcessModal && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-slate-900">Run Monthly Municipal Payroll</h3>
                            <button onClick={() => setShowProcessModal(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <p className="text-xs text-slate-600">
                            Executes statutory ZIMRA PAYE bands (plus 3% AIDS Levy), NSSA 4.5% pension cap, LAPF 6% pension, and medical aid deductions for all active employees.
                        </p>

                        <form onSubmit={handleProcessSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Period Start</label>
                                    <input
                                        type="date"
                                        required
                                        value={processForm.period_start}
                                        onChange={(e) => setProcessForm({ ...processForm, period_start: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Period End</label>
                                    <input
                                        type="date"
                                        required
                                        value={processForm.period_end}
                                        onChange={(e) => setProcessForm({ ...processForm, period_end: e.target.value })}
                                        className="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t">
                                <button
                                    type="button"
                                    onClick={() => setShowProcessModal(false)}
                                    className="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 text-sm font-medium text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg"
                                >
                                    Process & Calculate Pay
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

