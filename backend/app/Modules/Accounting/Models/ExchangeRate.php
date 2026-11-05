<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class ExchangeRate extends TenantModel
{
    protected $fillable = ['from_code', 'to_code', 'rate', 'effective_date', 'source', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'approved_at' => 'datetime'];
    }
}
