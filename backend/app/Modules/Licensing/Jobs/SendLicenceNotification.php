<?php

namespace App\Modules\Licensing\Jobs;

use App\Modules\Licensing\Models\LicenceNotification;
use App\Modules\Licensing\Services\EmailLicenceNotificationAdapter;
use App\Modules\Licensing\Services\PushLicenceNotificationAdapter;
use App\Modules\Licensing\Services\SmsLicenceNotificationAdapter;
use App\Modules\Platform\Jobs\TenantJob;
use App\Modules\Platform\Models\Council;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Delivers a single queued external notice inside its owning council context. */
class SendLicenceNotification extends TenantJob
{
    protected ?string $module = 'licensing';

    public function __construct(string $councilId, public readonly string $notificationId)
    {
        parent::__construct($councilId);
    }

    protected function execute(Council $council): void
    {
        $notification = DB::connection('tenant')->transaction(function (): ?LicenceNotification {
            $notification = LicenceNotification::query()->lockForUpdate()->findOrFail($this->notificationId);
            if ($notification->channel === 'in_app' || $notification->status === 'delivered') {
                return null;
            }
            $staleAttempt = $notification->status === 'attempted' && $notification->updated_at?->lt(now()->subMinutes(5));
            if (! in_array($notification->status, ['queued', 'failed'], true) && ! $staleAttempt) {
                return null;
            }
            $adapter = match ($notification->channel) {
                'email' => app(EmailLicenceNotificationAdapter::class),
                'sms' => app(SmsLicenceNotificationAdapter::class),
                'push' => app(PushLicenceNotificationAdapter::class),
                default => null,
            };
            if (! $adapter) {
                $notification->forceFill(['status' => 'unavailable', 'last_error' => 'Unknown notification channel.'])->save();

                return null;
            }
            if (! $adapter->isAvailable()) {
                $notification->forceFill(['status' => 'unavailable', 'last_error' => $adapter->unavailableReason()])->save();

                return null;
            }
            $notification->forceFill(['status' => 'attempted', 'attempts' => $notification->attempts + 1, 'last_error' => null])->save();

            return $notification;
        });
        if (! $notification) {
            return;
        }
        $adapter = match ($notification->channel) {
            'email' => app(EmailLicenceNotificationAdapter::class),
            'sms' => app(SmsLicenceNotificationAdapter::class),
            'push' => app(PushLicenceNotificationAdapter::class),
            default => null,
        };
        if (! $adapter) {
            return;
        }
        try {
            $adapter->deliver($notification);
            $notification->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $notification->forceFill(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();
            throw $exception;
        }
    }
}
