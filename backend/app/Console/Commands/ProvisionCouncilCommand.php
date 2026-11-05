<?php

namespace App\Console\Commands;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\CouncilProvisioner;
use Illuminate\Console\Command;
use Throwable;

class ProvisionCouncilCommand extends Command
{
    protected $signature = 'council:provision
        {code : Council code, e.g. HARARE}
        {--name= : Council display name}
        {--domain=* : Verified host names for this council}
        {--migrate : Only run tenant migrations for an existing council}';

    protected $description = 'Provision a council directory entry, dedicated database, roles, modules and baseline data';

    public function handle(CouncilProvisioner $provisioner): int
    {
        $code = strtoupper((string) $this->argument('code'));

        try {
            if ($this->option('migrate')) {
                $council = Council::where('code', $code)->firstOrFail();
                $provisioner->migrateTenant($council);
                $this->info("Tenant migrations complete for [{$council->code}].");

                return self::SUCCESS;
            }

            $council = $provisioner->provision([
                'code' => $code,
                'name' => (string) ($this->option('name') ?: $code.' Council'),
                'domains' => array_map('strtolower', (array) $this->option('domain')),
            ]);

            $this->info("Council [{$council->code}] provisioned with database [{$council->database_name}].");
            $this->warn('No staff users or operational data were created. Use the council administration workflow to assign memberships.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
