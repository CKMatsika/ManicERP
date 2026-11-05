<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends TenantModel
{
    protected $fillable = ['code', 'name', 'description', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class);
    }
}
