<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\Log;

class TenantLogger
{
    public function write(string $level, string $message, array $context = []): void
    {
        $council = app(TenantManager::class)->requireCouncil();
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $council->code)) {
            throw new \DomainException('Unsafe council log identifier.');
        }
        Log::build(['driver' => 'single', 'path' => storage_path('logs/councils/'.$council->code.'.log')])
            ->log($level, $message, [...$context, 'council_id' => $council->id, 'council' => $council->code]);
    }
}
