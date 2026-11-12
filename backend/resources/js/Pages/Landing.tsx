import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Banknote,
    Building2,
    BriefcaseBusiness,
    Calculator,
    ClipboardList,
    FileCheck2,
    Flame,
    HardHat,
    HeartHandshake,
    Landmark,
    MapPinned,
    ShieldCheck,
    Truck,
    Users,
    Waves,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type Props = {
    council: { name: string; code: string; currency: string } | null;
    demoCredentials: {
        admin: { email: string; password: string; mfa_code: string; role: string };
        citizen: { email: string; password: string; role: string };
    };
};

const modules: { title: string; description: string; icon: LucideIcon }[] = [
    { title: 'Finance & accounting', description: 'Ledger, journals, financial reports and treasury views.', icon: Landmark },
    { title: 'Revenue & billing', description: 'Customer accounts, rates, billing and collections.', icon: Banknote },
    { title: 'Assets & stores', description: 'Asset registers, inventory and municipal supplies.', icon: Building2 },
    { title: 'Procurement', description: 'Requisitions, purchasing and supplier management.', icon: ClipboardList },
    { title: 'Engineering & utilities', description: 'Water services, meters and infrastructure work.', icon: Waves },
    { title: 'Land & housing', description: 'Property records, housing applications and allocations.', icon: MapPinned },
    { title: 'People & payroll', description: 'Employee records, payroll and statutory deductions.', icon: Users },
    { title: 'Licences & permits', description: 'Applications, inspections and permit records.', icon: FileCheck2 },
    { title: 'Community services', description: 'Cemetery registers and public service records.', icon: HeartHandshake },
    { title: 'Emergency services', description: 'Incident records, dispatch and fire services.', icon: Flame },
    { title: 'Fleet & plant', description: 'Vehicles, equipment, fuel and maintenance records.', icon: Truck },
    { title: 'Capital projects', description: 'Project budgets, works and contractor certificates.', icon: HardHat },
];

const benefits = [
    { title: 'One connected workspace', text: 'Keep council teams and operational records together in one system.', icon: Building2 },
    { title: 'Clear financial records', text: 'Follow transactions through journals, reports and supporting records.', icon: Calculator },
    { title: 'Practical controls', text: 'Use role-based access and approval steps for day-to-day work.', icon: ShieldCheck },
    { title: 'Shared service visibility', text: 'Give departments a clearer view of requests, work and follow-up.', icon: BadgeCheck },
];

export default function Landing({ council }: Props) {
    return (
        <div className="min-h-screen bg-canvas text-ink antialiased">
            <Head title="ManicERP | Local authority management" />
            <header className="border-b border-line bg-white">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3" aria-label="ManicERP home">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-civic text-lg font-bold text-white">M</span>
                        <span><span className="block text-base font-semibold tracking-tight">ManicERP</span><span className="block text-xs text-muted">Local authority management</span></span>
                    </Link>
                    <nav className="hidden items-center gap-6 text-sm text-muted md:flex" aria-label="Main navigation">
                        <a href="#modules" className="hover:text-civic">Modules</a>
                        <a href="#benefits" className="hover:text-civic">Benefits</a>
                        <a href="#citizen-portal" className="hover:text-civic">Citizen portal</a>
                    </nav>
                    <Link href="/login" className="inline-flex items-center gap-2 rounded-md bg-civic px-4 py-2.5 text-sm font-semibold text-white hover:bg-civic-hover focus:outline-none focus:ring-2 focus:ring-civic focus:ring-offset-2">Sign in<ArrowRight className="h-4 w-4" /></Link>
                </div>
            </header>

            <main>
                <section className="border-b border-line bg-white">
                    <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 md:grid-cols-[1.2fr_0.8fr] md:py-24 lg:px-8">
                        <div>
                            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-line bg-canvas px-3 py-1.5 text-xs font-medium text-civic"><span className="h-2 w-2 rounded-full bg-civic" />Built for council operations</p>
                            <h1 className="max-w-3xl text-4xl font-semibold leading-tight tracking-tight text-ink sm:text-5xl">A clearer way to manage local authority services.</h1>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-muted">ManicERP brings finance, revenue, people and municipal operations into one practical workspace for council teams.</p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link href="/login" className="inline-flex items-center gap-2 rounded-md bg-civic px-5 py-3 text-sm font-semibold text-white hover:bg-civic-hover">Sign in<ArrowRight className="h-4 w-4" /></Link>
                                <a href="#demo-request" className="inline-flex items-center gap-2 rounded-md border border-line bg-white px-5 py-3 text-sm font-semibold text-ink hover:bg-canvas">Request a demo</a>
                            </div>
                            {council && <p className="mt-5 text-xs text-subtle">Demonstration environment: {council.name}</p>}
                        </div>
                        <div className="rounded-xl border border-line bg-canvas p-5 sm:p-6">
                            <div className="flex items-center justify-between border-b border-line pb-4"><div><p className="text-xs font-medium text-muted">Council workspace</p><p className="mt-1 text-base font-semibold">Connected services</p></div><span className="rounded-md border border-line bg-white px-2.5 py-1 text-xs text-muted">ManicERP</span></div>
                            <div className="mt-4 grid grid-cols-2 gap-3">
                                {[['Finance', Landmark], ['Revenue', Banknote], ['People', Users], ['Operations', BriefcaseBusiness]].map(([label, Icon]) => {
                                    const ModuleIcon = Icon as LucideIcon;
                                    return <div key={label as string} className="flex min-h-24 flex-col justify-between rounded-lg border border-line bg-white p-3"><ModuleIcon className="h-5 w-5 text-civic" /><span className="text-sm font-medium text-ink">{label as string}</span></div>;
                                })}
                            </div>
                            <p className="mt-4 text-xs leading-5 text-muted">Role-based workspaces help each team focus on the records and tasks they manage.</p>
                        </div>
                    </div>
                </section>

                <section id="modules" className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
                    <div className="mb-7 max-w-2xl"><p className="text-xs font-semibold uppercase tracking-wide text-civic">Council modules</p><h2 className="mt-2 text-2xl font-semibold tracking-tight">The work of a council, in one place</h2><p className="mt-2 text-sm leading-6 text-muted">Open the modules your council uses and your role permits.</p></div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {modules.map(({ title, description, icon: ModuleIcon }) => <article key={title} className="rounded-card border border-line bg-white p-4"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-civic-soft text-civic"><ModuleIcon className="h-5 w-5" /></span><h3 className="mt-3 text-sm font-semibold">{title}</h3><p className="mt-1 text-sm leading-5 text-muted">{description}</p></article>)}
                    </div>
                </section>

                <section id="benefits" className="border-y border-line bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                        <div className="mb-7 max-w-2xl"><p className="text-xs font-semibold uppercase tracking-wide text-civic">Designed for daily work</p><h2 className="mt-2 text-2xl font-semibold tracking-tight">Useful information for the people who need it</h2></div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">{benefits.map(({ title, text, icon: BenefitIcon }) => <article key={title}><BenefitIcon className="h-5 w-5 text-civic" /><h3 className="mt-3 text-sm font-semibold">{title}</h3><p className="mt-1 text-sm leading-6 text-muted">{text}</p></article>)}</div>
                    </div>
                </section>

                <section id="citizen-portal" className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <div className="flex flex-col justify-between gap-6 rounded-xl border border-line bg-white p-6 sm:p-8 md:flex-row md:items-center">
                        <div className="max-w-2xl"><span className="flex h-9 w-9 items-center justify-center rounded-lg bg-civic-soft text-civic"><Users className="h-5 w-5" /></span><h2 className="mt-4 text-xl font-semibold">A simpler citizen portal</h2><p className="mt-2 text-sm leading-6 text-muted">Residents can access their council account, review services and submit requests through the citizen portal.</p></div>
                        <Link href="/login" className="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-line px-4 py-2.5 text-sm font-semibold hover:bg-canvas">Citizen sign in<ArrowRight className="h-4 w-4" /></Link>
                    </div>
                </section>

                <section id="demo-request" className="border-t border-line bg-civic-soft">
                    <div className="mx-auto flex max-w-7xl flex-col justify-between gap-4 px-4 py-8 sm:px-6 md:flex-row md:items-center lg:px-8"><div><h2 className="text-base font-semibold">Request a guided demonstration</h2><p className="mt-1 text-sm text-muted">Contact your ManicERP implementation representative to arrange a walkthrough for your council.</p></div><Link href="/login" className="inline-flex items-center gap-2 self-start rounded-md bg-civic px-4 py-2.5 text-sm font-semibold text-white hover:bg-civic-hover md:self-auto">Explore the demo<ArrowRight className="h-4 w-4" /></Link></div>
                </section>
            </main>

            <footer className="border-t border-line bg-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-xs text-muted sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"><p>ManicERP · Local authority management</p><p className="max-w-3xl leading-5">Financial tools support configuration and management reporting. Official posting schedules, current applicability, complete disclosures and council approvals remain required; no automatic IPSAS compliance statement is issued.</p><Link href="/login" className="font-medium text-civic hover:underline">Sign in</Link></div>
            </footer>
        </div>
    );
}
