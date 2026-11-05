<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'council_code' => ['nullable', 'string', 'max:24'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $key = 'api-login:'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again later.']);
        }

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->isActive()) {
            RateLimiter::hit($key, 900);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        $memberships = $user->activeMemberships()
            ->whereHas('council', fn ($query) => $query->where('status', 'active'))
            ->with(['council', 'role'])
            ->get();

        $membership = $data['council_code'] ?? null
            ? $memberships->first(fn ($item) => $item->council?->code === strtoupper($data['council_code']))
            : ($memberships->count() === 1 ? $memberships->first() : null);

        if (! $membership) {
            throw ValidationException::withMessages([
                'council_code' => 'Select a council you belong to (council_code is required for multiple memberships).',
            ]);
        }

        $ability = $membership->member_type === 'citizen' ? 'portal' : 'staff';

        $token = $user->createToken(
            $data['device_name'] ?? 'mobile',
            ['council:'.$membership->council->code, $ability, $membership->member_type],
            now()->addDays(30),
        );

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email],
            'council' => [
                'code' => $membership->council->code,
                'name' => $membership->council->name,
                'currency' => $membership->council->currency_code,
                'timezone' => $membership->council->timezone,
            ],
            'membership' => [
                'type' => $membership->member_type,
                'role' => $membership->role?->code,
                'permissions' => $membership->role?->permissions,
            ],
            'abilities' => ['council:'.$membership->council->code, $ability],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        $token?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    public function me(Request $request)
    {
        $tenants = app(TenantManager::class);
        $council = $tenants->council();
        $membership = $tenants->membership();

        return response()->json([
            'user' => [
                'id' => $request->user()->getKey(),
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'council' => $council ? [
                'code' => $council->code,
                'name' => $council->name,
                'is_demo' => $council->is_demo,
                'currency' => $council->currency_code,
                'timezone' => $council->timezone,
            ] : null,
            'membership' => $membership ? [
                'type' => $membership->member_type,
                'role' => $membership->role?->code,
            ] : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
