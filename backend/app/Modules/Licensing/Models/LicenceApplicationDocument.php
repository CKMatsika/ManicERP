<?php

namespace App\Modules\Licensing\Models;

use App\Modules\Platform\Models\Document;
use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenceApplicationDocument extends TenantModel
{
    protected $fillable = ['licence_application_id', 'document_id', 'document_type', 'version', 'supersedes_id', 'uploaded_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
