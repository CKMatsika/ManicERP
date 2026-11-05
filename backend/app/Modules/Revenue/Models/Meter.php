<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meter extends TenantModel
{
    protected $fillable = [
        'service_connection_id', 'serial_number', 'meter_type', 'digits',
        'installed_on', 'removed_on', 'status', 'initial_reading',
    ];

    protected function casts(): array
    {
        return ['installed_on' => 'date', 'removed_on' => 'date', 'digits' => 'integer'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ServiceConnection::class, 'service_connection_id');
    }
}
