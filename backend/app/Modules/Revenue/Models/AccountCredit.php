<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountCredit extends TenantModel
{
    protected $fillable = [
        'customer_account_id', 'type', 'source', 'migration_batch_id', 'amount_minor',
        'remaining_minor', 'currency_code', 'reference', 'status', 'effective_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'remaining_minor' => 'integer',
            'effective_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
