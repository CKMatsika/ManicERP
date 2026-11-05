<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends TenantModel
{
    protected $fillable = [
        'payment_id', 'bill_id', 'amount_minor', 'allocated_at',
        'allocated_by', 'reversed_at', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return ['allocated_at' => 'datetime', 'reversed_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
