<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class CostCentre extends TenantModel
{
    protected $fillable = ['code', 'name', 'department_id'];
}
