<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class RevenuePolicy extends TenantModel
{
    protected static function booted(): void
    {
        static::updating(function (self $policy) {
            if ($policy->getOriginal('status') === 'approved') {
                throw new \DomainException('Approved revenue policies are immutable. Create a new effective-dated version.');
            }
        });
        static::deleting(function (self $policy) {
            if ($policy->status === 'approved') {
                throw new \DomainException('Approved revenue policies cannot be deleted.');
            }
        });
    }

    public const IMMEDIATE = 'immediate';

    public const DEFERRED = 'deferred';

    public const ADVANCE_RECEIPT = 'advance_receipt';

    public const REFUNDABLE_DEPOSIT = 'refundable_deposit';

    protected $fillable = [
        'charge_type', 'recognition_method', 'receivable_account_code',
        'revenue_account_code', 'deferred_account_code', 'source_document_id',
        'source_ref', 'effective_from', 'effective_to', 'status', 'version',
        'approved_by', 'approved_at', 'notes', 'recognition_rule', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'version' => 'integer',
            'recognition_rule' => 'array',
        ];
    }
}
