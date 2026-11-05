<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class ChartImportRow extends TenantModel
{
    protected $fillable = [
        'batch_id', 'row_number', 'code', 'name', 'account_class', 'account_type',
        'parent_code', 'normal_balance', 'is_control', 'active_from', 'active_to',
        'description', 'gfsm_code', 'errors', 'status',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'is_control' => 'boolean',
            'active_from' => 'date',
            'active_to' => 'date',
            'row_number' => 'integer',
        ];
    }
}
