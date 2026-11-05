<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\Property;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $citizen;

    private Membership $membership;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->council = $this->makeCouncil();
        $this->citizen = $this->makeUser('citizen@test.local', 'Citizen');
        $this->staff = $this->makeUser('clerk@test.local', 'Clerk');

        $citizenRole = $this->makeRole($this->council, 'citizen', ['customer.portal.access', 'customer.link.request', 'customer.service_request.create']);
        $staffRole = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view', 'customer.link.verify']);

        $this->membership = $this->makeMembership($this->citizen, $this->council, $citizenRole, 'citizen');
        $this->makeMembership($this->staff, $this->council, $staffRole);

        $this->actingAsCitizen($this->citizen, $this->council, $this->membership);
    }

    public function test_linking_requires_a_verification_code_before_access_is_granted(): void
    {
        $customer = $this->makeCustomerRecord();

        $this->get('/portal/accounts/'.$customer['account']->id)->assertStatus(404);

        $result = app(AccountLinkService::class)->request($this->citizen, $customer['account']->account_number, 'owner');
        $link = $result['link'];
        $code = $result['code'];

        $this->assertInstanceOf(PortalAccountLink::class, $link);
        $this->assertSame('pending', $link->status);

        // Still no access while the link is pending.
        $this->get('/portal/accounts/'.$customer['account']->id)->assertStatus(404);

        $this->post('/portal/link/'.$link->id.'/verify', ['code' => $code])
            ->assertRedirect('/portal/link');

        $this->assertSame('verified', $link->fresh()->status);

        $this->get('/portal/accounts/'.$customer['account']->id)->assertOk();
    }

    public function test_a_guessed_account_identifier_is_not_disclosed(): void
    {
        $own = $this->makeCustomerRecord();
        $other = $this->makeCustomerRecord();

        $result = app(AccountLinkService::class)->request($this->citizen, $own['account']->account_number);
        app(AccountLinkService::class)->verify($this->citizen, $result['link'], $result['code']);

        $this->get('/portal/accounts/'.$own['account']->id)->assertOk();
        $this->get('/portal/accounts/'.$other['account']->id)->assertStatus(404);
    }

    public function test_revoking_a_link_removes_access_and_is_audited(): void
    {
        $customer = $this->makeCustomerRecord();
        $result = app(AccountLinkService::class)->request($this->citizen, $customer['account']->account_number);
        app(AccountLinkService::class)->verify($this->citizen, $result['link'], $result['code']);

        $this->post('/portal/link/'.$result['link']->id.'/revoke', ['reason' => 'No longer the owner'])
            ->assertRedirect();

        $this->assertSame('revoked', $result['link']->fresh()->status);
        $this->get('/portal/accounts/'.$customer['account']->id)->assertStatus(404);

        $this->assertDatabaseHas('audit_events', ['action' => 'customer.account_link.revoked'], 'tenant');
    }

    public function test_incorrect_code_does_not_verify_the_link(): void
    {
        $customer = $this->makeCustomerRecord();
        $result = app(AccountLinkService::class)->request($this->citizen, $customer['account']->account_number);

        $this->post('/portal/link/'.$result['link']->id.'/verify', ['code' => '000000'])
            ->assertRedirect();

        $this->assertSame('pending', $result['link']->fresh()->status);
        $this->get('/portal/accounts/'.$customer['account']->id)->assertStatus(404);
    }

    public function test_citizen_portal_pages_render(): void
    {
        $customer = $this->makeCustomerRecord();
        $result = app(AccountLinkService::class)->request($this->citizen, $customer['account']->account_number);
        app(AccountLinkService::class)->verify($this->citizen, $result['link'], $result['code']);

        foreach (['/portal', '/portal/pay', '/portal/link', '/portal/requests'] as $page) {
            $response = $this->get($page);
            $this->assertSame(200, $response->status(), "Portal page [{$page}] returned {$response->status()}");
            $this->assertNotEmpty($response->viewData('page'));
        }
    }

    /** @return array{party: Party, property: Property, account: CustomerAccount} */
    private function makeCustomerRecord(): array
    {
        return $this->makeCustomer($this->council, $this->staff, ['email' => 'customer'.uniqid().'@example.com']);
    }
}
