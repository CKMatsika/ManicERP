<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends TenantModel
{
    protected static function booted(): void
    {
        static::updating(function (self $definition) {
            if ($definition->getOriginal('status') === 'approved') {
                throw new \DomainException('Approved report versions are immutable. Create a new draft version.');
            }
        });
        static::deleting(function (self $definition) {
            if ($definition->status === 'approved') {
                throw new \DomainException('Approved report versions cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'code', 'name', 'statement_type', 'version', 'status', 'source_document_id',
        'source_ref', 'effective_from', 'effective_to', 'currency_code', 'units',
        'approved_by', 'approved_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReportLine::class)->orderBy('sort_order');
    }
}
