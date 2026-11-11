<?php

namespace App\Modules\Licensing\Services;

class SmsLicenceNotificationAdapter extends UnavailableLicenceNotificationAdapter
{
    public function unavailableReason(): string
    {
        return 'Council SMS delivery adapter is not configured.';
    }
}
