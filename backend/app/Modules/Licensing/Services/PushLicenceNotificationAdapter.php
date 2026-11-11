<?php

namespace App\Modules\Licensing\Services;

class PushLicenceNotificationAdapter extends UnavailableLicenceNotificationAdapter
{
    public function unavailableReason(): string
    {
        return 'Council push delivery adapter is not configured.';
    }
}
