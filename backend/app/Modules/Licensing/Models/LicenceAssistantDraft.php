<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceAssistantDraft extends TenantModel
{
    protected $fillable = [
        'licence_application_id', 'draft_type', 'content', 'source_refs', 'status', 'created_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['source_refs' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LicenceApplication::class, 'licence_application_id');
    }
}
