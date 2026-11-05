<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TariffVersion extends TenantModel
{
    protected $fillable = [
        'code', 'name', 'service_type', 'charge_basis', 'status', 'effective_from',
        'effective_to', 'currency_code', 'version_no', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'version_no' => 'integer',
        ];
    }

    public function bands(): HasMany
    {
        return $this->hasMany(TariffBand::class)->orderBy('band_no');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
