<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends TenantModel
{
    protected $fillable = [
        'reference', 'customer_account_id', 'amount_minor', 'currency_code',
        'channel', 'gateway', 'status', 'idempotency_key', 'metadata', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'expires_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
