<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyValuation extends TenantModel
{
    protected $fillable = [
        'property_id', 'valuation_date', 'land_value_minor', 'improvements_value_minor',
        'total_value_minor', 'valuer', 'roll_reference', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'valuation_date' => 'date',
            'approved_at' => 'datetime',
            'land_value_minor' => 'integer',
            'improvements_value_minor' => 'integer',
            'total_value_minor' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
