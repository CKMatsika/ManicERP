<?php

namespace App\Modules\Revenue\Models;

use App\Modules\Procurement\Models\SupplierPayment;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends TenantModel
{
    protected $fillable = [
        'bank_statement_id', 'line_number', 'value_date', 'amount_minor', 'reference',
        'narrative', 'status', 'matched_payment_id', 'match_confidence',
        'matched_at', 'matched_by',
        'matched_supplier_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'amount_minor' => 'integer',
            'matched_at' => 'datetime',
            'match_confidence' => 'decimal:2',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'matched_supplier_payment_id');
    }
}
