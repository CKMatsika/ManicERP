<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Council extends Model
{
    use HasUlids;

    protected $fillable = [
        'code', 'name', 'type', 'status', 'database_name', 'currency_code',
        'timezone', 'fiscal_year_start_month', 'is_demo', 'settings', 'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
            'provisioned_at' => 'datetime',
            'fiscal_year_start_month' => 'integer',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(CouncilDomain::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CouncilModule::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
