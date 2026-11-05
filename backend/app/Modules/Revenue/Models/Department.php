<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class Department extends TenantModel
{
    protected $fillable = ['code', 'name'];
}
