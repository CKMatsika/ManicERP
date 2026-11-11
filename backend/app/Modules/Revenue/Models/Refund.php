<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends TenantModel
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SETTLING = 'settling';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'refund_number', 'customer_account_id', 'payment_id', 'type', 'amount_minor',
        'approved_amount_minor', 'settled_amount_minor', 'status', 'reason',
        'requested_by', 'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at',
        'settled_at', 'paid_at', 'sandbox_reference', 'journal_entry_id',
        'idempotency_key', 'customer_note', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'settled_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'settled_amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }
}
