<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends TenantModel
{
    protected $table = 'payment_plans';

    protected $guarded = [];

    protected $casts = [
        'total_arrears_minor' => 'integer',
        'interest_waived_minor' => 'integer',
        'net_payable_minor' => 'integer',
        'down_payment_minor' => 'integer',
        'monthly_installment_minor' => 'integer',
        'installment_count' => 'integer',
        'paid_installments' => 'integer',
        'total_paid_minor' => 'integer',
        'disconnection_immunity' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class, 'payment_plan_id')->orderBy('installment_number');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function nextPendingInstallment(): ?PaymentPlanInstallment
    {
        return $this->installments()->where('status', 'pending')->orderBy('installment_number')->first();
    }
}

