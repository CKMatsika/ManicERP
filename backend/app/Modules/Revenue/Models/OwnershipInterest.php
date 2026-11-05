<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipInterest extends TenantModel
{
    protected $fillable = [
        'property_id', 'party_id', 'interest_type', 'share_pct', 'effective_from',
        'effective_to', 'evidence_document_id', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
