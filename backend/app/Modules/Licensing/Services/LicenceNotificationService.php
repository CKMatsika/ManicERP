<?php

namespace App\Modules\Licensing\Services;

use App\Models\User;
use App\Modules\Licensing\Jobs\SendLicenceNotification;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceNotification;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;

/** Tenant-scoped in-app notices. External channel adapters remain unavailable until configured. */
class LicenceNotificationService
{
    public function __construct(private readonly TenantManager $tenants, private readonly PermissionService $permissions) {}

    public function application(LicenceApplication $application, string $event, ?Licence $licence = null): void
    {
        $recipients = collect([$application->submitted_by]);
        $council = $this->tenants->requireCouncil();
        foreach ($council->memberships()->with('user')->where('status', 'active')->get() as $membership) {
            $user = $membership->user;
            if ($user && ($this->permissions->allows($user, 'licensing.applications.view') || $this->permissions->allows($user, 'licensing.inspections.manage'))) {
                $recipients->push($user->getKey());
            }
        }
        $recipients->filter()->unique()->each(fn (string $userId) => $this->store(
            $application,
            $licence,
            $userId,
            $event.':'.$application->id.':'.$userId.':'.($application->updated_at?->timestamp ?? now()->timestamp),
            $event,
        ));
    }

    public function licence(Licence $licence, ?LicenceApplication $application, string $event, string $dedupeKey): void
    {
        if ($application) {
            $this->application($application, $event, $licence);

            return;
        }
        $this->store(null, $licence, null, $dedupeKey, $event);
    }

    /** @return array<int, array<string, mixed>> */
    public function inbox(User $user): array
    {
        return LicenceNotification::where('recipient_user_id', $user->getKey())->where('channel', 'in_app')->latest()->limit(100)->get()
            ->map(fn (LicenceNotification $notification): array => [
                'id' => $notification->id, 'event' => $notification->event, 'subject' => $notification->subject,
                'preview' => $notification->preview, 'channel' => $notification->channel,
                'status' => $notification->status, 'created_at' => $notification->created_at?->toIso8601String(),
                'read_at' => $notification->read_at?->toIso8601String(),
            ])->all();
    }

    public function markRead(User $user, string $notificationId): LicenceNotification
    {
        $notification = LicenceNotification::where('recipient_user_id', $user->getKey())->findOrFail($notificationId);
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    private function store(?LicenceApplication $application, ?Licence $licence, ?string $recipientId, string $dedupeKey, string $event): void
    {
        $labels = [
            'application_submitted' => ['Application received', 'A licensing application update is available.'],
            'information_requested' => ['Information requested', 'The council requested a response on an application.'],
            'decision_approved' => ['Application approved', 'A licensing decision is available.'],
            'decision_rejected' => ['Application decision', 'A licensing decision is available.'],
            'certificate_issued' => ['Certificate issued', 'A licence certificate is available.'],
            'licence_expired' => ['Licence expired', 'A licence status update is available.'],
        ];
        [$subject, $preview] = $labels[$event] ?? ['Licensing update', 'A licensing update is available in your council account.'];
        LicenceNotification::firstOrCreate(['dedupe_key' => $dedupeKey.':in_app'], [
            'licence_application_id' => $application?->id, 'licence_id' => $licence?->id,
            'recipient_user_id' => $recipientId, 'channel' => 'in_app', 'event' => $event,
            'subject' => $subject, 'preview' => $preview, 'status' => 'delivered',
            'payload' => ['delivery' => 'in_app', 'external_channels' => ['email' => 'unavailable', 'sms' => 'unavailable', 'push' => 'unavailable']],
            'attempts' => 1, 'available_at' => now(), 'delivered_at' => now(),
        ]);
        foreach (['email' => EmailLicenceNotificationAdapter::class, 'sms' => SmsLicenceNotificationAdapter::class, 'push' => PushLicenceNotificationAdapter::class] as $channel => $adapterClass) {
            $adapter = app($adapterClass);
            $available = $adapter->isAvailable();
            $external = LicenceNotification::firstOrCreate(['dedupe_key' => $dedupeKey.':'.$channel], [
                'licence_application_id' => $application?->id, 'licence_id' => $licence?->id,
                'recipient_user_id' => $recipientId, 'channel' => $channel, 'event' => $event,
                'subject' => $subject, 'preview' => $preview, 'status' => $available ? 'queued' : 'unavailable',
                'payload' => ['delivery' => $available ? 'queued' : 'not_configured'], 'attempts' => 0, 'available_at' => now(),
                'last_error' => $available ? null : $adapter->unavailableReason(),
            ]);
            if ($available && $external->wasRecentlyCreated) {
                SendLicenceNotification::dispatch($this->tenants->requireCouncil()->id, $external->id)->afterCommit();
            }
        }
    }
}
