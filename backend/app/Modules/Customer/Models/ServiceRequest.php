<?php

namespace App\Modules\Customer\Models;

use App\Modules\Revenue\Models\CustomerAccount;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends TenantModel
{
    protected $fillable = [
        'reference', 'customer_account_id', 'party_id', 'category', 'subject',
        'description', 'status', 'priority', 'assigned_to', 'submitted_by',
        'sla_due_at', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
