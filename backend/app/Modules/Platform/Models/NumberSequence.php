<?php

namespace App\Modules\Platform\Models;

use App\Support\TenantModel;

class NumberSequence extends TenantModel
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['key', 'prefix', 'next_value', 'padding', 'period_key', 'updated_at'];

    protected function casts(): array
    {
        return ['next_value' => 'integer', 'padding' => 'integer'];
    }
}
