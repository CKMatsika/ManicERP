<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\AccountingStandard;
use App\Modules\Accounting\Services\AccountingStandardsService;
use App\Modules\Platform\Models\Council;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AccountingStandardsTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $admin = $this->makeUser('standards@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($admin, $this->council, $role);
        $this->actingAsStaff($admin, $this->council, $membership);
    }

    public function test_the_register_seeds_unverified_planning_assumptions(): void
    {
        $count = app(AccountingStandardsService::class)->seedDefaults();

        $this->assertSame(13, $count);
        $this->assertSame(13, AccountingStandard::count());
        $this->assertSame(13, AccountingStandard::where('verification_status', 'unverified')->count());
        $this->assertSame(0, AccountingStandard::whereNotNull('approved_at')->count());
        $this->assertSame(1, AccountingStandard::where('code', 'IPSAS 47')->count());
    }

    public function test_the_register_is_visible_and_does_not_claim_compliance(): void
    {
        app(AccountingStandardsService::class)->seedDefaults();

        $response = $this->get('/accounting/standards');

        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('Accounting/Standards', $page['component']);
        $this->assertSame(13, $page['props']['summary']['total']);
        $this->assertSame(0, $page['props']['summary']['verified']);
    }

    public function test_seeding_is_idempotent(): void
    {
        app(AccountingStandardsService::class)->seedDefaults();
        app(AccountingStandardsService::class)->seedDefaults();

        $this->assertSame(13, AccountingStandard::count());
    }
}
