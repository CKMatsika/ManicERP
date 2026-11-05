<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartImportBatch extends TenantModel
{
    protected $fillable = [
        'chart_version_id', 'file_name', 'file_sha256', 'format', 'status',
        'row_count', 'valid_count', 'error_count', 'preview', 'mapping', 'notes',
        'uploaded_by', 'validated_by', 'validated_at', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'preview' => 'array',
            'mapping' => 'array',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'row_count' => 'integer',
            'valid_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ChartImportRow::class, 'batch_id');
    }
}
