<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class ProcurementAward extends TenantModel
{
    protected $table = 'procurement_awards';

    protected $guarded = [];
}
