<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPlanInstallment extends TenantModel
{
    protected $table = 'payment_plan_installments';

    protected $guarded = [];

    protected $casts = [
        'installment_number' => 'integer',
        'amount_minor' => 'integer',
        'paid_amount_minor' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }
}

