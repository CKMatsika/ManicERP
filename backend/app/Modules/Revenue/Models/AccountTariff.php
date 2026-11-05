<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTariff extends TenantModel
{
    protected $fillable = ['customer_account_id', 'service_type', 'tariff_version_id', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class, 'tariff_version_id');
    }
}
