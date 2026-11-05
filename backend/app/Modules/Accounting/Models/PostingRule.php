<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class PostingRule extends TenantModel
{
    protected $fillable = [
        'event_code', 'name', 'description', 'debit_account_code', 'credit_account_code',
        'required_dimensions', 'dimensions', 'status', 'version', 'effective_from',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'required_dimensions' => 'array',
            'dimensions' => 'array',
            'effective_from' => 'date',
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
