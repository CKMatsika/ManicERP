<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;

class BankAccount extends TenantModel
{
    protected $fillable = [
        'code', 'name', 'bank_name', 'account_number_masked', 'gl_account_code',
        'currency_code', 'status',
    ];
}
