<?php

namespace App\Console\Commands;

use App\Modules\Licensing\Jobs\LicenceMaintenanceJob;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\TenantScheduleRunner;
use Illuminate\Console\Command;

class RunScheduledTaskCommand extends Command
{
    protected $signature = 'council:run-scheduled {task=audit-verify : Task to run for every active council}';

    protected $description = 'Run a scheduled task for every council with validated tenant context';

    public function handle(TenantScheduleRunner $runner, AuditLogger $audit): int
    {
        $task = (string) $this->argument('task');

        $tasks = [
            'audit-verify' => function () use ($audit): array {
                return $audit->verify();
            },
            'licence-maintenance' => function ($council): string {
                LicenceMaintenanceJob::dispatch($council->id);

                return 'maintenance job queued';
            },
        ];

        if (! isset($tasks[$task])) {
            $this->error("Unknown tenant scheduled task [{$task}].");

            return self::FAILURE;
        }

        $results = $runner->run($task, $tasks[$task]);
        $failed = false;

        foreach ($results as $result) {
            if ($result['ok']) {
                $this->info("[{$result['council']}] {$task} OK");
            } else {
                $failed = true;
                $this->error("[{$result['council']}] {$task} FAILED: {$result['error']}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
