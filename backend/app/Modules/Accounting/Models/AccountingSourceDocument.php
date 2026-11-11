<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSourceDocument extends TenantModel
{
    protected $fillable = [
        'internal_title', 'file_name', 'storage_path', 'document_type', 'issuing_authority',
        'edition', 'document_date', 'page_count', 'file_hash', 'received_at',
        'examination_status', 'currency_status', 'metadata', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'metadata' => 'array',
            'page_count' => 'integer',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(AccountingRequirement::class, 'source_document_id');
    }

    public function isExamined(): bool
    {
        return $this->examination_status === 'examined';
    }
}
