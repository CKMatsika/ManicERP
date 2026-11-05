<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class Refund extends TenantModel
{
    protected $fillable = [
        'payment_id', 'amount_minor', 'status', 'reason', 'requested_by',
        'approved_by', 'approved_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'paid_at' => 'datetime', 'amount_minor' => 'integer'];
    }
}
