<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceFeeRelease extends TenantModel
{
    protected $fillable = [
        'licence_charge_id', 'revenue_policy_id', 'original_journal_entry_id', 'journal_entry_id', 'amount_minor',
        'trigger_event', 'event_key', 'evidence', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'released_at' => 'datetime'];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(LicenceCharge::class, 'licence_charge_id');
    }
}
