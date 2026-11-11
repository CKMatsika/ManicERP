<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Council;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a scheduled task once per active council with validated tenant context.
 * A failure in one council is logged and does not stop the others.
 */
class TenantScheduleRunner
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @return array<int, array{council: string, ok: bool, result?: mixed, error?: string}>
     */
    public function run(string $task, callable $callback): array
    {
        $results = [];

        foreach (Council::where('status', 'active')->orderBy('code')->cursor() as $council) {
            try {
                $result = $this->tenants->runFor($council, function (Council $current) use ($callback, $task) {
                    try {
                        return $callback($current, $task);
                    } catch (Throwable $exception) {
                        app(TenantLogger::class)->write('error', 'Scheduled task failed', ['task' => $task, 'error' => $exception->getMessage()]);
                        throw $exception;
                    }
                });

                $results[] = ['council' => $council->code, 'ok' => true, 'result' => $result];
            } catch (Throwable $exception) {
                Log::error('Scheduled tenant task failed', [
                    'task' => $task,
                    'council' => $council->code,
                    'error_class' => $exception::class,
                ]);

                $results[] = ['council' => $council->code, 'ok' => false, 'error' => $exception->getMessage()];
            }
        }

        return $results;
    }
}
