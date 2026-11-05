<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRunItem extends TenantModel
{
    protected $fillable = [
        'billing_run_id', 'customer_account_id', 'bill_id', 'amount_minor',
        'consumption', 'status', 'error_message', 'charge_type', 'tariff_version_id', 'calculation',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'calculation' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BillingRun::class, 'billing_run_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
