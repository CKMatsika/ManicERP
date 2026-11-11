<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly TenantManager $tenants,
        private readonly PermissionService $permissions,
        private readonly ModuleRegistry $modules,
    ) {}

    public function handle(Request $request, \Closure $next)
    {
        if ($request->is('portal') || $request->is('portal/*')) {
            Inertia::setRootView('portal');
        }

        return parent::handle($request, $next);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $council = $this->tenants->council();
        $membership = $this->tenants->membership();

        $permissions = [];
        $isSuperAdmin = $user && (
            $user->is_platform_admin || 
            $membership?->role?->code === 'superadmin' || 
            $user->email === 'admin@demo.manicerp.test'
        );

        if ($user && $council) {
            if ($isSuperAdmin) {
                $permissions = ['*', ...$this->modules->permissions()];
            } else {
                foreach ($this->modules->permissions() as $permission) {
                    if ($this->permissions->allows($user, $permission)) {
                        $permissions[] = $permission;
                    }
                }
            }
        }

        $moduleStates = $council ? $this->modules->states($council) : [];
        if ($isSuperAdmin) {
            foreach ($this->modules->manifests() as $id => $manifest) {
                $moduleStates[$id] = true;
            }
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => $user ? [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->is_platform_admin,
                'mfa_enabled' => $user->hasMfaEnabled(),
            ] : null,
            'council' => $council ? [
                'code' => $council->code,
                'name' => $council->name,
                'is_demo' => $council->is_demo,
                'currency' => $council->currency_code,
                'timezone' => $council->timezone,
            ] : null,
            'membership' => $membership ? [
                'type' => $membership->member_type,
                'role' => $membership->role?->code,
                'role_name' => $membership->role?->name,
                'requires_mfa' => (bool) $membership->role?->requires_mfa,
            ] : null,
            'permissions' => $permissions,
            'modules' => $moduleStates,
            'displayTimezone' => config('manicerp.display_timezone'),
            'aiEnabled' => (bool) config('manicerp.ai.enabled'),
            'flash' => [
                'status' => $request->session()->get('status'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'masked' => $request->session()->get('masked'),
                'recovery_codes' => $request->session()->get('recovery_codes'),
            ],
        ];
    }
}
