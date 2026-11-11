<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportLine extends TenantModel
{
    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if ($line->definition?->status === 'approved') {
                throw new \DomainException('Approved report lines are immutable. Create a new draft version.');
            }
        });
        static::deleting(function (self $line) {
            if ($line->definition?->status === 'approved') {
                throw new \DomainException('Approved report lines cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'report_definition_id', 'line_code', 'label', 'section', 'line_type',
        'sign', 'sort_order', 'is_comparative', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_comparative' => 'boolean', 'sort_order' => 'integer'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ReportLineMapping::class);
    }
}
