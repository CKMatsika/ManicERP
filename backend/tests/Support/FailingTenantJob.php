<?php

namespace Tests\Support;

use App\Modules\Platform\Jobs\TenantJob;
use App\Modules\Platform\Models\Council;

class FailingTenantJob extends TenantJob
{
    public static int $runs = 0;

    protected function execute(Council $council): void
    {
        self::$runs++;

        throw new \RuntimeException('Probe failure for '.$council->code);
    }
}
