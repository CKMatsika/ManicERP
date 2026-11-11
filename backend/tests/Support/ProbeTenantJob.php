<?php

namespace Tests\Support;

use App\Modules\Platform\Jobs\TenantJob;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantCache;
use App\Modules\Platform\Services\TenantStorage;
use App\Modules\Revenue\Models\Party;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class ProbeTenantJob extends TenantJob
{
    public static int $runs = 0;

    protected function execute(Council $council): void
    {
        self::$runs++;

        Party::create([
            'code' => 'Q-'.$council->code.'-'.Str::upper(Str::random(4)),
            'party_type' => 'individual',
            'display_name' => 'Queued probe for '.$council->code,
            'status' => 'active',
        ]);

        app(TenantCache::class)->put('probe', $council->code);
        Context::add('probe_council', $council->code);

        app(TenantStorage::class)->put('queue/probe-'.$council->code.'.txt', $council->code);
    }
}
