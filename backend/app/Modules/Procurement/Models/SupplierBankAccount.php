<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccount extends TenantModel
{
    protected $table = 'procurement_supplier_bank_accounts';

    protected $fillable = ['supplier_id', 'bank_name', 'account_name', 'account_number_encrypted', 'currency_code', 'status', 'change_history', 'submitted_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['account_number_encrypted' => 'encrypted', 'change_history' => 'array', 'approved_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
