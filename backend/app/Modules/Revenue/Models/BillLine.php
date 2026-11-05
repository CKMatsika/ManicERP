<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends TenantModel
{
    protected $fillable = [
        'bill_id', 'charge_type', 'description', 'quantity', 'unit_rate_minor',
        'amount_minor', 'tariff_version_id', 'meter_reading_id', 'dimensions',
    ];

    protected function casts(): array
    {
        return ['dimensions' => 'array', 'amount_minor' => 'integer', 'unit_rate_minor' => 'integer'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
