<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenceApplication extends TenantModel
{
    protected $fillable = ['reference', 'licence_id', 'licence_type_id', 'licence_type_version_id', 'customer_account_id', 'submitted_by', 'attempt_type', 'status', 'idempotency_key', 'configuration_snapshot', 'form_data', 'staff_draft_fields', 'holder_name', 'fee_currency', 'fee_amount_minor', 'fee_bill_id', 'fee_cancelled_at', 'fee_cancelled_by', 'fee_cancellation_reason', 'fee_credit_note_journal_id', 'submitted_at', 'reviewed_by', 'decided_at', 'decided_by', 'decision_reason', 'conditions'];

    protected function casts(): array
    {
        return ['configuration_snapshot' => 'array', 'form_data' => 'array', 'staff_draft_fields' => 'array', 'conditions' => 'array', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'fee_cancelled_at' => 'datetime', 'fee_amount_minor' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LicenceApplicationDocument::class)->orderBy('document_type')->orderByDesc('version');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(LicenceInspection::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LicenceCharge::class, 'licence_application_id')->orderBy('created_at');
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(LicenceDocumentExtraction::class, 'licence_application_id')->latest();
    }

    public function assistantDrafts(): HasMany
    {
        return $this->hasMany(LicenceAssistantDraft::class, 'licence_application_id')->latest();
    }
}
