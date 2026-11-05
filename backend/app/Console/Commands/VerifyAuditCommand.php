<?php

namespace App\Console\Commands;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Console\Command;

class VerifyAuditCommand extends Command
{
    protected $signature = 'audit:verify {--council= : Council code (defaults to every active council)}';

    protected $description = 'Verify the tamper-evident audit hash chain for one or all councils';

    public function handle(TenantManager $tenants, AuditLogger $audit): int
    {
        $code = $this->option('council');

        $councils = $code
            ? Council::where('code', strtoupper($code))->get()
            : Council::where('status', 'active')->get();

        if ($councils->isEmpty()) {
            $this->error('No councils found.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($councils as $council) {
            $tenants->connect($council);
            $result = $audit->verify();

            if ($result['ok']) {
                $this->info(sprintf('[%s] OK - %d events verified.', $council->code, $result['checked']));
            } else {
                $failed = true;
                $this->error(sprintf('[%s] BROKEN at chain index %s after %d events.', $council->code, $result['broken_at'], $result['checked']));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
