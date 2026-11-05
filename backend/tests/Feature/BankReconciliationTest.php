<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Services\BankReconciliationService;
use App\Modules\Revenue\Services\CashierService;
use App\Modules\Revenue\Services\PaymentService;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00'));
        CarbonImmutable::setTestNow('2026-03-20 10:00:00');

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('bank@test.local', 'Bank Officer');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);

        $this->actingAsStaff($this->admin, $this->council, $membership);
        $this->seedAccounting($this->council, $this->admin);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        CarbonImmutable::setTestNow(null);
        parent::tearDown();
    }

    public function test_statement_import_detects_duplicates_and_matches_verified_payments(): void
    {
        $customer = $this->makeCustomer($this->council, $this->admin);
        $this->useCouncil($this->council);

        $session = app(CashierService::class)->open($this->admin, 0);
        $payment = app(PaymentService::class)->captureCash(
            $customer['account'],
            100_000,
            'bank_transfer',
            'REF-123',
            $session,
            $this->admin,
        );

        $bank = BankAccount::create([
            'code' => 'BANK1',
            'name' => 'Main bank account',
            'bank_name' => 'Test Bank',
            'account_number_masked' => '**** 1234',
            'gl_account_code' => '1120',
            'currency_code' => 'USD',
            'status' => 'active',
        ]);

        $csv = "value_date,amount,reference,narrative\n2026-03-20,1000.00,{$payment->payment_number},Deposit\n";

        $service = app(BankReconciliationService::class);
        $statement = $service->importCsv($bank, 'STMT-1', '2026-03-20', 0, 100_000, $csv, $this->admin);

        $this->assertSame(1, $statement->lines()->count());
        $this->assertSame(100_000, $statement->closing_balance_minor);

        // Re-importing identical content is idempotent.
        $again = $service->importCsv($bank, 'STMT-1', '2026-03-20', 0, 100_000, $csv, $this->admin);
        $this->assertTrue($again->is($statement));

        $suggestions = $service->suggestions($statement);
        $this->assertCount(1, $suggestions);
        $this->assertSame(100, $suggestions[0]['confidence']);
        $this->assertSame($payment->id, $suggestions[0]['payment']?->id);

        $service->confirmMatch($statement->lines()->first(), $payment, $this->admin);

        $this->assertSame('matched', $statement->lines()->first()->fresh()->status);
        $this->assertSame('bank_import', $payment->fresh()->verification_source);

        $reconciliation = $service->reconcile($bank, $statement, $this->admin);

        $this->assertSame('completed', $reconciliation->status);
        $this->assertSame(0, $reconciliation->variance_minor);
        $this->assertSame(100_000, $reconciliation->cashbook_balance_minor);
    }

    public function test_conflicting_statement_numbers_are_rejected(): void
    {
        $bank = BankAccount::create([
            'code' => 'BANK2',
            'name' => 'Second bank account',
            'bank_name' => 'Test Bank',
            'account_number_masked' => '**** 5678',
            'gl_account_code' => '1120',
            'currency_code' => 'USD',
            'status' => 'active',
        ]);

        $service = app(BankReconciliationService::class);
        $service->importCsv($bank, 'STMT-2', '2026-03-20', 0, 1_000, "value_date,amount,reference,narrative\n2026-03-20,10.00,A,First\n", $this->admin);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been imported');

        $service->importCsv($bank, 'STMT-2', '2026-03-20', 0, 2_000, "value_date,amount,reference,narrative\n2026-03-20,20.00,B,Second\n", $this->admin);
    }

    public function test_unmatched_lines_are_reported(): void
    {
        $bank = BankAccount::create([
            'code' => 'BANK3',
            'name' => 'Third bank account',
            'bank_name' => 'Test Bank',
            'account_number_masked' => '**** 9999',
            'gl_account_code' => '1120',
            'currency_code' => 'USD',
            'status' => 'active',
        ]);

        $service = app(BankReconciliationService::class);
        $statement = $service->importCsv($bank, 'STMT-3', '2026-03-20', 0, 5_000, "value_date,amount,reference,narrative\n2026-03-20,50.00,UNKNOWN,No match\n", $this->admin);

        $suggestions = $service->suggestions($statement);

        $this->assertCount(1, $suggestions);
        $this->assertNull($suggestions[0]['payment']);
        $this->assertSame(0, $suggestions[0]['confidence']);
        $this->assertSame('unmatched', $statement->lines()->first()->status);
    }
}
