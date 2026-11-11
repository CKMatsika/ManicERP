<?php

namespace App\Modules\Licensing\Models;

use App\Support\TenantModel;

class LicenceNotification extends TenantModel
{
    protected $fillable = ['licence_application_id', 'licence_id', 'recipient_user_id', 'dedupe_key', 'channel', 'event', 'subject', 'preview', 'status', 'payload', 'attempts', 'available_at', 'delivered_at', 'read_at', 'last_error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'available_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
