<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoresItem extends TenantModel
{
    protected $fillable = [
        'item_code',
        'name',
        'category',
        'unit_of_measure',
        'reorder_level',
        'reorder_quantity',
        'quantity_on_hand',
        'average_unit_cost_minor',
        'total_valuation_minor',
        'bin_location',
        'description',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'average_unit_cost_minor' => 'integer',
        'total_valuation_minor' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(StoresTransaction::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity_on_hand <= (float) $this->reorder_level;
    }
}

