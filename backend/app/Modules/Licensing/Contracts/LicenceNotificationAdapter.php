<?php

namespace App\Modules\Licensing\Contracts;

use App\Modules\Licensing\Models\LicenceNotification;

interface LicenceNotificationAdapter
{
    public function isAvailable(): bool;

    public function unavailableReason(): string;

    public function deliver(LicenceNotification $notification): void;
}
