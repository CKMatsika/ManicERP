<?php

namespace App\Modules\Revenue\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPoint extends TenantModel
{
    protected $fillable = ['party_id', 'type', 'value', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
