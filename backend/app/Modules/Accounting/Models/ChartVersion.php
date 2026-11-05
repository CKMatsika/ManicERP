<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartVersion extends TenantModel
{
    protected $fillable = [
        'name', 'version_no', 'status', 'source_type', 'source_authority',
        'source_document', 'source_hash', 'effective_from', 'effective_to',
        'production_blocker', 'notes', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'production_blocker' => 'boolean',
            'version_no' => 'integer',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(GlAccount::class);
    }

    public function isOfficial(): bool
    {
        return $this->source_type === 'official';
    }
}
