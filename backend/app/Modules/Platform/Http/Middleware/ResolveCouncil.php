<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\CouncilResolver;
use App\Modules\Platform\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and validates the council context for the request, then points the
 * tenant connection at that council's database. Resolution is host/membership
 * based for the web and token-ability based for the mobile API; a client
 * supplied council id is never used.
 */
class ResolveCouncil
{
    public function __construct(
        private readonly CouncilResolver $resolver,
        private readonly TenantManager $tenants,
    ) {}

    public function handle(Request $request, Closure $next, string $memberType = 'staff'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($request->is('api/*') || $request->expectsJson() && $request->bearerToken()) {
            $councilCode = $this->councilFromToken($request);

            if ($councilCode) {
                $council = Council::query()
                    ->where('code', $councilCode)
                    ->where('status', 'active')
                    ->first();

                if ($council) {
                    $membership = $this->resolver->membershipFor($user, $council, $memberType);

                    if ($membership) {
                        $this->tenants->connect($council, $membership);

                        return $next($request);
                    }
                }
            }

            return response()->json(['message' => 'A valid, scoped council token is required.'], 403);
        }

        [$council, $membership] = $this->resolver->resolve($request, $user, $memberType);

        if (! $council) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No council context could be resolved.'], 403);
            }

            return redirect()->route('councils.index')
                ->with('error', 'Select a council you are authorised to use.');
        }

        if (! $membership) {
            abort(403, 'You do not have an active membership in this council.');
        }

        $this->tenants->connect($council, $membership);

        if ($membership->member_type !== $memberType) {
            abort(403, 'This area is not available for your membership type.');
        }

        $request->attributes->set(config('tenancy.request_attribute'), $council);
        $request->session()->put(config('tenancy.session_key'), $council->code);

        return $next($request);
    }

    private function councilFromToken(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return null;
        }

        foreach ($token->abilities ?? [] as $ability) {
            if (str_starts_with($ability, 'council:')) {
                return substr($ability, strlen('council:'));
            }
        }

        return null;
    }
}
