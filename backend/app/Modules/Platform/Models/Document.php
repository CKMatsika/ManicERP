<?php

namespace App\Modules\Platform\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends TenantModel
{
    protected $fillable = [
        'original_name', 'path', 'disk', 'mime_type', 'extension', 'size_bytes',
        'sha256', 'classification', 'scan_status', 'status', 'documentable_type',
        'documentable_id', 'uploaded_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
