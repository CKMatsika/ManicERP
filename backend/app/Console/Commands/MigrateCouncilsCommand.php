<?php

namespace App\Console\Commands;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\CouncilProvisioner;
use Illuminate\Console\Command;

class MigrateCouncilsCommand extends Command
{
    protected $signature = 'council:migrate {--council= : Council code; omit for every active council}';

    protected $description = 'Run pending tenant migrations for one or all councils';

    public function handle(CouncilProvisioner $provisioner): int
    {
        $code = $this->option('council');

        $councils = $code
            ? Council::where('code', strtoupper((string) $code))->get()
            : Council::where('status', 'active')->get();

        if ($councils->isEmpty()) {
            $this->error('No matching councils.');

            return self::FAILURE;
        }

        foreach ($councils as $council) {
            $this->info("Migrating [{$council->code}]...");
            $provisioner->migrateTenant($council);
        }

        $this->info('Tenant migrations complete.');

        return self::SUCCESS;
    }
}
