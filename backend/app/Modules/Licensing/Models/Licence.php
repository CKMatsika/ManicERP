<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Licence extends TenantModel
{
    protected $fillable = ['reference', 'licence_type_id', 'customer_account_id', 'holder_name', 'validity_status', 'valid_from', 'valid_to', 'suspended_at', 'revoked_at', 'reinstated_at', 'lifecycle_reason'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'suspended_at' => 'datetime', 'revoked_at' => 'datetime', 'reinstated_at' => 'datetime'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LicenceApplication::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(LicenceCertificate::class)->orderByDesc('version');
    }
}
