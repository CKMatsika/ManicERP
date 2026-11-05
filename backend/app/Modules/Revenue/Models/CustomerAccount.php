<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAccount extends TenantModel
{
    public const TYPES = ['rates', 'water', 'refuse', 'rental', 'market', 'licence', 'combined'];

    protected $fillable = [
        'account_number', 'property_id', 'primary_party_id', 'account_type', 'status',
        'opened_on', 'closed_on', 'currency_code', 'billing_day', 'created_by',
    ];

    protected function casts(): array
    {
        return ['opened_on' => 'date', 'closed_on' => 'date', 'billing_day' => 'integer'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function primaryParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'primary_party_id');
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(AccountLiability::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ServiceConnection::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(AccountTariff::class);
    }

    public function outstandingBalanceMinor(): int
    {
        return (int) $this->bills()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->sum('balance_minor');
    }
}
