<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends TenantModel
{
    public const TYPES = ['individual', 'organisation'];

    protected $fillable = [
        'code', 'party_type', 'display_name', 'first_name', 'last_name',
        'registration_number', 'tax_number', 'national_id', 'status', 'created_by',
    ];

    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContactPoint::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class, 'primary_party_id');
    }

    public function isOrganisation(): bool
    {
        return $this->party_type === 'organisation';
    }
}
