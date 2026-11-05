<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Services\MasterDataService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class CouncilIsolationTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    private Council $councilA;

    private Council $councilB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->councilA = $this->makeCouncil(['code' => 'ISOA', 'name' => 'Isolation Council A', 'database_name' => $this->tenantDatabase()]);
        $this->councilB = $this->makeCouncil(['code' => 'ISOB', 'name' => 'Isolation Council B', 'database_name' => $this->secondaryTenantDatabase()]);

        $this->admin = $this->makeUser('iso-admin@test.local', 'Isolation Admin');
        $role = $this->makeRole($this->councilA, 'council_admin', ['*']);
        $this->makeMembership($this->admin, $this->councilA, $role);
    }

    protected function tearDown(): void
    {
        foreach ([$this->councilA, $this->councilB] as $council) {
            try {
                app(TenantManager::class)->connect($council);

                foreach ([
                    'account_liabilities', 'customer_accounts', 'ownership_interests',
                    'property_valuations', 'properties', 'contact_points', 'party_roles', 'parties',
                    'audit_events', 'number_sequences',
                ] as $table) {
                    DB::connection('tenant')->table($table)->delete();
                }
            } catch (\Throwable) {
                // Best-effort cleanup for the physical isolation databases.
            }
        }

        parent::tearDown();
    }

    public function test_operational_data_is_physically_isolated_between_councils(): void
    {
        $tenant = app(TenantManager::class);

        $tenant->connect($this->councilA);
        $partyA = app(MasterDataService::class)->createParty([
            'party_type' => 'individual',
            'display_name' => 'Council A Customer',
            'email' => 'a@example.com',
        ], $this->admin);

        $this->assertSame(1, Party::count());

        $tenant->connect($this->councilB);
        $this->assertSame(0, Party::count());
        $this->assertNull(Party::find($partyA->id));

        $partyB = app(MasterDataService::class)->createParty([
            'party_type' => 'individual',
            'display_name' => 'Council B Customer',
            'email' => 'b@example.com',
        ], $this->admin);

        $tenant->connect($this->councilA);
        $this->assertSame(1, Party::count());
        $this->assertNotNull(Party::find($partyA->id));
        $this->assertNull(Party::find($partyB->id));
    }

    public function test_a_staff_session_cannot_read_another_councils_accounts(): void
    {
        $tenant = app(TenantManager::class);
        $services = app(MasterDataService::class);

        $tenant->connect($this->councilA);
        $accountA = $this->createAccountFor($this->councilA, $this->admin, 'A-'.uniqid());

        $tenant->connect($this->councilB);
        $accountB = $this->createAccountFor($this->councilB, $this->admin, 'B-'.uniqid());

        $tenant->connect($this->councilA);

        $response = $this->actingAs($this->admin)
            ->withSession(['council_code' => $this->councilB->code, 'mfa_verified_user' => $this->admin->id, 'mfa_verified_at' => time()])
            ->get('/revenue/accounts');

        $response->assertOk();

        $page = $response->viewData('page');
        $ids = collect($page['props']['accounts']['data'] ?? [])->pluck('id')->all();

        $this->assertContains($accountA->id, $ids);
        $this->assertNotContains($accountB->id, $ids);
    }

    public function test_an_api_token_cannot_reach_a_council_without_a_membership(): void
    {
        $token = $this->admin->createToken('isolation', ['council:'.$this->councilB->code, 'portal'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/accounts')
            ->assertStatus(403)
            ->assertJsonPath('message', 'A valid, scoped council token is required.');
    }

    private function createAccountFor(Council $council, User $admin, string $email): CustomerAccount
    {
        $services = app(MasterDataService::class);

        $party = $services->createParty([
            'party_type' => 'individual',
            'display_name' => $council->code.' Customer',
            'email' => $email.'@example.com',
        ], $admin);

        $property = $services->createProperty([
            'parcel_number' => $council->code.'-'.uniqid(),
            'ward_code' => null,
        ], $admin);

        return $services->createAccount($property, $party, 'rates', $admin);
    }
}
