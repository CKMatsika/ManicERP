<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class Requisition extends TenantModel
{
    protected $table = 'procurement_requisitions';

    protected $guarded = [];
}
