<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPeriod extends TenantModel
{
    protected $fillable = [
        'financial_year_id', 'sequence', 'code', 'start_date', 'end_date',
        'status', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'locked_at' => 'datetime', 'sequence' => 'integer'];
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
