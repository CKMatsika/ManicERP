<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Revenue\Models\Party;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends TenantModel
{
    protected $table = 'procurement_suppliers';

    protected $fillable = ['party_id', 'supplier_number', 'status', 'tax_identifier', 'verification', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['verification' => 'array', 'approved_at' => 'datetime'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class, 'supplier_id');
    }
}
