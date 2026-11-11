<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Modules\Licensing\Contracts\LicenceAiProvider;
use App\Modules\Licensing\Services\SdkLicenceAiProvider;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Contracts\RefundGateway;
use App\Modules\Revenue\Gateways\SandboxRefundGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
        $this->app->bind(RefundGateway::class, SandboxRefundGateway::class);
        $this->app->bind(LicenceAiProvider::class, SdkLicenceAiProvider::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function boot(): void
    {
        // Permissions are checked against the validated membership, its role
        // and the owning module's activation state. Policies still run when
        // this returns null.
        Gate::before(function (User $user, string $ability) {
            $council = app(TenantManager::class)->council();

            if (! $council) {
                return null;
            }

            $permissions = app(PermissionService::class);

            if ($permissions->moduleForPermission($ability) === null) {
                return null;
            }

            return $permissions->allows($user, $ability) ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getKey() ?: $request->ip());
        });
    }
}
