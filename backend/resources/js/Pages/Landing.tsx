import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    council: { name: string; code: string; currency: string } | null;
    demoCredentials: {
        admin: { email: string; password: string; mfa_code: string; role: string };
        citizen: { email: string; password: string; role: string };
    };
};

export default function Landing({ council, demoCredentials }: Props) {
    const [activeTab, setActiveTab] = useState<'housing' | 'billing' | 'licensing' | 'smart_meter' | 'payroll'>('housing');

    // Housing Simulator State
    const [applicantName, setApplicantName] = useState('Kudzai Moyo');
    const [nationalId, setNationalId] = useState('63-182940-Q-42');
    const [standNumber, setStandNumber] = useState('STD-4821-CHK');
    const [suburb, setSuburb] = useState('Chikanga Phase 2');
    const [standPrice, setStandPrice] = useState(2400);
    const [depositRequired, setDepositRequired] = useState(400);
    const [housingGenerated, setHousingGenerated] = useState(false);

    // Billing & Debt Relief Simulator State
    const [waterConsumption, setWaterConsumption] = useState(26);
    const [priorArrears, setPriorArrears] = useState(140);
    const [planMonths, setPlanMonths] = useState(6);
    const [interestWaiverPercent, setInterestWaiverPercent] = useState(50);
    const [billingGenerated, setBillingGenerated] = useState(false);

    // Licensing Simulator State
    const [tradingName, setTradingName] = useState('Eastern Highlands Fresh Bakery');
    const [licenceType, setLicenceType] = useState('Food Hygiene & Catering Licence');
    const [licenceFee, setLicenceFee] = useState(50);
    const [licenceGenerated, setLicenceGenerated] = useState(false);

    // Smart Meter Telemetry Simulator State
    const [meterNumber, setMeterNumber] = useState('WTR-MTR-00101');
    const [averageMonthlyFlow, setAverageMonthlyFlow] = useState(17);
    const [currentMeterReading, setCurrentMeterReading] = useState(58);
    const [telemetrySimulated, setTelemetrySimulated] = useState(false);

    // Payroll Simulator State
    const [basicSalary, setBasicSalary] = useState(1200);
    const [housingAllowance, setHousingAllowance] = useState(350);
    const [transportAllowance, setTransportAllowance] = useState(150);
    const [payrollGenerated, setPayrollGenerated] = useState(false);

    // Calculations for Billing & Section 279 Relief
    const tier1Units = Math.min(waterConsumption, 10);
    const tier2Units = Math.max(0, Math.min(waterConsumption - 10, 10));
    const tier3Units = Math.max(0, waterConsumption - 20);
    const waterCharge = tier1Units * 0.45 + tier2Units * 0.85 + tier3Units * 1.45;
    const sewerageSurcharge = waterCharge * 0.25;
    const currentWaterTotal = waterCharge + sewerageSurcharge;

    const interestArrearsPortion = priorArrears * 0.30;
    const waivedInterest = (interestArrearsPortion * interestWaiverPercent) / 100;
    const netArrearsToPay = priorArrears - waivedInterest;
    const monthlyInstallment = netArrearsToPay / planMonths;

    // Calculations for Payroll
    const grossSalary = basicSalary + housingAllowance + transportAllowance;
    const nssaDeduction = Math.min(grossSalary, 700) * 0.045; // 4.5% up to USD 700 ceiling
    const lapfDeduction = basicSalary * 0.06; // 6% of basic
    const taxableIncome = grossSalary - nssaDeduction - lapfDeduction;
    let baseTax = 0;
    if (taxableIncome > 3000) baseTax += (taxableIncome - 3000) * 0.40;
    if (taxableIncome > 2000) baseTax += Math.min(taxableIncome - 2000, 1000) * 0.35;
    if (taxableIncome > 1000) baseTax += Math.min(taxableIncome - 1000, 1000) * 0.30;
    if (taxableIncome > 300) baseTax += Math.min(taxableIncome - 300, 700) * 0.25;
    if (taxableIncome > 100) baseTax += Math.min(taxableIncome - 100, 200) * 0.20;
    const aidsLevy = baseTax * 0.03;
    const totalPaye = baseTax + aidsLevy;
    const medicalAid = 45;
    const totalDeductions = nssaDeduction + lapfDeduction + totalPaye + medicalAid;
    const netSalary = grossSalary - totalDeductions;

    return (
        <div className="min-h-screen bg-slate-900 text-slate-100 antialiased selection:bg-emerald-500 selection:text-white">
            <Head title="ManicERP — Next-Generation Municipal Management System for Zimbabwe" />

            {/* Top Statutory Banner */}
            <header className="border-b border-slate-800 bg-slate-950/80 px-4 py-2 text-xs backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between text-slate-400">
                    <div className="flex items-center gap-2">
                        <span className="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Designed for Zimbabwean Urban & Rural Councils</span>
                        <span className="hidden sm:inline text-slate-600">|</span>
                        <span className="hidden sm:inline">Urban Councils Act [Cap 29:15] · IPSAS Accrual Standard · PRAZ Compliant</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link href="/login" className="text-emerald-400 hover:text-emerald-300 font-semibold">
                            Staff Sign In →
                        </Link>
                    </div>
                </div>
            </header>

            {/* Main Navigation */}
            <nav className="sticky top-0 z-50 border-b border-slate-800 bg-slate-900/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600 font-black text-white shadow-lg shadow-emerald-900/40">
                            M
                        </div>
                        <div>
                            <span className="text-lg font-bold tracking-tight text-white">Manic<span className="text-emerald-400">ERP</span></span>
                            <p className="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Municipal Operating System</p>
                        </div>
                    </div>

                    <div className="hidden md:flex items-center gap-6 text-sm text-slate-300">
                        <a href="#simulator" className="hover:text-emerald-400 transition">Town Clerk Live Simulator</a>
                        <a href="#comparison" className="hover:text-emerald-400 transition">Comparison vs. BIQ & LADS</a>
                        <a href="#modules" className="hover:text-emerald-400 transition">Module Catalogue</a>
                        <a href="#credentials" className="hover:text-emerald-400 transition">Demo Logins</a>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href="/dashboard"
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-emerald-900/30 hover:bg-emerald-500 transition"
                        >
                            Open ERP Console
                        </Link>
                    </div>
                </div>
            </nav>

            {/* Hero Section */}
            <section className="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-28">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(16,185,129,0.15),rgba(255,255,255,0))] pointer-events-none"></div>

                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
                    <div className="text-center max-w-3xl mx-auto">
                        <div className="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-950/40 px-3.5 py-1 text-xs font-semibold text-emerald-400 mb-6">
                            <span>Zimbabwe Local Authorities Benchmark</span>
                            <span>•</span>
                            <span>Engineered to Surpass Legacy Legacy BIQ & LADS</span>
                        </div>
                        <h1 className="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl leading-tight">
                            The Modern Operating System for <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300">Zimbabwe Councils</span>
                        </h1>
                        <p className="mt-6 text-lg text-slate-300 leading-relaxed">
                            A unified, high-integrity municipal enterprise platform designed specifically for Town Clerks, Chamber Secretaries, and Finance Directors. Complete with native IPSAS accrual accounting, mobile citizen services, cryptographic audit chains, and automated smart water billing.
                        </p>

                        <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <a
                                href="#simulator"
                                className="rounded-lg bg-emerald-500 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400 transition flex items-center gap-2"
                            >
                                <span>Try Town Clerk Live Simulator</span>
                                <span>↓</span>
                            </a>
                            <Link
                                href="/dashboard"
                                className="rounded-lg border border-slate-700 bg-slate-800/80 px-6 py-3.5 text-sm font-semibold text-white hover:bg-slate-700 transition"
                            >
                                Enter Demo ERP Dashboard
                            </Link>
                        </div>
                    </div>

                    {/* Trust Signals */}
                    <div className="mt-14 grid grid-cols-2 gap-4 sm:grid-cols-4 max-w-4xl mx-auto text-center border-t border-slate-800 pt-8">
                        <div>
                            <p className="text-2xl font-bold text-emerald-400">IPSAS 1-24</p>
                            <p className="text-xs text-slate-400 mt-1">Full Accrual Compliance</p>
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-emerald-400">Mobile + Web</p>
                            <p className="text-xs text-slate-400 mt-1">Native Citizen Flutter App</p>
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-emerald-400">SHA-256</p>
                            <p className="text-xs text-slate-400 mt-1">Cryptographic Audit Chain</p>
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-emerald-400">USD & ZiG</p>
                            <p className="text-xs text-slate-400 mt-1">Dual-Currency Auto Journal</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* LIVE TOWN CLERK SIMULATOR SECTION */}
            <section id="simulator" className="py-16 bg-slate-950 border-t border-b border-slate-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-10">
                        <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">Interactive Live Demonstration</span>
                        <h2 className="text-3xl font-bold text-white mt-2">Test Drive Key Council Modules Live</h2>
                        <p className="text-sm text-slate-400 mt-2">
                            No sales fluff or generic mockups. Input real parameters below as a Town Clerk, Finance Director, or Engineer to generate authentic, statutory municipal output documents.
                        </p>
                    </div>

                    {/* Role / Module Tab Selector */}
                    <div className="flex flex-wrap items-center justify-center gap-2 mb-8">
                        <button
                            onClick={() => { setActiveTab('housing'); setHousingGenerated(false); }}
                            className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                activeTab === 'housing'
                                    ? 'bg-emerald-600 text-white shadow-md'
                                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                            }`}
                        >
                            1. Housing Stand Allocation (Town Clerk)
                        </button>
                        <button
                            onClick={() => { setActiveTab('billing'); setBillingGenerated(false); }}
                            className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                activeTab === 'billing'
                                    ? 'bg-emerald-600 text-white shadow-md'
                                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                            }`}
                        >
                            2. Water Tariffs & Section 279 Debt Relief (Finance)
                        </button>
                        <button
                            onClick={() => { setActiveTab('licensing'); setLicenceGenerated(false); }}
                            className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                activeTab === 'licensing'
                                    ? 'bg-emerald-600 text-white shadow-md'
                                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                            }`}
                        >
                            3. Shop & Health Licensing (Health Director)
                        </button>
                        <button
                            onClick={() => { setActiveTab('smart_meter'); setTelemetrySimulated(false); }}
                            className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                activeTab === 'smart_meter'
                                    ? 'bg-emerald-600 text-white shadow-md'
                                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                            }`}
                        >
                            4. Smart Water AMR & Burst Alert (Engineering)
                        </button>
                        <button
                            onClick={() => { setActiveTab('payroll'); setPayrollGenerated(false); }}
                            className={`rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                activeTab === 'payroll'
                                    ? 'bg-emerald-600 text-white shadow-md'
                                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                            }`}
                        >
                            5. Statutory Municipal Payroll (HR Director)
                        </button>
                    </div>

                    {/* Active Simulator Card */}
                    <div className="grid lg:grid-cols-12 gap-6 bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
                        {/* Left Input Pane (5 cols) */}
                        <div className="lg:col-span-5 border-b lg:border-b-0 lg:border-r border-slate-800 pb-6 lg:pb-0 lg:pr-6">
                            {activeTab === 'housing' && (
                                <div className="space-y-4">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-amber-400">Scenario 1</span>
                                        <h3 className="text-lg font-bold text-white mt-1">Town Planning & Stand Allocation</h3>
                                        <p className="text-xs text-slate-400">
                                            Manage the housing waiting list, apply seniority rules, and allocate serviced municipal land parcels under council covenants.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Applicant Full Name</label>
                                        <input
                                            type="text"
                                            value={applicantName}
                                            onChange={(e) => setApplicantName(e.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">National ID</label>
                                            <input
                                                type="text"
                                                value={nationalId}
                                                onChange={(e) => setNationalId(e.target.value)}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Stand Number</label>
                                            <input
                                                type="text"
                                                value={standNumber}
                                                onChange={(e) => setStandNumber(e.target.value)}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Township / Suburb</label>
                                        <input
                                            type="text"
                                            value={suburb}
                                            onChange={(e) => setSuburb(e.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Purchase Price ($)</label>
                                            <input
                                                type="number"
                                                value={standPrice}
                                                onChange={(e) => setStandPrice(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Deposit ($)</label>
                                            <input
                                                type="number"
                                                value={depositRequired}
                                                onChange={(e) => setDepositRequired(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <button
                                        onClick={() => setHousingGenerated(true)}
                                        className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-emerald-500 transition"
                                    >
                                        Execute Allocation & Issue Offer Letter →
                                    </button>
                                </div>
                            )}

                            {activeTab === 'billing' && (
                                <div className="space-y-4">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-teal-400">Scenario 2</span>
                                        <h3 className="text-lg font-bold text-white mt-1">Inverted Block Tariffs & Debt Relief</h3>
                                        <p className="text-xs text-slate-400">
                                            Test statutory stepped water billing tariffs and Urban Councils Act Section 279 payment plan calculations with interest amnesty.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Water Consumption (Cubic Metres / m³)</label>
                                        <div className="flex items-center gap-3 mt-1">
                                            <input
                                                type="range"
                                                min="5"
                                                max="60"
                                                value={waterConsumption}
                                                onChange={(e) => setWaterConsumption(Number(e.target.value))}
                                                className="w-full accent-emerald-500"
                                            />
                                            <span className="text-sm font-bold text-emerald-400 w-12 text-right">{waterConsumption} m³</span>
                                        </div>
                                        <p className="text-[10px] text-slate-500 mt-1">Tiers: 0-10m³ Lifeline ($0.45), 11-20m³ ($0.85), 21+m³ ($1.45)</p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Ratepayer Arrears ($)</label>
                                            <input
                                                type="number"
                                                value={priorArrears}
                                                onChange={(e) => setPriorArrears(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Plan Term (Months)</label>
                                            <select
                                                value={planMonths}
                                                onChange={(e) => setPlanMonths(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            >
                                                <option value={3}>3 Months</option>
                                                <option value={6}>6 Months (Standard)</option>
                                                <option value={12}>12 Months (Relief)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Council Resolution Interest Waiver (%)</label>
                                        <input
                                            type="number"
                                            value={interestWaiverPercent}
                                            onChange={(e) => setInterestWaiverPercent(Number(e.target.value))}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <button
                                        onClick={() => setBillingGenerated(true)}
                                        className="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-teal-500 transition"
                                    >
                                        Compute Bill & Issue Section 279 Plan →
                                    </button>
                                </div>
                            )}

                            {activeTab === 'licensing' && (
                                <div className="space-y-4">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Scenario 3</span>
                                        <h3 className="text-lg font-bold text-white mt-1">Municipal Health & Shop Licences</h3>
                                        <p className="text-xs text-slate-400">
                                            Process business licence applications under the Shop Licences Act [Cap 14:17] and Public Health Act [Cap 15:17].
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Trading Enterprise Name</label>
                                        <input
                                            type="text"
                                            value={tradingName}
                                            onChange={(e) => setTradingName(e.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Licence Category</label>
                                        <select
                                            value={licenceType}
                                            onChange={(e) => {
                                                setLicenceType(e.target.value);
                                                if (e.target.value.includes('Food')) setLicenceFee(50);
                                                else if (e.target.value.includes('Liquor')) setLicenceFee(100);
                                                else if (e.target.value.includes('Building')) setLicenceFee(60);
                                                else setLicenceFee(35);
                                            }}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        >
                                            <option value="Food Hygiene & Catering Licence">Food Hygiene & Catering Licence</option>
                                            <option value="General Dealer & Retail Shop Licence">General Dealer & Retail Shop Licence</option>
                                            <option value="Liquor Outlet & Bar Licence">Liquor Outlet & Bar Licence</option>
                                            <option value="Development & Building Plan Approval Permit">Development & Building Plan Approval</option>
                                            <option value="Public Transport & Rank Permit (Kombi/Taxi)">Public Transport & Rank Permit</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Statutory Application Fee ($)</label>
                                        <input
                                            type="number"
                                            value={licenceFee}
                                            readOnly
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800/60 px-3 py-1.5 text-xs text-emerald-400 font-bold focus:outline-none cursor-not-allowed"
                                        />
                                    </div>

                                    <button
                                        onClick={() => setLicenceGenerated(true)}
                                        className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-indigo-500 transition"
                                    >
                                        Inspect Premises & Issue Certificate →
                                    </button>
                                </div>
                            )}

                            {activeTab === 'smart_meter' && (
                                <div className="space-y-4">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-blue-400">Scenario 4</span>
                                        <h3 className="text-lg font-bold text-white mt-1">Smart Water AMR & IoT Valve Control</h3>
                                        <p className="text-xs text-slate-400">
                                            Simulate automatic LoRaWAN / NB-IoT index packets, burst anomaly detection (+220% spike), and remote motorized valve cutoff.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Meter Number</label>
                                        <input
                                            type="text"
                                            value={meterNumber}
                                            onChange={(e) => setMeterNumber(e.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Historical Avg Flow (m³)</label>
                                            <input
                                                type="number"
                                                value={averageMonthlyFlow}
                                                onChange={(e) => setAverageMonthlyFlow(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Simulated Telemetry (m³)</label>
                                            <input
                                                type="number"
                                                value={currentMeterReading}
                                                onChange={(e) => setCurrentMeterReading(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <p className="text-[11px] text-amber-400 bg-amber-950/40 border border-amber-800/40 p-2.5 rounded">
                                        {currentMeterReading > averageMonthlyFlow * 2
                                            ? `🚨 Flow rate (+${Math.round(((currentMeterReading - averageMonthlyFlow) / averageMonthlyFlow) * 100)}%) exceeds burst threshold! Automated shutoff command will be dispatched.`
                                            : `✓ Flow rate within normal municipal tolerance range.`}
                                    </p>

                                    <button
                                        onClick={() => setTelemetrySimulated(true)}
                                        className="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-blue-500 transition"
                                    >
                                        Ingest IoT Packet & Actuate Valve →
                                    </button>
                                </div>
                            )}

                            {activeTab === 'payroll' && (
                                <div className="space-y-4">
                                    <div>
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-rose-400">Scenario 5</span>
                                        <h3 className="text-lg font-bold text-white mt-1">ZIMRA & Statutory Municipal Payroll</h3>
                                        <p className="text-xs text-slate-400">
                                            Calculate statutory deductions: ZIMRA PAYE progressive bands, 3% AIDS Levy, NSSA 4.5% ($700 cap), and LAPF 6% pension.
                                        </p>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-300">Basic Salary ($)</label>
                                        <input
                                            type="number"
                                            value={basicSalary}
                                            onChange={(e) => setBasicSalary(Number(e.target.value))}
                                            className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Housing Allowance ($)</label>
                                            <input
                                                type="number"
                                                value={housingAllowance}
                                                onChange={(e) => setHousingAllowance(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-300">Transport Allowance ($)</label>
                                            <input
                                                type="number"
                                                value={transportAllowance}
                                                onChange={(e) => setTransportAllowance(Number(e.target.value))}
                                                className="mt-1 w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <button
                                        onClick={() => setPayrollGenerated(true)}
                                        className="w-full rounded-lg bg-rose-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-rose-500 transition"
                                    >
                                        Compute Gross-to-Net Municipal Payslip →
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Right Output Document Pane (7 cols) */}
                        <div className="lg:col-span-7 flex flex-col justify-center">
                            {activeTab === 'housing' && (
                                <div className="bg-white text-slate-900 rounded-xl p-6 shadow-xl border border-slate-200">
                                    <div className="border-b-2 border-slate-900 pb-3 flex items-center justify-between">
                                        <div>
                                            <h4 className="text-sm font-black uppercase tracking-wider text-slate-900">DEMO CITY COUNCIL</h4>
                                            <p className="text-[10px] text-slate-600 uppercase font-semibold">Department of Housing & Community Services</p>
                                        </div>
                                        <div className="text-right">
                                            <span className="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-bold">PROVISIONAL OFFER</span>
                                            <p className="text-[10px] font-mono text-slate-500 mt-0.5">REF: ALC-2026-WMLF5F</p>
                                        </div>
                                    </div>

                                    <div className="py-4 space-y-3 text-xs">
                                        <p className="text-slate-700 leading-relaxed">
                                            <strong>Dear {applicantName}</strong> (ID: {nationalId}),<br />
                                            We are pleased to inform you that your application on the Municipal Housing Waiting List has succeeded under the Priority Seniority Allocation Scheme.
                                        </p>

                                        <div className="rounded border border-slate-200 bg-slate-50 p-3 grid grid-cols-2 gap-2 text-[11px]">
                                            <div><span className="text-slate-500">Stand Allocated:</span> <strong>{standNumber}</strong></div>
                                            <div><span className="text-slate-500">Township:</span> <strong>{suburb}</strong></div>
                                            <div><span className="text-slate-500">Purchase Price:</span> <strong>USD ${standPrice.toFixed(2)}</strong></div>
                                            <div><span className="text-slate-500">Deposit Due:</span> <strong className="text-emerald-700">USD ${depositRequired.toFixed(2)}</strong></div>
                                        </div>

                                        <div className="text-[10px] text-slate-600 bg-amber-50 border border-amber-200 p-2.5 rounded">
                                            <strong>Town Planning Covenants:</strong> Foundation must be completed within 12 months; roof level within 24 months. Failure will trigger statutory repossession under Model Building By-laws.
                                        </div>
                                    </div>

                                    <div className="pt-3 border-t border-slate-200 flex items-center justify-between text-[10px] text-slate-500">
                                        <span>Signed: <strong>Town Clerk & Chamber Secretary</strong></span>
                                        <span className="text-emerald-700 font-semibold">✓ Cryptographic Audit Verified</span>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'billing' && (
                                <div className="bg-white text-slate-900 rounded-xl p-6 shadow-xl border border-slate-200">
                                    <div className="border-b border-slate-200 pb-3 flex items-center justify-between">
                                        <div>
                                            <h4 className="text-sm font-bold text-slate-900">MUNICIPAL UTILITY BILL & RELIEF NOTICE</h4>
                                            <p className="text-[10px] text-slate-500">Urban Councils Act Section 279 Debt Relief Agreement</p>
                                        </div>
                                        <span className="rounded bg-teal-100 text-teal-800 px-2 py-0.5 text-[10px] font-bold">IMMUNITY ACTIVE</span>
                                    </div>

                                    <div className="py-3 space-y-2 text-xs">
                                        <div className="rounded border border-slate-200 p-3 bg-slate-50">
                                            <p className="font-semibold text-slate-800 mb-1">Stepped Water Consumption Breakdown ({waterConsumption} m³):</p>
                                            <div className="space-y-1 text-[11px]">
                                                <div className="flex justify-between">
                                                    <span>Lifeline Tier (0-10 m³ @ $0.45):</span>
                                                    <span>${(tier1Units * 0.45).toFixed(2)}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Standard Tier (11-20 m³ @ $0.85):</span>
                                                    <span>${(tier2Units * 0.85).toFixed(2)}</span>
                                                </div>
                                                {tier3Units > 0 && (
                                                    <div className="flex justify-between text-amber-700 font-medium">
                                                        <span>High Volume Tier (21+ m³ @ $1.45):</span>
                                                        <span>${(tier3Units * 1.45).toFixed(2)}</span>
                                                    </div>
                                                )}
                                                <div className="flex justify-between border-t border-slate-200 pt-1 font-semibold">
                                                    <span>Current Water & Sewer Total:</span>
                                                    <span>${currentWaterTotal.toFixed(2)}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded border border-teal-200 bg-teal-50/70 p-3 text-[11px]">
                                            <p className="font-bold text-teal-900 mb-1">Section 279 Payment Plan Agreement:</p>
                                            <div className="space-y-0.5 text-teal-800">
                                                <div className="flex justify-between"><span>Gross Arrears Brought Forward:</span> <span>${priorArrears.toFixed(2)}</span></div>
                                                <div className="flex justify-between font-semibold text-emerald-700">
                                                    <span>Council 50% Interest Amnesty Waiver:</span> <span>-${waivedInterest.toFixed(2)}</span>
                                                </div>
                                                <div className="flex justify-between border-t border-teal-200 pt-1 font-bold">
                                                    <span>Net Monthly Arrears Installment ({planMonths} mos):</span>
                                                    <span>${monthlyInstallment.toFixed(2)} / mo</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="pt-2 border-t border-slate-200 flex justify-between text-[10px] text-slate-500">
                                        <span>Status: Disconnection Immunity Enforced</span>
                                        <span className="font-mono text-slate-400">Journal: JRN-2026-WTR91</span>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'licensing' && (
                                <div className="bg-white text-slate-900 rounded-xl p-6 shadow-xl border-2 border-slate-900 text-center">
                                    <div className="inline-block rounded-full border-2 border-slate-900 px-3 py-0.5 text-[9px] font-black uppercase tracking-widest text-slate-800 mb-2">
                                        ZIMBABWE LOCAL AUTHORITY
                                    </div>
                                    <h4 className="text-base font-black tracking-tight text-slate-900">MUNICIPAL TRADING & HEALTH LICENCE</h4>
                                    <p className="text-[10px] text-slate-500 font-semibold uppercase">Shop Licences Act [Cap 14:17] & Public Health By-Laws</p>

                                    <div className="my-4 py-3 border-y border-dashed border-slate-300 space-y-1 text-xs">
                                        <p className="text-slate-500 text-[10px]">THIS IS TO CERTIFY THAT</p>
                                        <p className="text-sm font-bold text-slate-900">{tradingName}</p>
                                        <p className="text-slate-500 text-[10px]">HAS BEEN DULY LICENSED FOR</p>
                                        <p className="text-xs font-semibold text-emerald-800">{licenceType}</p>
                                    </div>

                                    <div className="grid grid-cols-3 gap-2 text-[10px] text-left bg-slate-50 p-2.5 rounded border border-slate-200">
                                        <div><span className="text-slate-400 block">Licence No:</span> <strong>LIC-2026-8910</strong></div>
                                        <div><span className="text-slate-400 block">Fee Settled:</span> <strong>USD ${licenceFee}.00</strong></div>
                                        <div><span className="text-slate-400 block">Valid Until:</span> <strong>31 Dec 2027</strong></div>
                                    </div>

                                    <div className="mt-3 text-[10px] text-slate-400 flex items-center justify-between">
                                        <span>Public Health Inspector: <strong>PASSED</strong></span>
                                        <span className="text-emerald-700 font-mono">QR: manicerp.gov/lic/8910</span>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'smart_meter' && (
                                <div className="bg-slate-950 text-slate-100 rounded-xl p-6 shadow-xl border border-slate-800 font-mono text-xs">
                                    <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                        <div className="flex items-center gap-2">
                                            <span className="h-2.5 w-2.5 rounded-full bg-blue-500 animate-ping"></span>
                                            <span className="font-bold text-slate-200">LoRaWAN / NB-IoT Gateway Telemetry</span>
                                        </div>
                                        <span className="text-[10px] text-blue-400 bg-blue-950 px-2 py-0.5 rounded border border-blue-800">AMR_PACKET_0x9B</span>
                                    </div>

                                    <div className="py-4 space-y-2.5">
                                        <div className="flex justify-between text-slate-400">
                                            <span>Meter Serial:</span> <span className="text-slate-100 font-bold">{meterNumber}</span>
                                        </div>
                                        <div className="flex justify-between text-slate-400">
                                            <span>Communication Link:</span> <span className="text-emerald-400">LoRaWAN (RSSI -84 dBm, SNR +9)</span>
                                        </div>
                                        <div className="flex justify-between text-slate-400">
                                            <span>Current Ingested Flow:</span> <span className="text-amber-400 font-bold">{currentMeterReading} m³</span>
                                        </div>
                                        <div className="flex justify-between text-slate-400">
                                            <span>Historical Baseline:</span> <span>{averageMonthlyFlow} m³ / month</span>
                                        </div>

                                        <div className={`p-3 rounded border text-[11px] ${
                                            currentMeterReading > averageMonthlyFlow * 2
                                                ? 'bg-rose-950/60 border-rose-800 text-rose-300'
                                                : 'bg-emerald-950/60 border-emerald-800 text-emerald-300'
                                        }`}>
                                            {currentMeterReading > averageMonthlyFlow * 2 ? (
                                                <div>
                                                    <p className="font-bold text-rose-200">⚠ ANOMALY: BURST DETECTED (+{Math.round(((currentMeterReading - averageMonthlyFlow) / averageMonthlyFlow) * 100)}%)</p>
                                                    <p className="text-[10px] mt-0.5 text-rose-300">Downlink command dispatched: <code>ACTUATE_VALVE(CLOSE)</code></p>
                                                    <p className="text-[10px] mt-0.5">Valve status: <span className="font-bold text-white">CLOSED</span></p>
                                                </div>
                                            ) : (
                                                <p className="font-bold">✓ Normal Flow Pattern. Valve status: OPEN</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="pt-2 border-t border-slate-800 text-[10px] text-slate-500 flex justify-between">
                                        <span>Battery Level: 96%</span>
                                        <span>Tamper Status: SECURE</span>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'payroll' && (
                                <div className="bg-white text-slate-900 rounded-xl p-6 shadow-xl border border-slate-200 text-xs">
                                    <div className="border-b border-slate-200 pb-3 flex items-center justify-between">
                                        <div>
                                            <h4 className="text-sm font-bold text-slate-900">MUNICIPAL PAYROLL PAYSLIP</h4>
                                            <p className="text-[10px] text-slate-500">ZIMRA & Statutory Local Authority Deductions</p>
                                        </div>
                                        <span className="rounded bg-rose-100 text-rose-800 px-2 py-0.5 text-[10px] font-bold">CONFIDENTIAL</span>
                                    </div>

                                    <div className="py-3 grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="font-bold text-slate-700 mb-1 border-b border-slate-100 pb-1">Earnings</p>
                                            <div className="space-y-1 text-[11px]">
                                                <div className="flex justify-between"><span>Basic Salary:</span> <span>${basicSalary.toFixed(2)}</span></div>
                                                <div className="flex justify-between"><span>Housing Allowance:</span> <span>${housingAllowance.toFixed(2)}</span></div>
                                                <div className="flex justify-between"><span>Transport Allowance:</span> <span>${transportAllowance.toFixed(2)}</span></div>
                                                <div className="flex justify-between font-bold border-t border-slate-200 pt-1 text-slate-900">
                                                    <span>Total Gross:</span> <span>${grossSalary.toFixed(2)}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <p className="font-bold text-slate-700 mb-1 border-b border-slate-100 pb-1">Statutory Deductions</p>
                                            <div className="space-y-1 text-[11px]">
                                                <div className="flex justify-between"><span>NSSA (4.5% POBS):</span> <span>${nssaDeduction.toFixed(2)}</span></div>
                                                <div className="flex justify-between"><span>LAPF Pension (6%):</span> <span>${lapfDeduction.toFixed(2)}</span></div>
                                                <div className="flex justify-between"><span>ZIMRA PAYE:</span> <span>${baseTax.toFixed(2)}</span></div>
                                                <div className="flex justify-between text-rose-700"><span>3% AIDS Levy:</span> <span>${aidsLevy.toFixed(2)}</span></div>
                                                <div className="flex justify-between"><span>Medical Aid:</span> <span>${medicalAid.toFixed(2)}</span></div>
                                                <div className="flex justify-between font-bold border-t border-slate-200 pt-1 text-rose-800">
                                                    <span>Total Deductions:</span> <span>-${totalDeductions.toFixed(2)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="p-2.5 rounded bg-slate-900 text-white flex items-center justify-between font-bold text-sm">
                                        <span>NET PAYABLE TO BANK:</span>
                                        <span className="text-emerald-400 font-mono text-base">${netSalary.toFixed(2)}</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>

            {/* STRATEGIC COMPARISON: MANICERP VS BIQ VS LADS */}
            <section id="comparison" className="py-20 bg-slate-900">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-12">
                        <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">Competitive Analysis</span>
                        <h2 className="text-3xl font-bold text-white mt-2">How ManicERP Surpasses BIQ & LADS Africa</h2>
                        <p className="text-sm text-slate-400 mt-2">
                            While legacy systems like BIQ and LADS pioneered computerization in Zimbabwean local authorities, they carry decades of technical debt. ManicERP was architected from day one to address their critical shortcomings.
                        </p>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-950/60 shadow-xl">
                        <table className="w-full text-left text-xs sm:text-sm">
                            <thead className="bg-slate-900 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800">
                                <tr>
                                    <th className="px-6 py-4">Capability & Statutory Mandate</th>
                                    <th className="px-6 py-4 text-emerald-400 font-bold">ManicERP (Next-Gen)</th>
                                    <th className="px-6 py-4 text-slate-400">Legacy BIQ (Quill)</th>
                                    <th className="px-6 py-4 text-slate-400">LADS Africa</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/60 text-slate-300">
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Citizen Mobile Self-Service App</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Native Flutter iOS & Android App</td>
                                    <td className="px-6 py-3.5 text-slate-500">✗ No mobile app (Counter only)</td>
                                    <td className="px-6 py-3.5 text-slate-500">✗ Limited web portal</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">IPSAS Accrual Accounting (IPSAS 1-24)</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Built-in standard registers & balances</td>
                                    <td className="px-6 py-3.5 text-amber-500">Partial (Patchwork cash-to-accrual)</td>
                                    <td className="px-6 py-3.5 text-amber-500">Partial (Requires custom consulting)</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Cryptographic Tamper-Proof Audit Trail</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ SHA-256 Merkle Chain Verification</td>
                                    <td className="px-6 py-3.5 text-slate-500">✗ Standard mutable database logs</td>
                                    <td className="px-6 py-3.5 text-slate-500">✗ Standard DB logging</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Smart AMR/AMI & Prepaid STS Water</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ LoRaWAN/NB-IoT + Valve Actuation</td>
                                    <td className="px-6 py-3.5 text-amber-500">Cell phone reading only (Manual)</td>
                                    <td className="px-6 py-3.5 text-slate-500">✗ Manual meter entry</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Housing Waiting List Seniority Engine</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Priority scoring, renewal & offer issuance</td>
                                    <td className="px-6 py-3.5 text-slate-500">Basic land register (No seniority engine)</td>
                                    <td className="px-6 py-3.5 text-slate-500">Stand ledger only</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Urban Councils Act s279 Debt Relief</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Automated amnesty & immunity controls</td>
                                    <td className="px-6 py-3.5 text-slate-500">Manual journal adjustments</td>
                                    <td className="px-6 py-3.5 text-slate-500">Manual adjustments</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">PRAZ Procurement & 3-Way Matching</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Strict statutory thresholds & matching</td>
                                    <td className="px-6 py-3.5 text-amber-500">Purchase orders only</td>
                                    <td className="px-6 py-3.5 text-amber-500">Stores & orders</td>
                                </tr>
                                <tr className="hover:bg-slate-900/40">
                                    <td className="px-6 py-3.5 font-medium text-white">Modern Clean User Experience (UI/UX)</td>
                                    <td className="px-6 py-3.5 text-emerald-400 font-semibold">✓ Fast, human-centered, responsive React</td>
                                    <td className="px-6 py-3.5 text-slate-500">Legacy Windows 98/XP style forms</td>
                                    <td className="px-6 py-3.5 text-slate-500">Complex dated web grids</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {/* COMPLETE 10-DIRECTORATE MODULE CATALOGUE */}
            <section id="modules" className="py-20 bg-slate-950 border-t border-slate-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto mb-12">
                        <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">Complete Municipal Suite</span>
                        <h2 className="text-3xl font-bold text-white mt-2">10 Directorates & 35 Integrated Modules</h2>
                        <p className="text-sm text-slate-400 mt-2">
                            Every municipal function in one interconnected system. No fragmented silos or third-party spreadsheet workarounds.
                        </p>
                    </div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <ModuleCard
                            title="1. Treasury & Revenue Cycle"
                            status="Implemented & Live"
                            items={[
                                'Multi-currency valuation rolls & ratepayer accounts',
                                'Inverted block tariffs (Lifeline, Standard, Commercial)',
                                'Cashier POS desk with multi-currency drawer balancing',
                                'Bank reconciliation & electronic Paynow clearing',
                                'Section 279 payment plans & disconnection immunity',
                            ]}
                        />
                        <ModuleCard
                            title="2. Town Planning, Housing & GIS"
                            status="Implemented & Live"
                            items={[
                                'Housing waiting list with seniority priority scoring',
                                'Stand inventory (High, Medium, Low density, Commercial)',
                                'Automated provisional stand offer letters & covenants',
                                'Citizen waiting list renewal & status tracking in mobile',
                                'Subdivision & building plan appraisal workflows',
                            ]}
                        />
                        <ModuleCard
                            title="3. Engineering & Water Utilities"
                            status="Implemented & Live"
                            items={[
                                'Mechanical and Smart IoT AMR/AMI water meters',
                                'Automated telemetry ingestion & +220% burst alerts',
                                'Remote motorized valve shutoff commands',
                                'Prepaid STS IEC 62055-41 token integration provisions',
                                'Non-Revenue Water (NRW) district metering zones',
                            ]}
                        />
                        <ModuleCard
                            title="4. Fixed Assets & Stores (IPSAS 17)"
                            status="Implemented & Live"
                            items={[
                                'Componentized infrastructure asset register',
                                'Automated monthly straight-line depreciation engine',
                                'GPS coordinates, custodian & barcode/QR tagging',
                                'Central stores inventory with bin cards & reorder points',
                                'Stores issues, receipts and revaluation journals',
                            ]}
                        />
                        <ModuleCard
                            title="5. Human Resources & Payroll"
                            status="Implemented & Live"
                            items={[
                                'Automated ZIMRA progressive PAYE calculations',
                                '3% National AIDS Trust Fund (AIDS Levy)',
                                'NSSA Pension POBS (4.5% up to USD 700 cap)',
                                'Local Authorities Pension Fund (LAPF 6%)',
                                'Bank batch transfer file (TXT/CSV) generation',
                            ]}
                        />
                        <ModuleCard
                            title="6. Public Health & Licensing"
                            status="Implemented & Live"
                            items={[
                                'Shop Licences Act [Cap 14:17] compliance register',
                                'Food Hygiene & Restaurant inspection audits',
                                'Liquor outlet council endorsement workflows',
                                'Building permits & outdoor billboard permits',
                                'Digital QR-verifiable certificates of operation',
                            ]}
                        />
                        <ModuleCard
                            title="7. Financial Accounting & IPSAS"
                            status="Implemented & Live"
                            items={[
                                'Multi-dimensional double-entry general ledger',
                                'Auditor-General IPSAS 1-24 compliance register',
                                'Statement of Financial Position (IPSAS 1)',
                                'Statement of Financial Performance (IPSAS 1)',
                                'Cryptographic SHA-256 tamper-evident audit ledger',
                            ]}
                        />
                        <ModuleCard
                            title="8. Supply Chain & PRAZ Procurement"
                            status="Implemented & Live"
                            items={[
                                'Requisitions with departmental budget commitment checks',
                                'PRAZ procurement thresholds (RFQ, Tender, Direct)',
                                'Three-way purchase order, receipt & invoice matching',
                                'Supplier performance scoring & suspension flags',
                                'Statutory withholding tax certificate generation',
                            ]}
                        />
                        <ModuleCard
                            title="9. Cemeteries & Grave Register"
                            status="Implemented & Live"
                            items={[
                                'Cemetery plot reservation & lawn section mapping',
                                'Depth checks (6ft, 8ft, 10ft) & double-interment rules',
                                'Statutory burial permits & interment orders (BUR)',
                                'Perpetual care & headstone erection approvals',
                            ]}
                        />
                        <ModuleCard
                            title="10. Emergency Services & Fire Brigade"
                            status="Implemented & Live"
                            items={[
                                'Emergency incident dispatch (Structural fire, RTA, Ambulance)',
                                'Fire hydrant pressure & flow rate telemetry logs',
                                'Commercial fire safety compliance certificates (FSC)',
                                'Statutory callout & inter-facility ambulance fees',
                            ]}
                        />
                        <ModuleCard
                            title="11. Fleet & Heavy Plant Management"
                            status="Implemented & Live"
                            items={[
                                'Compactors, tippers, graders, ambulances & plant register',
                                'Fuel coupon disbursement & serialized voucher tracking',
                                'Digital trip logbooks with starting & ending odometer logs',
                                'Preventative maintenance schedules & cost allocation',
                            ]}
                        />
                        <ModuleCard
                            title="12. Devolution Capital Projects"
                            status="Implemented & Live"
                            items={[
                                'Roads, water reservoirs, schools & clinic projects',
                                'Intergovernmental Devolution Grant fund tracking',
                                'Interim Payment Certificates (IPC) engineer sign-offs',
                                'Statutory 10% defects liability retention money held',
                            ]}
                        />
                    </div>
                </div>
            </section>

            {/* DEMO ACCESS & LOGIN CREDENTIALS */}
            <section id="credentials" className="py-16 bg-slate-900 border-t border-slate-800">
                <div className="mx-auto max-w-5xl px-4 sm:px-6">
                    <div className="rounded-2xl border border-emerald-500/30 bg-gradient-to-b from-slate-800/80 to-slate-900/90 p-8 shadow-2xl">
                        <div className="text-center max-w-2xl mx-auto mb-8">
                            <span className="text-xs font-bold uppercase tracking-wider text-emerald-400">Instant Access</span>
                            <h3 className="text-2xl font-bold text-white mt-1">Ready to Explore the Live Council Environment?</h3>
                            <p className="text-xs text-slate-300 mt-2">
                                Use the pre-configured credentials below to log into the main ERP console or test the citizen mobile interface.
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 gap-6">
                            <div className="rounded-xl border border-slate-700 bg-slate-950/70 p-5">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-xs font-bold text-emerald-400 uppercase">Super Administrator / Town Clerk</span>
                                    <span className="text-[10px] bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded font-mono">Role: admin</span>
                                </div>
                                <div className="space-y-1.5 text-xs">
                                    <p className="text-slate-400">Email: <strong className="text-white font-mono">{demoCredentials.admin.email}</strong></p>
                                    <p className="text-slate-400">Password: <strong className="text-white font-mono">{demoCredentials.admin.password}</strong></p>
                                    <p className="text-slate-400">2FA Code: <strong className="text-amber-400 font-mono">{demoCredentials.admin.mfa_code}</strong> <span className="text-slate-500">(Instant fallback)</span></p>
                                </div>
                                <Link
                                    href="/login"
                                    className="mt-4 block w-full text-center rounded-lg bg-emerald-600 py-2 text-xs font-bold text-white hover:bg-emerald-500 transition"
                                >
                                    Log In to Superuser ERP →
                                </Link>
                            </div>

                            <div className="rounded-xl border border-slate-700 bg-slate-950/70 p-5">
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-xs font-bold text-teal-400 uppercase">Citizen / Ratepayer Portal</span>
                                    <span className="text-[10px] bg-teal-950 text-teal-400 border border-teal-800 px-2 py-0.5 rounded font-mono">Role: citizen</span>
                                </div>
                                <div className="space-y-1.5 text-xs">
                                    <p className="text-slate-400">Email: <strong className="text-white font-mono">{demoCredentials.citizen.email}</strong></p>
                                    <p className="text-slate-400">Password: <strong className="text-white font-mono">{demoCredentials.citizen.password}</strong></p>
                                    <p className="text-slate-400">Property: <strong className="text-white font-mono">AV-1024</strong> <span className="text-slate-500">(ACC-0000001)</span></p>
                                </div>
                                <Link
                                    href="/portal"
                                    className="mt-4 block w-full text-center rounded-lg bg-teal-600 py-2 text-xs font-bold text-white hover:bg-teal-500 transition"
                                >
                                    Open Citizen Web Portal →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t border-slate-800 bg-slate-950 py-8 text-center text-xs text-slate-500">
                <p>ManicERP Municipal Operating System · Developed for Local Authorities in Zimbabwe.</p>
                <p className="mt-1">All demonstration models ground on authentic statutory instruments and municipal by-laws.</p>
            </footer>
        </div>
    );
}

function ModuleCard({ title, items, status, badge }: { title: string; items: string[]; status: string; badge?: string }) {
    return (
        <div className="rounded-xl border border-slate-800 bg-slate-900/60 p-5 hover:border-slate-700 transition">
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-sm font-bold text-white">{title}</h3>
                {badge ? (
                    <span className="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-medium">{badge}</span>
                ) : (
                    <span className="text-[10px] bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded font-medium">{status}</span>
                )}
            </div>
            <ul className="space-y-1.5 text-xs text-slate-400 mt-3">
                {items.map((item, idx) => (
                    <li key={idx} className="flex items-start gap-2">
                        <span className="text-emerald-500 text-xs mt-0.5">▪</span>
                        <span>{item}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

