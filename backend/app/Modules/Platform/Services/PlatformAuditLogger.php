<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\PlatformAuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Control-plane audit trail (council provisioning, role changes, module
 * activation). Kept separate from council operational audit.
 */
class PlatformAuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        string $action,
        ?string $councilId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
    ): PlatformAuditLog {
        if ($metadata !== []) {
            $after = ($after ?: []) + ['metadata' => $metadata];
        }

        return PlatformAuditLog::create([
            'council_id' => $councilId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'request_id' => request()?->headers->get('X-Request-Id'),
        ]);
    }
}
