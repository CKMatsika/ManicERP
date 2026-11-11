<?php

namespace App\Modules\Land\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandParcel extends TenantModel
{
    protected $table = 'land_parcels';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'area_sqm' => 'float',
        'purchase_price_minor' => 'integer',
        'deposit_required_minor' => 'integer',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('allocation_status', 'available');
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('stand_category', $category);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HousingAllocation::class, 'land_parcel_id');
    }
}

