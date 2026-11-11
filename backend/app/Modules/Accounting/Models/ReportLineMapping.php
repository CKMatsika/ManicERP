<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLineMapping extends TenantModel
{
    protected static function booted(): void
    {
        static::saving(function (self $mapping) {
            if ($mapping->getOriginal('status') === 'approved' || $mapping->line?->definition?->status === 'approved') {
                throw new \DomainException('Approved mappings are immutable. Create a new report version.');
            }
        });
        static::deleting(function (self $mapping) {
            if ($mapping->status === 'approved') {
                throw new \DomainException('Approved mappings cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'report_line_id', 'gl_account_code', 'account_class', 'dimension_filters',
        'effective_from', 'effective_to', 'status', 'approved_by', 'approved_at', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dimension_filters' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(ReportLine::class, 'report_line_id');
    }
}
