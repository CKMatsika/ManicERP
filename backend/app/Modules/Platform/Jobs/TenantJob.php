<?php

namespace App\Modules\Platform\Jobs;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantCache;
use App\Modules\Platform\Services\TenantLogger;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for every queued job that touches council operational data.
 *
 * The council identifier is the only tenant context that travels in the
 * payload. The job re-resolves the council from the central directory, rejects
 * missing/inactive councils, re-checks module availability, and always clears
 * the tenant connection and log/cache context, including on exceptions.
 */
abstract class TenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    public function __construct(public readonly string $councilId) {}

    /** Module identifier that must remain enabled, when applicable. */
    protected ?string $module = null;

    final public function handle(): void
    {
        $council = Council::find($this->councilId);

        if (! $council || ! $council->isActive()) {
            $exception = new TenantContextException("No active council for tenant job [{$this->councilId}].");
            $this->failPermanently($exception);

            throw $exception;
        }

        $registry = app(ModuleRegistry::class);

        if ($this->module !== null && ! $registry->enabled($council, $this->module)) {
            $exception = new TenantContextException("Module [{$this->module}] is disabled for council [{$council->code}].");
            $this->failPermanently($exception);

            throw $exception;
        }

        $tenants = app(TenantManager::class);

        try {
            $tenants->runFor($council, function () use ($council): void {
                $this->tenantCache()->put('job.last_run', now()->toIso8601String());
                try {
                    $this->execute($council);
                } catch (Throwable $exception) {
                    app(TenantLogger::class)->write('error', 'Tenant job failed', ['job' => static::class, 'error' => $exception->getMessage()]);
                    throw $exception;
                }
            });
        } catch (Throwable $exception) {
            Log::error('Tenant job failed', [
                'job' => static::class,
                'council' => $council->code,
                'council_id' => $council->id,
                'error_class' => $exception::class,
            ]);

            throw $exception;
        } finally {
            // runFor restores or clears the previous context. This is defensive
            // for jobs invoked directly outside a worker.
            if ($tenants->council() === null) {
                Context::forget(['council', 'tenant_database']);
            }
        }
    }

    abstract protected function execute(Council $council): void;

    private function failPermanently(Throwable $exception): void
    {
        if ($this->job !== null) {
            $this->fail($exception);
        }
    }

    protected function tenantCache(): TenantCache
    {
        return app(TenantCache::class);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Tenant job permanently failed', [
            'job' => static::class,
            'council_id' => $this->councilId,
            'error_class' => $exception::class,
        ]);
    }
}
