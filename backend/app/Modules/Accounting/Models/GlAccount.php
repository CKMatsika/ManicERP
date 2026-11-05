<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends TenantModel
{
    public const CLASSES = ['asset', 'liability', 'net_asset', 'revenue', 'expense'];

    public const TYPES = ['head', 'subhead', 'posting', 'control'];

    protected $fillable = [
        'chart_version_id', 'code', 'name', 'account_class', 'account_type',
        'parent_id', 'normal_balance', 'is_control', 'is_bank', 'is_cash',
        'active_from', 'active_to', 'description', 'gfsm_code', 'source_code',
    ];

    protected function casts(): array
    {
        return [
            'is_control' => 'boolean',
            'is_bank' => 'boolean',
            'is_cash' => 'boolean',
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    public function chartVersion(): BelongsTo
    {
        return $this->belongsTo(ChartVersion::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isPostable(): bool
    {
        return in_array($this->account_type, ['posting', 'control'], true);
    }

    public function isActiveOn(string $date): bool
    {
        return ($this->active_from === null || $this->active_from->toDateString() <= $date)
            && ($this->active_to === null || $this->active_to->toDateString() >= $date);
    }
}
