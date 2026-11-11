<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class ProcurementQuotation extends TenantModel
{
    protected $table = 'procurement_quotations';

    protected $guarded = [];
}
