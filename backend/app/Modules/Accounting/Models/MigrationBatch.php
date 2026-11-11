<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationBatch extends TenantModel
{
    protected static function booted(): void
    {
        static::updating(function (self $batch) {
            if ($batch->getOriginal('status') === 'posted') {
                throw new \DomainException('Posted migration batches are immutable and require controlled corrections.');
            }
        });
        static::deleting(function (self $batch) {
            if (in_array($batch->status, ['approved', 'posted'], true)) {
                throw new \DomainException('Approved migration batches cannot be deleted.');
            }
        });
    }

    public const TYPE_GL = 'opening_gl';

    public const TYPE_CUSTOMER = 'opening_customer';

    protected $fillable = [
        'batch_number', 'type', 'status', 'file_name', 'file_hash', 'cutover_date',
        'currency_code', 'row_count', 'valid_count', 'error_count', 'totals',
        'control_totals', 'reconciliation', 'notes', 'uploaded_by', 'approved_by',
        'approved_at', 'posted_at', 'journal_entry_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'cutover_date' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'totals' => 'array',
            'control_totals' => 'array',
            'reconciliation' => 'array',
            'row_count' => 'integer',
            'valid_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MigrationRow::class, 'batch_id');
    }
}
