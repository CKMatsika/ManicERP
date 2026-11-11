<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class SupplierPayment extends TenantModel
{
    protected $table = 'procurement_supplier_payments';

    protected $guarded = [];
}
