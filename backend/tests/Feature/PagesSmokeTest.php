<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingStandardsService;
use App\Modules\Platform\Models\Council;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('pages@test.local', 'Page Admin');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);

        $this->actingAsStaff($this->admin, $this->council, $membership);
        $this->seedAccounting($this->council, $this->admin);
        app(AccountingStandardsService::class)->seedDefaults();
        $this->makeCustomer($this->council, $this->admin);
    }

    public function test_staff_pages_render_with_real_data(): void
    {
        $pages = [
            '/dashboard',
            '/accounting/chart',
            '/accounting/journals',
            '/accounting/reports/trial-balance',
            '/accounting/reports/position',
            '/accounting/reports/performance',
            '/accounting/reports/ledger?account=1110',
            '/accounting/standards',
            '/revenue/accounts',
            '/revenue/billing',
            '/revenue/cashier',
            '/revenue/bank',
            '/admin/modules',
            '/admin/audit',
            '/ai/assistant',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);
            $this->assertSame(200, $response->status(), "Page [{$page}] returned {$response->status()}");
            $this->assertNotEmpty($response->viewData('page'), "Page [{$page}] did not return an Inertia payload");
        }
    }

    public function test_customer_account_page_renders_for_staff(): void
    {
        $customer = $this->makeCustomer($this->council, $this->admin, ['email' => 'pagecustomer@example.com']);

        $this->get('/revenue/accounts/'.$customer['account']->id)->assertOk();
    }
}
