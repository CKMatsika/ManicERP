<?php

namespace App\Modules\Customer\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Builder;

class CivicItem extends TenantModel
{
    protected $table = 'civic_items';

    protected $guarded = [];

    protected $casts = [
        'published_date' => 'date',
        'event_date' => 'date',
        'is_featured' => 'boolean',
    ];

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}

