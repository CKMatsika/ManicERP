<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends TenantModel
{
    protected $fillable = [
        'payment_number', 'payment_intent_id', 'customer_account_id', 'amount_minor',
        'currency_code', 'method', 'status', 'received_at', 'verified_at', 'verified_by',
        'verification_source', 'external_reference', 'cashier_session_id', 'received_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'verified_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function verified(): bool
    {
        return $this->status === 'verified';
    }

    public function unallocatedMinor(): int
    {
        return max(0, $this->amount_minor - (int) $this->allocations()->whereNull('reversed_at')->sum('amount_minor')
            - (int) Refund::where('payment_id', $this->id)->where('status', 'settled')->sum('amount_minor'));
    }
}
