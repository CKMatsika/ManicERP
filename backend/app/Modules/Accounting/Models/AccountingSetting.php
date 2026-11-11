<?php

namespace App\Modules\Accounting\Models;

use App\Support\TenantModel;

class AccountingSetting extends TenantModel
{
    protected $fillable = ['key', 'value', 'set_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['value' => 'array', 'approved_at' => 'datetime'];
    }

    public static function adoption(): ?self
    {
        return static::where('key', 'adoption')->first();
    }

    public static function cutoverDate(): ?string
    {
        return static::adoption()?->value['migration_cutover_date'] ?? null;
    }
}
