<?php

namespace App\Models;

use App\Modules\Platform\Models\Membership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'status', 'is_platform_admin', 'mfa_secret', 'mfa_confirmed_at', 'mfa_recovery_codes', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'mfa_recovery_codes' => 'array',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active')->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->locked_until === null || $this->locked_until->isPast());
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_secret !== null && $this->mfa_confirmed_at !== null;
    }
}
