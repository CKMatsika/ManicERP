<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends TenantModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'entry_number', 'journal_type', 'source_type', 'source_id', 'idempotency_key',
        'entry_date', 'posting_date', 'period_id', 'currency_code', 'exchange_rate',
        'description', 'status', 'total_debit_minor', 'total_credit_minor',
        'posted_at', 'posted_by', 'created_by', 'reversal_of_id', 'reversed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posting_date' => 'date',
            'posted_at' => 'datetime',
            'total_debit_minor' => 'integer',
            'total_credit_minor' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isBalanced(): bool
    {
        return $this->total_debit_minor === $this->total_credit_minor;
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry) {
            if ($entry->getOriginal('status') === self::STATUS_POSTED) {
                $dirty = array_keys($entry->getDirty());
                $allowed = ['reversed_by_id', 'reversal_of_id', 'updated_at'];

                if (array_diff($dirty, $allowed) !== []) {
                    throw new \DomainException('Posted journal entries are immutable. Use a linked reversal.');
                }
            }
        });

        static::deleting(function (JournalEntry $entry) {
            if ($entry->status === self::STATUS_POSTED) {
                throw new \DomainException('Posted journal entries cannot be deleted. Use a linked reversal.');
            }
        });
    }
}
