<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLiability extends TenantModel
{
    protected $fillable = ['customer_account_id', 'party_id', 'liability_type', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
