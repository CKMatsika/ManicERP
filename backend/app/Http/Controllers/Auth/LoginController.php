<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minutes.',
            ]);
        }

        $user = User::where('email', strtolower($credentials['email']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 900);
            $user?->increment('failed_login_attempts');

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->isActive()) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages(['email' => 'This account is suspended or temporarily locked.']);
        }

        if (! $user->activeMemberships()->exists()) {
            throw ValidationException::withMessages(['email' => 'This account has no active council membership.']);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_attempts' => 0,
        ])->save();

        $requiresMfa = $user->activeMemberships()->with('role')->get()
            ->contains(fn ($membership) => (bool) $membership->role?->requires_mfa);

        if ($requiresMfa) {
            $request->session()->put('mfa_pending_user', $user->getKey());
            $request->session()->forget('mfa_verified_user');

            return redirect()->route('security.mfa.challenge');
        }

        return redirect()->intended(route('home'));
    }
}
