<?php

namespace App\Modules\Licensing\Jobs;

use App\Models\User;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Services\LicenceChargeService;
use App\Modules\Licensing\Services\LicenceNotificationService;
use App\Modules\Platform\Jobs\TenantJob;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Expires licences and creates deduplicated, explicitly unavailable reminders. */
class LicenceMaintenanceJob extends TenantJob
{
    protected ?string $module = 'licensing';

    protected function execute(Council $council): void
    {
        $actor = User::query()
            ->whereHas('memberships', fn ($query) => $query->where('council_id', $council->id)->where('status', 'active'))
            ->get()
            ->first(function (User $user) {
                return $user->memberships->contains(function ($membership) {
                    $permissions = $membership->role?->permissions ?? [];

                    return in_array('*', $permissions, true) || in_array('licensing.charges.release', $permissions, true);
                });
            });

        if ($actor) {
            app(LicenceChargeService::class)->releaseEligibleDeferred($actor);
        }

        $today = CarbonImmutable::now($council->timezone ?: 'Africa/Harare')->toDateString();
        foreach (Licence::whereIn('validity_status', ['active', 'expired'])->orderBy('id')->get() as $licence) {
            DB::connection('tenant')->transaction(function () use ($licence, $today, $council): void {
                $locked = Licence::query()->lockForUpdate()->findOrFail($licence->id);
                if ($locked->validity_status === 'active' && $locked->valid_to?->toDateString() < $today) {
                    $locked->forceFill(['validity_status' => 'expired', 'lifecycle_reason' => 'Expired at the end of the configured council-local validity date'])->save();
                    app(AuditLogger::class)->record(action: 'licensing.expired', subject: $locked, after: ['valid_to' => $locked->valid_to->toDateString(), 'timezone' => $council->timezone], module: 'licensing');
                    $issuedApplication = LicenceApplication::where('licence_id', $locked->id)->where('status', 'issued')->latest()->first();
                    app(LicenceNotificationService::class)->licence($locked, $issuedApplication, 'licence_expired', 'expiry:'.$locked->id.':'.$locked->valid_to->toDateString());
                }
                if (! $locked->valid_to || ! in_array($locked->validity_status, ['active', 'expired'], true)) {
                    return;
                }
                $days = (int) CarbonImmutable::parse($today)->diffInDays($locked->valid_to, false);
                if ($days < 0) {
                    return;
                }
                $application = LicenceApplication::where('licence_id', $locked->id)->where('status', 'issued')->latest()->first();
                $reminders = $application?->configuration_snapshot['notification_days'] ?? [];
                if (! in_array($days, array_map('intval', $reminders), true)) {
                    return;
                }
                app(LicenceNotificationService::class)->licence($locked, $application, 'renewal_reminder_'.$days, 'renewal:'.$locked->id.':'.$locked->valid_to->toDateString().':'.$days);
            });
        }
    }
}
