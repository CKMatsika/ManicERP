<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces MFA for privileged staff roles. Users without an enrolled
 * authenticator are sent to enrolment before they can reach operational data.
 */
class EnsureMfaVerified
{
    private const MAX_AGE_SECONDS = 43200;

    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->tenants->membership();
        $user = $request->user();

        if (! $user || ! $membership?->role?->requires_mfa) {
            return $next($request);
        }

        if (! $user->hasMfaEnabled()) {
            if ($request->routeIs('security.mfa.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('security.mfa.setup')
                ->with('warning', 'Your role requires multi-factor authentication. Enrol an authenticator to continue.');
        }

        $verifiedFor = $request->session()->get('mfa_verified_user');
        $verifiedAt = (int) $request->session()->get('mfa_verified_at', 0);
        $fresh = (time() - $verifiedAt) < self::MAX_AGE_SECONDS;

        if ($verifiedFor !== $user->getKey() || ! $fresh) {
            if ($request->routeIs('security.mfa.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            $request->session()->put('mfa_pending_user', $user->getKey());
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('security.mfa.challenge');
        }

        return $next($request);
    }
}
