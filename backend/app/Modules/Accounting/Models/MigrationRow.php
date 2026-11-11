<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationRow extends TenantModel
{
    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if ($row->batch?->status === 'posted') {
                throw new \DomainException('Posted opening rows require controlled corrections.');
            }
        });
        static::deleting(function (self $row) {
            if (in_array($row->batch?->status, ['approved', 'posted'], true)) {
                throw new \DomainException('Approved opening rows cannot be deleted.');
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class, 'batch_id');
    }

    protected $fillable = [
        'batch_id', 'row_number', 'row_type', 'account_code', 'account_number',
        'reference', 'description', 'debit_minor', 'credit_minor', 'amount_minor',
        'due_date', 'dimensions', 'data', 'errors', 'status', 'bill_id', 'credit_id',
    ];

    protected function casts(): array
    {
        return [
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'amount_minor' => 'integer',
            'due_date' => 'date',
            'dimensions' => 'array',
            'data' => 'array',
            'errors' => 'array',
        ];
    }
}
