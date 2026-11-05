<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\ModuleRegistry;
use App\Modules\Platform\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces server-side module activation for a route group. Navigation is not
 * a security boundary.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $council = $this->tenants->requireCouncil();
        $registry = app(ModuleRegistry::class);

        foreach ($modules as $module) {
            if (! $registry->enabled($council, $module)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => "Module [{$module}] is not enabled."], 403);
                }

                abort(403, "The [{$module}] module is not enabled for this council.");
            }
        }

        return $next($request);
    }
}
