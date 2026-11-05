<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouncilDomain extends Model
{
    use HasUlids;

    protected $fillable = ['council_id', 'host', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }
}
