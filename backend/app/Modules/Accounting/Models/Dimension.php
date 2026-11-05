<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class Dimension extends TenantModel
{
    protected $fillable = ['code', 'name', 'description', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }
}
