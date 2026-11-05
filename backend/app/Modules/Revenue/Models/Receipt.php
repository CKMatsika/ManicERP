<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends TenantModel
{
    protected $fillable = [
        'receipt_number', 'payment_id', 'customer_account_id', 'amount_minor',
        'issued_at', 'issued_by', 'status', 'reversal_reason', 'pdf_path',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'amount_minor' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
