<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\PlatformAuditLogger;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ModuleRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly PlatformAuditLogger $platformAudit,
    ) {}

    public function index()
    {
        $council = $this->tenants->requireCouncil();
        $states = $this->registry->states($council);

        $modules = [];

        foreach ($this->registry->manifests() as $id => $manifest) {
            $modules[] = [
                'id' => $id,
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'description' => $manifest['description'],
                'dependencies' => $manifest['dependencies'],
                'available' => (bool) ($manifest['available'] ?? false),
                'core' => (bool) ($manifest['core'] ?? false),
                'enabled' => $states[$id] ?? false,
                'can_enable' => $this->registry->canEnable($council, $id),
                'can_disable' => $this->registry->canDisable($council, $id),
                'active_dependants' => $this->registry->activeDependants($council, $id),
            ];
        }

        return Inertia::render('Admin/Modules', ['modules' => $modules]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'module_id' => ['required', 'string', 'max:64'],
            'enabled' => ['required', 'boolean'],
        ]);

        $council = $this->tenants->requireCouncil();

        if (! $this->registry->exists($data['module_id'])) {
            abort(404);
        }

        try {
            $this->registry->setEnabled($council, $data['module_id'], $data['enabled'], $request->user()->getKey());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->audit->record(
            action: $data['enabled'] ? 'module.enabled' : 'module.disabled',
            after: ['module' => $data['module_id']],
            module: 'platform.core',
        );

        $this->platformAudit->record(
            action: $data['enabled'] ? 'council.module_enabled' : 'council.module_disabled',
            councilId: $council->id,
            subjectType: 'module',
            subjectId: $data['module_id'],
        );

        return back()->with('success', "Module [{$data['module_id']}] ".($data['enabled'] ? 'enabled' : 'disabled').'.');
    }
}
