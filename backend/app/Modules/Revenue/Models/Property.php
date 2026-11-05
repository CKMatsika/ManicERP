<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends TenantModel
{
    protected $fillable = [
        'parcel_number', 'stand_number', 'ward_id', 'address_line', 'suburb',
        'gps_lat', 'gps_lng', 'area_sqm', 'land_use', 'status', 'created_by',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(PropertyValuation::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(OwnershipInterest::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class);
    }

    public function approvedValuation(): ?PropertyValuation
    {
        return $this->valuations()
            ->where('status', 'approved')
            ->orderByDesc('valuation_date')
            ->first();
    }
}
