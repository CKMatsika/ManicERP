<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouncilModule extends Model
{
    use HasUlids;

    protected $fillable = ['council_id', 'module_id', 'status', 'settings', 'enabled_at', 'enabled_by'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'enabled_at' => 'datetime',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled';
    }
}
