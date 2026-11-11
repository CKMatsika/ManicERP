<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Council-prefixed cache access. Job and UI code must use this instead of the
 * global Cache facade whenever the value is council-specific.
 */
class TenantCache
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function key(string $key): string
    {
        $council = $this->tenants->requireCouncil();

        return 'council:'.$council->code.':'.$key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, int|\DateTimeInterface|null $ttl = null): bool
    {
        return Cache::put($this->key($key), $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->key($key));
    }

    public function remember(string $key, int|\DateTimeInterface|\Closure|null $ttl, \Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $ttl, $callback);
    }
}
