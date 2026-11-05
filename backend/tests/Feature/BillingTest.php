<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Revenue\Models\AccountTariff;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\Property;
use App\Modules\Revenue\Services\BillingService;
use App\Modules\Revenue\Services\MasterDataService;
use App\Modules\Revenue\Services\MeterReadingService;
use App\Modules\Revenue\Services\TariffCalculator;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    private User $approver;

    private Membership $adminMembership;

    private Membership $approverMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('preparer@test.local', 'Preparer');
        $this->approver = $this->makeUser('approver@test.local', 'Approver');

        $adminRole = $this->makeRole($this->council, 'council_admin', ['*']);
        $approverRole = $this->makeRole($this->council, 'finance_approver', ['*']);

        $this->adminMembership = $this->makeMembership($this->admin, $this->council, $adminRole);
        $this->approverMembership = $this->makeMembership($this->approver, $this->council, $approverRole);

        $this->actingAsStaff($this->admin, $this->council, $this->adminMembership);
        $this->seedAccounting($this->council, $this->admin);
    }

    public function test_effective_dating_selects_the_tariff_that_applies_to_the_period(): void
    {
        $customer = $this->prepareWaterCustomer();

        $this->useCouncil($this->council, $this->adminMembership);

        $assignedId = AccountTariff::where('customer_account_id', $customer['account']->id)
            ->where('service_type', 'water')
            ->value('tariff_version_id');

        $newer = app(MasterDataService::class)->createTariff(
            'WATER-TEST',
            'Water test tariff v2',
            'water',
            'metered',
            '2026-04-01',
            [['from_units' => 0, 'to_units' => null, 'rate_minor' => 500, 'fixed_charge_minor' => 0, 'minimum_charge_minor' => 0]],
            'USD',
            $this->admin,
        );
        app(MasterDataService::class)->approveTariff($newer, $this->admin);

        AccountTariff::where('customer_account_id', $customer['account']->id)
            ->where('service_type', 'water')
            ->update(['effective_to' => '2026-03-31']);

        $calculator = app(TariffCalculator::class);

        $march = $calculator->effectiveTariff($customer['account']->id, 'water', '2026-03-15');
        $april = $calculator->effectiveTariff($customer['account']->id, 'water', '2026-04-15');

        $this->assertSame($assignedId, $march?->id);
        $this->assertSame($newer->id, $april?->id);
    }

    public function test_preview_approve_and_post_create_bill_snapshot_and_balanced_journal(): void
    {
        $customer = $this->prepareWaterCustomer();

        $this->useCouncil($this->council, $this->adminMembership);

        $run = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'water-run-1'], $this->admin);

        $this->assertSame('previewed', $run->status);
        $this->assertSame(1, $run->control_totals['included']);
        $this->assertSame(2950, $run->control_totals['total_minor']);

        $this->actingAsStaff($this->approver, $this->council, $this->approverMembership);

        $approved = app(BillingService::class)->approve($run, $this->approver);
        $this->assertSame('approved', $approved->status);

        $posted = app(BillingService::class)->post($approved, $this->approver);
        $this->assertSame('posted', $posted->status);

        $bill = Bill::where('customer_account_id', $customer['account']->id)->firstOrFail();

        $this->assertSame(2950, $bill->total_minor);
        $this->assertSame(2950, $bill->balance_minor);
        $this->assertSame('issued', $bill->status);
        $this->assertSame('water', $bill->service_type);
        $this->assertSame('2026-03-31', $bill->issue_date->toDateString());
        $this->assertNotNull($bill->calculation_snapshot);
        $this->assertSame(25, (int) $bill->calculation_snapshot['consumption']);
        $this->assertNotEmpty($bill->calculation_snapshot['lines'][0]['explanation']);

        $journal = JournalEntry::findOrFail($bill->journal_entry_id);
        $this->assertSame('posted', $journal->status);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(2950, $journal->total_debit_minor);

        $report = app(ReportingService::class)->trialBalance('2026-03-31');
        $this->assertTrue($report['totals']['balanced']);
    }

    public function test_a_billing_run_cannot_be_approved_by_its_preparer(): void
    {
        $this->prepareWaterCustomer();
        $this->useCouncil($this->council, $this->adminMembership);

        $run = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'water-self-approval'], $this->admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Separation of duties');

        app(BillingService::class)->approve($run, $this->admin);
    }

    public function test_posting_the_same_run_twice_does_not_duplicate_bills(): void
    {
        $customer = $this->prepareWaterCustomer();
        $this->useCouncil($this->council, $this->adminMembership);

        $run = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'water-double-post'], $this->admin);
        app(BillingService::class)->approve($run, $this->approver);
        app(BillingService::class)->post($run, $this->approver);

        $second = app(BillingService::class)->post($run->fresh(), $this->approver);

        $this->assertSame('posted', $second->status);
        $this->assertSame(1, Bill::where('customer_account_id', $customer['account']->id)->count());
        $this->assertSame(1, JournalEntry::where('source_type', 'bill')->count());
    }

    public function test_preview_is_idempotent_for_the_same_key(): void
    {
        $this->prepareWaterCustomer();
        $this->useCouncil($this->council, $this->adminMembership);

        $first = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'same-preview'], $this->admin);
        $second = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'same-preview'], $this->admin);

        $this->assertTrue($first->is($second));
    }

    /** @return array{party: Party, property: Property, account: CustomerAccount} */
    private function prepareWaterCustomer(): array
    {
        $customer = $this->makeCustomer($this->council, $this->admin);

        $services = app(MasterDataService::class);
        $connection = $services->addConnection($customer['account'], 'water', $customer['property'], $this->admin);
        $meter = $services->addMeter($connection, 'WM-'.strtoupper(substr(md5(uniqid()), 0, 8)), '0', $this->admin);

        app(MeterReadingService::class)->record($meter, '25', '2026-03-20 10:00:00', 'actual', $this->admin);

        $tariff = $this->makeTariff($this->council, $this->admin, 'water');
        $services->assignTariff($customer['account'], $tariff, '2026-01-01');

        return $customer;
    }
}
