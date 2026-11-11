<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\AccountingSourceService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Console\Command;

class SeedAccountingSourcesCommand extends Command
{
    protected $signature = 'accounting:seed-sources {--council= : Council code; omit for every active council}';

    protected $description = 'Seed the source-backed ZFRM/Finance Act requirements register';

    public function handle(TenantManager $tenants, AccountingSourceService $service): int
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
            $result = $tenants->runFor($council, fn () => $service->seed());
            $this->info(sprintf(
                '[%s] sources seeded: %d documents, %d requirements, %d standards linked (none verified).',
                $council->code,
                $result['documents'],
                $result['requirements'],
                $result['standards_linked'],
            ));
        }

        return self::SUCCESS;
    }
}
