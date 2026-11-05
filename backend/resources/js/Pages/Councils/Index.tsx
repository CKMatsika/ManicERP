import { Head, router } from '@inertiajs/react';

type CouncilOption = { code: string; name: string; role: string | null; demo: boolean };

export default function CouncilsIndex({ staff, citizen }: { staff: CouncilOption[]; citizen: CouncilOption[] }) {
    function select(code: string) {
        router.post('/councils/select', { code });
    }

    return (
        <>
            <Head title="Select council" />
            <div className="mx-auto max-w-2xl px-4 py-10">
                <h1 className="text-xl font-semibold tracking-tight">Select a council</h1>
                <p className="mt-1 text-sm text-slate-600">Your access is limited to councils where you hold an active membership.</p>

                <section className="mt-6">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Staff access</h2>
                    <div className="mt-2 space-y-2">
                        {staff.length === 0 && <p className="text-sm text-slate-500">No staff memberships.</p>}
                        {staff.map((option) => (
                            <button
                                key={option.code}
                                onClick={() => select(option.code)}
                                className="flex w-full items-center justify-between rounded border border-slate-200 bg-white px-4 py-3 text-left hover:border-teal-400"
                            >
                                <span>
                                    <span className="block text-sm font-medium">{option.name}</span>
                                    <span className="block text-xs text-slate-500">{option.role}</span>
                                </span>
                                {option.demo && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">Demo</span>}
                            </button>
                        ))}
                    </div>
                </section>

                <section className="mt-6">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Citizen access</h2>
                    <div className="mt-2 space-y-2">
                        {citizen.length === 0 && <p className="text-sm text-slate-500">No citizen memberships.</p>}
                        {citizen.map((option) => (
                            <button
                                key={option.code}
                                onClick={() => select(option.code)}
                                className="flex w-full items-center justify-between rounded border border-slate-200 bg-white px-4 py-3 text-left hover:border-teal-400"
                            >
                                <span>
                                    <span className="block text-sm font-medium">{option.name}</span>
                                    <span className="block text-xs text-slate-500">{option.role}</span>
                                </span>
                                {option.demo && <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">Demo</span>}
                            </button>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
