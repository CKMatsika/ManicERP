<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceConnection extends TenantModel
{
    protected $fillable = [
        'customer_account_id', 'property_id', 'connection_number', 'service_type',
        'status', 'installed_on', 'disconnected_on', 'route_code',
    ];

    protected function casts(): array
    {
        return ['installed_on' => 'date', 'disconnected_on' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function activeMeter(): ?Meter
    {
        return $this->meters()->where('status', 'active')->latest('installed_on')->first();
    }
}
