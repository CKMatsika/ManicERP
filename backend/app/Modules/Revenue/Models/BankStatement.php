<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends TenantModel
{
    protected $fillable = [
        'bank_account_id', 'statement_number', 'statement_date', 'opening_balance_minor',
        'closing_balance_minor', 'file_sha256', 'status', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'opening_balance_minor' => 'integer',
            'closing_balance_minor' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
