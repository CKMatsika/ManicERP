<?php

namespace App\Modules\Land\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousingAllocation extends TenantModel
{
    protected $table = 'housing_allocations';

    protected $guarded = [];

    protected $casts = [
        'area_sqm' => 'float',
        'purchase_price_minor' => 'integer',
        'deposit_required_minor' => 'integer',
        'deposit_paid_minor' => 'integer',
        'development_covenants' => 'array',
        'allocated_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function waitingListEntry(): BelongsTo
    {
        return $this->belongsTo(HousingWaitingListEntry::class, 'waiting_list_entry_id');
    }

    public function landParcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class, 'land_parcel_id');
    }

    public function scopeActiveOffer(Builder $query): Builder
    {
        return $query->whereIn('status', ['offer_issued', 'accepted']);
    }

    public function isExpired(): bool
    {
        return $this->status === 'offer_issued' && $this->offer_expires_at->isPast();
    }
}

