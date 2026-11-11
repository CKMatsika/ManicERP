<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class ProcurementReceipt extends TenantModel
{
    protected $table = 'procurement_receipts';

    protected $guarded = [];
}
