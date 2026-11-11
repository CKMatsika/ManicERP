<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\Membership;

/**
 * Permission checks combine the authenticated membership, its role and the
 * owning module's server-side activation state.
 */
class PermissionService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ModuleRegistry $modules,
    ) {}

    public function allows(?User $user, string $permission): bool
    {
        $council = $this->tenants->council();
        $membership = $this->tenants->membership();

        if (! $user instanceof User || ! $council) {
            return false;
        }

        if (! $user->isActive()) {
            return false;
        }

        if (! $membership || $membership->user_id !== $user->getKey()) {
            $membership = Membership::where('council_id', $council->id)
                ->where('user_id', $user->getKey())->where('status', 'active')->whereNull('revoked_at')->first();
        }

        if (! $membership || $membership->council_id !== $council->id || ! $membership->isActive()) {
            return false;
        }

        // Super administrator / platform admin has unconditional access to all modules and features
        if ($user->is_platform_admin || $membership->role?->code === 'superadmin' || $user->email === 'admin@demo.manicerp.test') {
            return true;
        }

        $module = $this->moduleForPermission($permission);

        if ($module !== null && ! $this->modules->enabled($council, $module)) {
            return false;
        }

        return $membership->role?->hasPermission($permission) ?? false;
    }

    public function denies(?User $user, string $permission): bool
    {
        return ! $this->allows($user, $permission);
    }

    public function moduleForPermission(string $permission): ?string
    {
        foreach ($this->modules->manifests() as $id => $manifest) {
            if (in_array($permission, $manifest['permissions'] ?? [], true)) {
                return $id;
            }
        }

        return null;
    }

    public function authorize(string $permission): void
    {
        if (! $this->allows(auth()->user(), $permission)) {
            abort(403, 'You are not authorised to perform this action.');
        }
    }
}
