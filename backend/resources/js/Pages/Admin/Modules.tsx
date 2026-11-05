import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState } from '../../Components/Ui';

type Module = {
    id: string;
    name: string;
    version: string;
    description: string;
    dependencies: string[];
    available: boolean;
    core: boolean;
    enabled: boolean;
    can_enable: boolean;
    can_disable: boolean;
    active_dependants: string[];
};

export default function Modules({ modules }: { modules: Module[] }) {
    function toggle(module: Module) {
        router.patch('/admin/modules', { module_id: module.id, enabled: !module.enabled }, { preserveScroll: true });
    }

    return (
        <AppLayout title="Modules">
            <Head title="Modules" />

            <p className="mb-4 max-w-3xl text-sm text-slate-600">
                Activation is enforced on the server for routes, jobs and integrations. Core identity, audit and accounting safeguards cannot be disabled,
                and a module with active dependants cannot be switched off.
            </p>

            {modules.length === 0 ? (
                <EmptyState title="No module manifests" />
            ) : (
                <div className="grid gap-3 lg:grid-cols-2">
                    {modules.map((module) => (
                        <div key={module.id} className="rounded-lg border border-slate-200 bg-white p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{module.name}</p>
                                    <p className="text-xs text-slate-500">{module.id} · v{module.version}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={module.enabled ? 'green' : 'slate'}>{module.enabled ? 'Enabled' : 'Disabled'}</Badge>
                                    {module.core && <Badge tone="sky">Core</Badge>}
                                    {!module.available && <Badge tone="amber">Not implemented</Badge>}
                                </div>
                            </div>

                            <p className="mt-2 text-sm text-slate-600">{module.description}</p>

                            <p className="mt-2 text-xs text-slate-500">
                                Dependencies: {module.dependencies.length ? module.dependencies.join(', ') : 'none'}
                            </p>

                            {module.active_dependants.length > 0 && (
                                <p className="mt-1 text-xs text-amber-700">Active dependants: {module.active_dependants.join(', ')}</p>
                            )}

                            <div className="mt-3">
                                {module.core ? (
                                    <p className="text-xs text-slate-500">Core module: always on.</p>
                                ) : (
                                    <button
                                        onClick={() => toggle(module)}
                                        disabled={module.enabled ? !module.can_disable : !module.can_enable}
                                        className="rounded border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {module.enabled ? 'Disable module' : 'Enable module'}
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
