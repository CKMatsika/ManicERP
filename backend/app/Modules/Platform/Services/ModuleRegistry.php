<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Council;

/**
 * Reads module manifests, enforces dependencies and stores per-council
 * activation state. Core safeguards (identity, audit, accounting) can never
 * be switched off.
 */
class ModuleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function manifests(): array
    {
        return config('modules.modules', []);
    }

    public function manifest(string $moduleId): array
    {
        $manifests = $this->manifests();

        if (! isset($manifests[$moduleId])) {
            throw new \InvalidArgumentException("Unknown module [{$moduleId}].");
        }

        return $manifests[$moduleId];
    }

    public function exists(string $moduleId): bool
    {
        return isset($this->manifests()[$moduleId]);
    }

    public function isAvailable(string $moduleId): bool
    {
        return (bool) ($this->manifest($moduleId)['available'] ?? false);
    }

    public function isCore(string $moduleId): bool
    {
        return (bool) ($this->manifest($moduleId)['core'] ?? false);
    }

    /** @return array<string, bool> */
    public function states(Council $council): array
    {
        $rows = $council->modules()->pluck('status', 'module_id');
        $states = [];

        foreach ($this->manifests() as $id => $manifest) {
            $states[$id] = $this->isCore($id) || ($rows[$id] ?? null) === 'enabled';
        }

        return $states;
    }

    public function enabled(Council $council, string $moduleId): bool
    {
        if (! $this->exists($moduleId)) {
            return false;
        }

        if ($this->isCore($moduleId)) {
            return true;
        }

        return $council->modules()
            ->where('module_id', $moduleId)
            ->where('status', 'enabled')
            ->exists();
    }

    public function ensureEnabled(Council $council, string $moduleId): void
    {
        if (! $this->enabled($council, $moduleId)) {
            abort(403, "The [{$moduleId}] module is not enabled for this council.");
        }
    }

    /** @return array<int, string> */
    public function dependants(string $moduleId): array
    {
        $dependants = [];

        foreach ($this->manifests() as $id => $manifest) {
            if (in_array($moduleId, $manifest['dependencies'] ?? [], true)) {
                $dependants[] = $id;
            }
        }

        return $dependants;
    }

    /** @return array<int, string> */
    public function activeDependants(Council $council, string $moduleId): array
    {
        return array_values(array_filter(
            $this->dependants($moduleId),
            fn (string $id) => $this->enabled($council, $id),
        ));
    }

    public function canDisable(Council $council, string $moduleId): bool
    {
        return ! $this->isCore($moduleId) && $this->activeDependants($council, $moduleId) === [];
    }

    public function canEnable(Council $council, string $moduleId): bool
    {
        if (! $this->isAvailable($moduleId)) {
            return false;
        }

        foreach ($this->manifest($moduleId)['dependencies'] ?? [] as $dependency) {
            if (! $this->enabled($council, $dependency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Activation/deactivation. Server-side dependency rules are enforced here,
     * independent of any navigation state.
     */
    public function setEnabled(Council $council, string $moduleId, bool $enabled, ?string $actorId = null): void
    {
        if ($enabled && ! $this->canEnable($council, $moduleId)) {
            throw new \DomainException("Module [{$moduleId}] cannot be enabled: unavailable or missing dependencies.");
        }

        if (! $enabled && ! $this->canDisable($council, $moduleId)) {
            if ($this->isCore($moduleId)) {
                throw new \DomainException("Core module [{$moduleId}] cannot be disabled.");
            }

            throw new \DomainException("Module [{$moduleId}] has active dependants and cannot be disabled.");
        }

        $council->modules()->updateOrCreate(
            ['module_id' => $moduleId],
            [
                'status' => $enabled ? 'enabled' : 'disabled',
                'enabled_at' => $enabled ? now() : null,
                'enabled_by' => $actorId,
            ],
        );
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['permissions'] ?? [] as $permission) {
                $permissions[] = $permission;
            }
        }

        sort($permissions);

        return array_values(array_unique($permissions));
    }
}
