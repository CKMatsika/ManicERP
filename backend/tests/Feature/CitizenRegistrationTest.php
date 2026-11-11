<?php

namespace Tests\Feature;

use App\Mail\CitizenRegistrationVerificationMail;
use App\Models\User;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Platform\Models\Membership;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class CitizenRegistrationTest extends TestCase
{
    use BuildsMunicipalContext;

    public function test_verified_registration_creates_only_a_citizen_membership_without_linking_accounts(): void
    {
        Mail::fake();
        $council = $this->makeCouncil();
        $this->makeRole($council, 'citizen', ['customer.portal.access']);
        $this->post('/register', [
            'name' => 'Synthetic Citizen', 'email' => 'newcitizen@test.local', 'council_code' => $council->code,
            'password' => 'StrongPassword!2026', 'password_confirmation' => 'StrongPassword!2026',
        ])->assertRedirect();

        $user = User::query()->where('email', 'newcitizen@test.local')->firstOrFail();
        $this->assertSame('pending_verification', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, Membership::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, PortalAccountLink::query()->where('user_id', $user->id)->count());
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword!2026'])->assertSessionHasErrors('email');

        Mail::assertSent(CitizenRegistrationVerificationMail::class, function (CitizenRegistrationVerificationMail $mail) use ($council, $user) {
            parse_str((string) parse_url($mail->verificationUrl, PHP_URL_QUERY), $query);
            $path = parse_url($mail->verificationUrl, PHP_URL_PATH).'?'.http_build_query($query);
            $this->get($path)->assertOk();
            $this->post($path)->assertRedirect('/login');
            $this->assertSame($council->id, Membership::query()->where('user_id', $user->id)->firstOrFail()->council_id);
            $this->assertSame('citizen', Membership::query()->where('user_id', $user->id)->firstOrFail()->role->code);
            $this->assertNotNull($user->fresh()->email_verified_at);
            $this->assertSame(0, PortalAccountLink::query()->where('user_id', $user->id)->count());

            return true;
        });
    }

    public function test_duplicate_address_is_not_enumerated_or_granted_a_new_membership(): void
    {
        Mail::fake();
        $council = $this->makeCouncil();
        $existing = $this->makeUser('existing@test.local');
        $this->post('/register', [
            'name' => 'Changed Name', 'email' => $existing->email, 'council_code' => $council->code,
            'password' => 'StrongPassword!2026', 'password_confirmation' => 'StrongPassword!2026',
        ])->assertSessionHas('status');
        $this->assertSame('Test User', $existing->fresh()->name);
        $this->assertSame(0, Membership::query()->where('user_id', $existing->id)->count());
        Mail::assertNothingSent();
    }

    public function test_registration_rejects_weak_password_and_unknown_council(): void
    {
        $council = $this->makeCouncil();
        $this->from('/register')->post('/register', [
            'name' => 'Synthetic Citizen', 'email' => 'weak@test.local', 'council_code' => $council->code,
            'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'weak@test.local']);
    }

    public function test_expired_signed_verification_link_cannot_activate_an_identity(): void
    {
        $council = $this->makeCouncil();
        $user = User::create([
            'name' => 'Expired Synthetic Citizen',
            'email' => 'expired-registration@test.local',
            'password' => 'StrongPassword!2026',
            'status' => 'pending_verification',
        ]);
        $expiredUrl = URL::temporarySignedRoute(
            'register.verify',
            now()->subMinute(),
            ['user' => $user->id, 'token' => 'synthetic-expired-token'],
        );
        $path = parse_url($expiredUrl, PHP_URL_PATH).'?'.parse_url($expiredUrl, PHP_URL_QUERY);

        $this->get($path)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(0, Membership::query()->where('user_id', $user->id)->count());
    }

    public function test_registration_endpoint_throttles_abusive_requests(): void
    {
        Mail::fake();
        $council = $this->makeCouncil();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/register', [
                'name' => 'Synthetic Citizen', 'email' => "throttle{$attempt}@test.local", 'council_code' => $council->code,
                'password' => 'StrongPassword!2026', 'password_confirmation' => 'StrongPassword!2026',
            ])->assertRedirect();
        }

        $this->post('/register', [
            'name' => 'Synthetic Citizen', 'email' => 'throttle6@test.local', 'council_code' => $council->code,
            'password' => 'StrongPassword!2026', 'password_confirmation' => 'StrongPassword!2026',
        ])->assertTooManyRequests();
    }
}
