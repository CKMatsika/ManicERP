<?php

namespace App\Modules\Customer\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLinkVerification extends TenantModel
{
    protected $fillable = [
        'portal_account_link_id', 'channel', 'code_hash', 'attempts',
        'expires_at', 'consumed_at', 'sent_to_masked',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(PortalAccountLink::class, 'portal_account_link_id');
    }
}
