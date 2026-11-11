<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CitizenRegistrationVerificationMail;
use App\Models\User;
use App\Modules\Platform\Models\CitizenRegistrationVerification;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class CitizenRegistrationController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Register', [
            'councils' => Council::query()->where('status', 'active')->orderBy('name')->get(['code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'council_code' => ['required', 'string', 'exists:councils,code'],
        ]);

        $council = Council::query()->where('code', strtoupper($data['council_code']))->where('status', 'active')->first();
        abort_unless($council, 422, 'The selected council is not accepting registrations.');

        $email = mb_strtolower(trim($data['email']));
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            // Keep account-existence information private and avoid creating new
            // memberships or changing an existing identity.
            return back()->with('status', 'If this address can be registered, a verification message will be sent.');
        }

        [$user, $plainToken] = DB::transaction(function () use ($data, $email, $council) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => Hash::make($data['password']),
                'status' => 'pending_verification',
                'locale' => 'en',
            ]);
            $plainToken = bin2hex(random_bytes(32));
            CitizenRegistrationVerification::create([
                'user_id' => $user->id,
                'council_id' => $council->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(30),
            ]);

            return [$user, $plainToken];
        });

        $url = URL::temporarySignedRoute('register.verify', now()->addMinutes(30), [
            'user' => $user->id,
            'token' => $plainToken,
        ]);
        Mail::to($user->email)->send(new CitizenRegistrationVerificationMail($url));

        return back()->with('status', 'If this address can be registered, a verification message will be sent.');
    }

    public function verifyForm(Request $request)
    {
        $verification = $this->pendingVerification($request);
        abort_unless($verification, 404);

        return Inertia::render('Auth/VerifyRegistration', [
            'email' => $verification->user->email,
        ]);
    }

    public function verify(Request $request)
    {
        $verification = $this->pendingVerification($request, lock: true);
        abort_unless($verification, 404);

        DB::transaction(function () use ($verification) {
            $locked = CitizenRegistrationVerification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->consumed_at || $locked->expires_at->isPast() || $locked->attempts >= 5, 410);
            $locked->increment('attempts');

            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $council = Council::query()->whereKey($locked->council_id)->where('status', 'active')->firstOrFail();
            $role = Role::query()->where('council_id', $council->id)->where('code', 'citizen')->firstOrFail();

            $user->forceFill(['email_verified_at' => now(), 'status' => 'active'])->save();
            Membership::firstOrCreate(
                ['council_id' => $council->id, 'user_id' => $user->id, 'role_id' => $role->id],
                ['member_type' => 'citizen', 'status' => 'active', 'started_at' => now()],
            );
            $locked->forceFill(['consumed_at' => now()])->save();
        });

        return redirect()->route('login')->with('status', 'Email verified. You can now sign in and request access to an account using its recorded contact details.');
    }

    private function pendingVerification(Request $request, bool $lock = false): ?CitizenRegistrationVerification
    {
        $userId = (string) $request->query('user');
        $token = (string) $request->query('token');
        if ($userId === '' || $token === '') {
            return null;
        }
        $query = CitizenRegistrationVerification::query()->with('user')->where('user_id', $userId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $verification = $query->first();
        if (! $verification || $verification->consumed_at || $verification->expires_at->isPast() || $verification->attempts >= 5 || ! hash_equals($verification->token_hash, hash('sha256', $token))) {
            if ($verification && ! $verification->consumed_at) {
                $verification->increment('attempts');
            }

            return null;
        }

        return $verification;
    }
}
