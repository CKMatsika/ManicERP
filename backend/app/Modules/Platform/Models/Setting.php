<?php

namespace App\Modules\Platform\Models;

use App\Support\TenantModel;

class Setting extends TenantModel
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'group', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
