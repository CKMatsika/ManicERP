<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterMeter extends TenantModel
{
    protected $fillable = [
        'meter_number',
        'serial_number',
        'brand_model',
        'meter_size',
        'customer_account_id',
        'property_stand',
        'suburb',
        'location_address',
        'gps_lat',
        'gps_lng',
        'initial_reading',
        'last_reading',
        'last_reading_date',
        'average_monthly_consumption',
        'tariff_category',
        'status',
    ];

    protected $casts = [
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'initial_reading' => 'decimal:3',
        'last_reading' => 'decimal:3',
        'average_monthly_consumption' => 'decimal:3',
        'last_reading_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WaterMeterReading::class);
    }
}

