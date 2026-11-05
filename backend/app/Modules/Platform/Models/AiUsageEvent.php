<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'council_id', 'user_id', 'feature', 'provider', 'model',
        'input_tokens', 'output_tokens', 'cost_minor', 'status', 'error_code', 'meta', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
            'cost_minor' => 'integer',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }
}
