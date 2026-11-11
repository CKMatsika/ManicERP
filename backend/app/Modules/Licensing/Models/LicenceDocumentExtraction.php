<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceDocumentExtraction extends TenantModel
{
    protected $fillable = [
        'licence_application_id', 'licence_application_document_id', 'document_id', 'document_version', 'status',
        'provider', 'model', 'proposed_fields', 'field_provenance', 'reviewed_fields', 'created_by', 'reviewed_by',
        'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['document_version' => 'integer', 'proposed_fields' => 'array', 'field_provenance' => 'array', 'reviewed_fields' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenceApplication::class, 'licence_application_id');
    }

    public function applicationDocument(): BelongsTo
    {
        return $this->belongsTo(LicenceApplicationDocument::class, 'licence_application_document_id');
    }
}
