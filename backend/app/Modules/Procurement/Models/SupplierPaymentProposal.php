<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;

class SupplierPaymentProposal extends TenantModel
{
    protected $table = 'procurement_payment_proposals';

    protected $guarded = [];
}
