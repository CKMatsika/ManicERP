<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenceType extends TenantModel
{
    protected $fillable = ['code', 'name', 'status', 'configuration_authority', 'effective_from', 'effective_to', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LicenceTypeVersion::class)->orderByDesc('version');
    }
}
