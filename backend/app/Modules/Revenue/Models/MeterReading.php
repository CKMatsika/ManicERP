<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends TenantModel
{
    public const STATUSES = ['captured', 'confirmed', 'billed', 'void'];

    protected $fillable = [
        'meter_id', 'service_connection_id', 'read_at', 'reading', 'previous_reading',
        'consumption', 'source', 'status', 'photo_document_id', 'gps_lat', 'gps_lng',
        'captured_by', 'abnormal_flag', 'abnormal_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'abnormal_flag' => 'boolean',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ServiceConnection::class, 'service_connection_id');
    }
}
