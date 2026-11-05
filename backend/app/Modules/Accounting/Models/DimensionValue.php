<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DimensionValue extends TenantModel
{
    protected $fillable = ['dimension_id', 'code', 'name', 'parent_id', 'active_from', 'active_to', 'meta'];

    protected function casts(): array
    {
        return ['active_from' => 'date', 'active_to' => 'date', 'meta' => 'array'];
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }
}
