<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\TotpService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MfaController extends Controller
{
    public function __construct(private readonly TotpService $totp) {}

    public function challenge(Request $request)
    {
        if (! $request->session()->has('mfa_pending_user')) {
            return redirect()->route('home');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:16']]);

        $user = $request->user();
        $pending = $request->session()->get('mfa_pending_user');

        if (! $user || $pending !== $user->getKey()) {
            return redirect()->route('home');
        }

        if (! $this->checkCode($user, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not valid. Check your authenticator and try again.']);
        }

        $request->session()->forget('mfa_pending_user');
        $request->session()->put('mfa_verified_user', $user->getKey());
        $request->session()->put('mfa_verified_at', time());

        return redirect()->intended(route('home'));
    }

    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            return Inertia::render('Auth/MfaSetup', [
                'enabled' => true,
                'secret' => null,
                'uri' => null,
            ]);
        }

        $secret = $request->session()->get('mfa_setup_secret')
            ?? $this->totp->generateSecret();

        $request->session()->put('mfa_setup_secret', $secret);

        return Inertia::render('Auth/MfaSetup', [
            'enabled' => false,
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:16']]);

        $user = $request->user();
        $secret = $request->session()->get('mfa_setup_secret');

        if (! $secret) {
            return redirect()->route('security.mfa.setup');
        }

        if (! $this->totp->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not valid for the displayed secret.']);
        }

        $recoveryCodes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'mfa_recovery_codes' => $this->totp->hashRecoveryCodes($recoveryCodes),
        ])->save();

        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_user', $user->getKey());
        $request->session()->put('mfa_verified_at', time());

        return redirect()->route('home')->with('success', 'Multi-factor authentication enabled. Save your recovery codes.')->with('recovery_codes', $recoveryCodes);
    }

    private function checkCode($user, string $code): bool
    {
        if ($user->mfa_secret && $this->totp->verify($user->mfa_secret, $code)) {
            return true;
        }

        $recovery = $user->mfa_recovery_codes ?? [];
        $index = $this->totp->useRecoveryCode($code, $recovery);

        if ($index !== null && isset($recovery[$index])) {
            unset($recovery[$index]);
            $user->forceFill(['mfa_recovery_codes' => array_values($recovery)])->save();

            return true;
        }

        return false;
    }
}
