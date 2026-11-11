<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoresTransaction extends TenantModel
{
    protected $fillable = [
        'transaction_number',
        'type',
        'stores_item_id',
        'quantity',
        'unit_cost_minor',
        'total_cost_minor',
        'department_recipient',
        'work_order_ref',
        'authorized_by',
        'issued_by',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost_minor' => 'integer',
        'total_cost_minor' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoresItem::class, 'stores_item_id');
    }
}

