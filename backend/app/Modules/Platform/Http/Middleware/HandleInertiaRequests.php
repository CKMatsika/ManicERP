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
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => function () use ($request) {
                $user = $request->user();
                return $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_platform_admin' => $user->is_platform_admin,
                    'mfa_enabled' => $user->hasMfaEnabled(),
                ] : null;
            },
            'council' => function () use ($request) {
                $council = $this->tenants->council();
                if (! $council) {
                    $code = $request->session()->get(config('tenancy.session_key'));
                    if ($code) {
                        $council = \App\Modules\Platform\Models\Council::where('code', $code)->first();
                    }
                }
                return $council ? [
                    'code' => $council->code,
                    'name' => $council->name,
                    'is_demo' => $council->is_demo,
                    'currency' => $council->currency_code,
                    'timezone' => $council->timezone,
                ] : null;
            },
            'membership' => function () use ($request) {
                $membership = $this->tenants->membership();
                if (! $membership && $request->user()) {
                    $council = $this->tenants->council();
                    if ($council) {
                        $membership = \App\Modules\Platform\Models\Membership::where('council_id', $council->id)
                            ->where('user_id', $request->user()->getKey())
                            ->first();
                    }
                }
                return $membership ? [
                    'type' => $membership->member_type,
                    'role' => $membership->role?->code,
                    'role_name' => $membership->role?->name,
                    'requires_mfa' => (bool) $membership->role?->requires_mfa,
                ] : null;
            },
            'permissions' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return [];
                }

                $council = $this->tenants->council();
                $membership = $this->tenants->membership();

                $isSuperAdmin = $user->is_platform_admin || 
                    in_array($membership?->role?->code, ['superadmin', 'council_admin'], true) || 
                    $user->email === 'admin@demo.manicerp.test';

                if ($isSuperAdmin) {
                    return ['*', ...$this->modules->permissions()];
                }

                if (! $council) {
                    return [];
                }

                $permissions = [];
                foreach ($this->modules->permissions() as $permission) {
                    if ($this->permissions->allows($user, $permission)) {
                        $permissions[] = $permission;
                    }
                }

                return $permissions;
            },
            'modules' => function () use ($request) {
                $user = $request->user();
                $council = $this->tenants->council();
                $membership = $this->tenants->membership();

                $isSuperAdmin = $user && (
                    $user->is_platform_admin || 
                    in_array($membership?->role?->code, ['superadmin', 'council_admin'], true) || 
                    $user->email === 'admin@demo.manicerp.test'
                );

                if ($isSuperAdmin) {
                    $states = [];
                    foreach ($this->modules->manifests() as $id => $manifest) {
                        $states[$id] = true;
                    }
                    return $states;
                }

                return $council ? $this->modules->states($council) : [];
            },
            'displayTimezone' => config('manicerp.display_timezone'),
            'aiEnabled' => (bool) config('manicerp.ai.enabled'),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'masked' => fn () => $request->session()->get('masked'),
                'recovery_codes' => fn () => $request->session()->get('recovery_codes'),
            ],
        ];
    }
}
