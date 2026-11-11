<?php

namespace App\Modules\Platform\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Council-prefixed private file storage. Every path includes the council code,
 * so two councils can never overwrite or read each other's files even if they
 * share a disk.
 */
class TenantStorage
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function path(string $path): string
    {
        $council = $this->tenants->requireCouncil();
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $council->code)) {
            throw new \DomainException('Unsafe council storage identifier.');
        }

        if ($path === '' || str_contains($path, '\\') || str_contains($path, ':') || str_starts_with($path, '/') || preg_match('~(^|/)\.{1,2}(/|$)|[\x00-\x1f]~', $path)) {
            throw new \DomainException('A safe relative council storage path is required.');
        }

        return 'councils/'.$council->code.'/'.ltrim($path, '/');
    }

    private function disk(string $disk = 'documents'): Filesystem
    {
        return Storage::disk($disk);
    }

    public function put(string $path, string $contents, string $disk = 'documents'): string
    {
        $full = $this->path($path);
        $this->disk($disk)->put($full, $contents);

        return $full;
    }

    public function exists(string $path, string $disk = 'documents'): bool
    {
        return $this->disk($disk)->exists($this->path($path));
    }
}
