<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends TenantModel
{
    protected $fillable = [
        'bill_number', 'customer_account_id', 'billing_run_id', 'service_type', 'period_start',
        'period_end', 'issue_date', 'due_date', 'status', 'currency_code',
        'total_minor', 'paid_minor', 'balance_minor', 'calculation_snapshot',
        'journal_entry_id', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'calculation_snapshot' => 'array',
            'approved_at' => 'datetime',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'balance_minor' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid'], true) && $this->balance_minor > 0;
    }
}
