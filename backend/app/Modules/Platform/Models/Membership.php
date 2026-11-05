<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasUlids;

    protected $table = 'council_memberships';

    protected $fillable = [
        'council_id', 'user_id', 'role_id', 'member_type', 'status',
        'job_title', 'started_at', 'revoked_at', 'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->revoked_at === null;
    }

    public function isStaff(): bool
    {
        return $this->member_type === 'staff';
    }

    public function isCitizen(): bool
    {
        return $this->member_type === 'citizen';
    }
}
