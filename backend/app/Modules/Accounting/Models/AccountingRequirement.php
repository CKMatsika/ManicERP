<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRequirement extends TenantModel
{
    protected $fillable = [
        'source_document_id', 'requirement_code', 'title', 'section_ref', 'page_ref',
        'requirement_text', 'category', 'jurisdiction', 'applicability_status',
        'applicability_rationale', 'requirement_extracted', 'applicability_verified',
        'policy_approved', 'implementation_tested', 'implementation_status',
        'affected_workflows', 'blocker', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'requirement_extracted' => 'boolean',
            'applicability_verified' => 'boolean',
            'policy_approved' => 'boolean',
            'implementation_tested' => 'boolean',
            'affected_workflows' => 'array',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingSourceDocument::class, 'source_document_id');
    }

    /**
     * Five independent states are reported, never a single "verified" flag.
     *
     * @return array<string, bool>
     */
    public function states(): array
    {
        return [
            'source_received' => $this->sourceDocument?->examination_status === 'examined',
            'requirement_extracted' => $this->requirement_extracted,
            'applicability_verified' => $this->applicability_verified,
            'policy_approved' => $this->policy_approved,
            'implementation_tested' => $this->implementation_tested,
        ];
    }
}
