<?php

namespace App\Modules\Licensing\Models;

use App\Modules\Revenue\Models\Bill;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenceCharge extends TenantModel
{
    protected $fillable = [
        'licence_application_id', 'licence_type_version_id', 'bill_id', 'revenue_policy_id', 'stage', 'trigger_event',
        'event_key', 'amount_minor', 'currency_code', 'payment_prerequisite', 'payment_required_before', 'refund_policy', 'accounting_treatment',
        'recognition_method', 'status', 'waived_by', 'waived_at', 'waiver_reason', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'released_minor',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'payment_prerequisite' => 'boolean', 'refund_policy' => 'array',
            'accounting_treatment' => 'array', 'waived_at' => 'datetime', 'released_minor' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenceApplication::class, 'licence_application_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(LicenceFeeRelease::class, 'licence_charge_id')->orderBy('released_at');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }
}
