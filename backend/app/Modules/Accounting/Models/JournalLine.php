<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends TenantModel
{
    protected $fillable = [
        'journal_entry_id', 'line_no', 'gl_account_id', 'debit_minor',
        'credit_minor', 'dimensions', 'memo', 'party_id',
    ];

    protected function casts(): array
    {
        return [
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'dimensions' => 'array',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }
}
