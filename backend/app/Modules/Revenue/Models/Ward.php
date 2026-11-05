<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class Ward extends TenantModel
{
    protected $fillable = ['code', 'name', 'suburb', 'population'];
}
