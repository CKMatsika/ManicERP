<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'platform_audit_events';

    protected $fillable = [
        'council_id', 'actor_user_id', 'action', 'subject_type', 'subject_id',
        'before', 'after', 'ip_address', 'user_agent', 'request_id',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }
}
