<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierSession extends TenantModel
{
    protected $fillable = [
        'session_number', 'cashier_id', 'opened_at', 'closed_at', 'status',
        'opening_float_minor', 'expected_cash_minor', 'counted_cash_minor',
        'variance_minor', 'tender_totals', 'closed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'tender_totals' => 'array',
            'opening_float_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
