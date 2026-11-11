<?php

namespace App\Modules\Licensing\Services;

class EmailLicenceNotificationAdapter extends UnavailableLicenceNotificationAdapter
{
    public function unavailableReason(): string
    {
        return 'Council email delivery adapter is not configured.';
    }
}
