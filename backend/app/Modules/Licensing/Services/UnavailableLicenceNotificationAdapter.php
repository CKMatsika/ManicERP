<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Contracts\LicenceNotificationAdapter;
use App\Modules\Licensing\Models\LicenceNotification;

abstract class UnavailableLicenceNotificationAdapter implements LicenceNotificationAdapter
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function deliver(LicenceNotification $notification): void
    {
        throw new \RuntimeException($this->unavailableReason());
    }
}
