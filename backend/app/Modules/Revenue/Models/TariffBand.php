<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffBand extends TenantModel
{
    protected $fillable = [
        'tariff_version_id', 'band_no', 'from_units', 'to_units', 'rate_minor',
        'fixed_charge_minor', 'minimum_charge_minor', 'description',
    ];

    protected function casts(): array
    {
        return [
            'band_no' => 'integer',
            'rate_minor' => 'integer',
            'fixed_charge_minor' => 'integer',
            'minimum_charge_minor' => 'integer',
        ];
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class, 'tariff_version_id');
    }
}
