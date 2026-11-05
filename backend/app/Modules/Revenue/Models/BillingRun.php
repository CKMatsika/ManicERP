<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingRun extends TenantModel
{
    protected $fillable = [
        'run_number', 'service_type', 'period_start', 'period_end', 'status',
        'idempotency_key', 'control_totals', 'notes', 'created_by',
        'approved_by', 'approved_at', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'control_totals' => 'array',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingRunItem::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
