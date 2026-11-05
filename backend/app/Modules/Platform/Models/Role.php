<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUlids;

    protected $fillable = ['council_id', 'code', 'name', 'description', 'permissions', 'requires_mfa', 'is_system'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'requires_mfa' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
