<?php

namespace App\Modules\Customer\Models;

use App\Support\TenantModel;

class NotificationPreference extends TenantModel
{
    protected $fillable = ['user_id', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
