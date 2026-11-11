<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterMeterReading extends TenantModel
{
    protected $fillable = [
        'water_meter_id',
        'reading_period',
        'previous_reading',
        'current_reading',
        'consumption',
        'reader_user_id',
        'reading_source',
        'gps_lat',
        'gps_lng',
        'gps_distance_meters',
        'anomaly_flag',
        'billed_status',
        'bill_id',
        'notes',
    ];

    protected $casts = [
        'previous_reading' => 'decimal:3',
        'current_reading' => 'decimal:3',
        'consumption' => 'decimal:3',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'gps_distance_meters' => 'integer',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(WaterMeter::class, 'water_meter_id');
    }
}

