<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class BankReconciliation extends TenantModel
{
    protected $fillable = [
        'bank_account_id', 'bank_statement_id', 'period_start', 'period_end', 'status',
        'statement_closing_minor', 'cashbook_balance_minor', 'variance_minor',
        'notes', 'reconciled_at', 'reconciled_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'reconciled_at' => 'datetime',
            'statement_closing_minor' => 'integer',
            'cashbook_balance_minor' => 'integer',
            'variance_minor' => 'integer',
        ];
    }
}
