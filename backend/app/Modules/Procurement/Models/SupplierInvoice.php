<?php

namespace App\Modules\Procurement\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends TenantModel
{
    protected $table = 'procurement_supplier_invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'total_minor' => 'integer', 'credited_minor' => 'integer', 'paid_minor' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
