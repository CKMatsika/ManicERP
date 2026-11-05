<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyRole extends TenantModel
{
    protected $fillable = ['party_id', 'role', 'effective_from', 'effective_to', 'meta'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'meta' => 'array'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
