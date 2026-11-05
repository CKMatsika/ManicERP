<?php

namespace App\Modules\Platform\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * Append-only audit trail with a per-council hash chain. Rows may never be
 * updated or deleted through the application.
 */
class AuditEvent extends TenantModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'chain_index', 'occurred_at', 'actor_user_id', 'actor_name', 'actor_role',
        'action', 'module', 'subject_type', 'subject_id', 'before', 'after',
        'metadata', 'ip_address', 'user_agent', 'request_id', 'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'chain_index' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new MassAssignmentException('Audit events are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new MassAssignmentException('Audit events are append-only and cannot be deleted.');
        });
    }
}
