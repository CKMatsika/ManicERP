<?php

namespace App\Modules\Customer\Models;

use App\Modules\Revenue\Models\CustomerAccount;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAccountLink extends TenantModel
{
    protected $fillable = [
        'user_id', 'customer_account_id', 'relationship', 'status', 'verification_method',
        'requested_at', 'verified_at', 'verified_by', 'revoked_at', 'revoked_reason',
        'evidence_document_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'verified_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'verified' && $this->revoked_at === null;
    }
}
