<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\AccountingStandardsService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Console\Command;

class SeedStandardsCommand extends Command
{
    protected $signature = 'accounting:seed-standards {--council= : Council code; omit for every active council}';

    protected $description = 'Seed the unverified IPSAS/standards register for one or all councils';

    public function handle(TenantManager $tenants, AccountingStandardsService $service): int
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
            $tenants->connect($council);
            $count = $service->seedDefaults();
            $this->info("[{$council->code}] standards register seeded ({$count} entries, all unverified).");
        }

        return self::SUCCESS;
    }
}
