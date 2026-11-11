<?php

namespace Tests\Feature;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TotpService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_staff_user_can_sign_in(): void
    {
        $user = $this->makeUser('login@test.local');
        $role = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view']);
        $this->makeMembership($user, $this->council, $role);

        $response = $this->post('/login', ['email' => 'login@test.local', 'password' => 'Secret!12345']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->makeUser('bad@test.local');
        $role = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view']);
        $this->makeMembership($user, $this->council, $role);

        $this->from('/login')->post('/login', ['email' => 'bad@test.local', 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_privileged_role_is_challenged_for_multi_factor_authentication(): void
    {
        $user = $this->makeUser('privileged@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*'], requiresMfa: true);
        $this->makeMembership($user, $this->council, $role);

        $this->post('/login', ['email' => 'privileged@test.local', 'password' => 'Secret!12345'])
            ->assertRedirect('/security/mfa/setup');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect('/security/mfa/setup');
        $this->get('/security/mfa/setup')->assertOk();
    }

    public function test_privileged_user_can_enrol_mfa_through_the_setup_confirmation_flow(): void
    {
        $user = $this->makeUser('mfa-enrol@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*'], requiresMfa: true);
        $this->makeMembership($user, $this->council, $role);

        $this->post('/login', ['email' => 'mfa-enrol@test.local', 'password' => 'Secret!12345'])
            ->assertRedirect('/security/mfa/setup');
        $this->get('/security/mfa/setup')->assertOk();
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);

        $this->post('/security/mfa/setup', ['code' => app(TotpService::class)->code($secret)])
            ->assertRedirect('/home');

        $this->assertTrue($user->fresh()->hasMfaEnabled());
        $this->get('/dashboard')->assertOk();
    }

    public function test_an_enrolled_privileged_user_is_challenged_for_a_code(): void
    {
        $user = $this->makeUser('mfa@test.local');
        $role = $this->makeRole($this->council, 'finance_approver', ['*'], requiresMfa: true);
        $this->makeMembership($user, $this->council, $role);

        $service = app(TotpService::class);
        $secret = $service->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => 'mfa@test.local', 'password' => 'Secret!12345'])
            ->assertRedirect('/security/mfa/challenge');

        $this->get('/security/mfa/challenge')->assertOk();

        $this->get('/dashboard')->assertRedirect('/security/mfa/challenge');

        $this->post('/security/mfa/challenge', ['code' => $service->code($secret)])
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
    }

    public function test_expired_privileged_mfa_session_requires_a_fresh_challenge(): void
    {
        $user = $this->makeUser('expired-mfa@test.local');
        $role = $this->makeRole($this->council, 'finance_approver', ['*'], requiresMfa: true);
        $this->makeMembership($user, $this->council, $role);
        $user->forceFill(['mfa_secret' => app(TotpService::class)->generateSecret(), 'mfa_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->withSession([
                'council_code' => $this->council->code,
                'mfa_verified_user' => $user->id,
                'mfa_verified_at' => time() - 43201,
            ])
            ->get('/dashboard')
            ->assertRedirect('/security/mfa/challenge');
    }

    public function test_a_cashier_cannot_post_journals(): void
    {
        $user = $this->makeUser('cashier@test.local');
        $role = $this->makeRole($this->council, 'cashier', ['revenue.payment.capture', 'revenue.cashier.open']);
        $membership = $this->makeMembership($user, $this->council, $role);

        $this->actingAsStaff($user, $this->council, $membership);

        $this->get('/accounting/journals')->assertStatus(403);
        $this->post('/accounting/journals', [])->assertStatus(403);
    }

    public function test_an_unauthorised_staff_member_cannot_manage_modules(): void
    {
        $user = $this->makeUser('clerk2@test.local');
        $role = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view']);
        $membership = $this->makeMembership($user, $this->council, $role);

        $this->actingAsStaff($user, $this->council, $membership);

        $this->get('/admin/modules')->assertStatus(403);
    }

    public function test_a_user_without_membership_cannot_sign_in(): void
    {
        $this->makeUser('nomember@test.local');

        $this->post('/login', ['email' => 'nomember@test.local', 'password' => 'Secret!12345'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
