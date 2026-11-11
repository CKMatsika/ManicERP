<?php

namespace App\Modules\Land\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Builder;

class HousingWaitingListEntry extends TenantModel
{
    protected $table = 'housing_waiting_list_entries';

    protected $guarded = [];

    protected $casts = [
        'preferred_suburbs' => 'array',
        'application_date' => 'date',
        'expiry_date' => 'date',
        'last_renewed_at' => 'datetime',
        'number_of_dependents' => 'integer',
        'monthly_income_minor' => 'integer',
        'priority_points' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isRenewalDue(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now(), false) >= -30;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}

