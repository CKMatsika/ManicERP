<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class AccountingStandard extends TenantModel
{
    protected $fillable = [
        'code', 'title', 'edition', 'issued_on', 'international_effective_from',
        'jurisdiction', 'applicability', 'transition_method', 'policy_choices',
        'source_document', 'source_url', 'verification_status', 'verified_by',
        'verified_at', 'approver', 'approved_at', 'affected_workflows', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'international_effective_from' => 'date',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'policy_choices' => 'array',
            'affected_workflows' => 'array',
        ];
    }
}
