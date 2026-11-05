<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\Receipt;
use App\Modules\Revenue\Services\BillingService;
use App\Modules\Revenue\Services\MasterDataService;
use App\Modules\Revenue\Services\MeterReadingService;
use App\Modules\Revenue\Services\PaymentService;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    private User $approver;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('payadmin@test.local');
        $this->approver = $this->makeUser('payapprover@test.local');

        $adminRole = $this->makeRole($this->council, 'council_admin', ['*']);
        $approverRole = $this->makeRole($this->council, 'finance_approver', ['*']);

        $this->membership = $this->makeMembership($this->admin, $this->council, $adminRole);
        $this->makeMembership($this->approver, $this->council, $approverRole);

        $this->actingAsStaff($this->admin, $this->council, $this->membership);
        $this->seedAccounting($this->council, $this->admin);
    }

    public function test_verified_webhook_creates_one_payment_receipt_allocation_and_balanced_journal(): void
    {
        $bill = $this->makeWaterBill();

        $intent = app(PaymentService::class)->createIntent(
            $bill->account,
            (int) $bill->balance_minor,
            'web',
            'sandbox',
            'payment-intent-test-1',
            $this->admin,
        );

        $response = $this->postSandboxWebhook([
            'reference' => $intent->reference,
            'provider_reference' => 'SBX-TEST-0001',
            'status' => 'succeeded',
            'amount_minor' => (int) $bill->balance_minor,
            'nonce' => 'nonce-1',
        ]);

        $response->assertOk()->assertJsonPath('status', 'verified');

        $payment = Payment::firstOrFail();
        $this->assertSame('verified', $payment->status);
        $this->assertSame('gateway', $payment->verification_source);
        $this->assertSame(1, Receipt::count());
        $this->assertSame(1, $payment->allocations()->count());

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame(0, $bill->balance_minor);

        $journal = JournalEntry::where('source_type', 'payment')->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $this->assertSame((int) $payment->amount_minor, $journal->total_debit_minor);

        $this->assertSame('verified', $intent->fresh()->status);
    }

    public function test_replayed_webhook_is_rejected(): void
    {
        $bill = $this->makeWaterBill();
        $intent = app(PaymentService::class)->createIntent(
            $bill->account,
            (int) $bill->balance_minor,
            'web',
            'sandbox',
            'payment-intent-test-2',
            $this->admin,
        );

        $payload = [
            'reference' => $intent->reference,
            'provider_reference' => 'SBX-TEST-0002',
            'status' => 'succeeded',
            'amount_minor' => (int) $bill->balance_minor,
            'nonce' => 'nonce-replay',
        ];

        $this->postSandboxWebhook($payload)->assertOk();
        $this->postSandboxWebhook($payload)->assertStatus(422);

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Receipt::count());
    }

    public function test_invalid_signature_is_rejected_and_creates_nothing(): void
    {
        $bill = $this->makeWaterBill();
        $intent = app(PaymentService::class)->createIntent($bill->account, 1_000, 'web', 'sandbox', 'payment-intent-test-3', $this->admin);

        $body = json_encode([
            'reference' => $intent->reference,
            'provider_reference' => 'SBX-BAD',
            'status' => 'succeeded',
            'amount_minor' => 1_000,
            'nonce' => 'nonce-bad',
        ]);

        $this->call('POST', '/api/v1/webhooks/payments/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SANDBOX_TIMESTAMP' => (string) time(),
            'HTTP_X_SANDBOX_SIGNATURE' => 'deadbeef',
        ], $body)->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    public function test_duplicate_settlement_is_idempotent(): void
    {
        $bill = $this->makeWaterBill();
        $intent = app(PaymentService::class)->createIntent($bill->account, 2_000, 'web', 'sandbox', 'payment-intent-test-4', $this->admin);

        $service = app(PaymentService::class);
        $first = $service->settle($intent, 'succeeded', 'SBX-IDEMPOTENT', ['amount_minor' => 2_000]);
        $second = $service->settle($intent->fresh(), 'succeeded', 'SBX-IDEMPOTENT', ['amount_minor' => 2_000]);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Payment::where('external_reference', 'SBX-IDEMPOTENT')->count());
        $this->assertSame(1, Receipt::count());
    }

    public function test_overpayment_leaves_an_unapplied_credit_liability(): void
    {
        $bill = $this->makeWaterBill();
        $amount = (int) $bill->balance_minor + 1_000;

        $intent = app(PaymentService::class)->createIntent($bill->account, $amount, 'web', 'sandbox', 'payment-intent-test-5', $this->admin);
        $payment = app(PaymentService::class)->settle($intent, 'succeeded', 'SBX-OVERPAY', ['amount_minor' => $amount]);

        $this->assertSame(1_000, $payment->unallocatedMinor());
        $this->assertSame(0, $bill->fresh()->balance_minor);

        $journal = JournalEntry::where('source_type', 'payment')->firstOrFail();
        $unapplied = $journal->lines()->whereHas('account', fn ($query) => $query->where('code', '2050'))->first();

        $this->assertNotNull($unapplied);
        $this->assertSame(1_000, $unapplied->credit_minor);
    }

    public function test_failed_settlement_creates_no_receipt(): void
    {
        $bill = $this->makeWaterBill();
        $intent = app(PaymentService::class)->createIntent($bill->account, 1_500, 'web', 'sandbox', 'payment-intent-test-6', $this->admin);

        $payment = app(PaymentService::class)->settle($intent, 'failed', 'SBX-FAILED', ['amount_minor' => 1_500]);

        $this->assertNull($payment);
        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Receipt::count());
    }

    public function test_browser_return_alone_does_not_issue_a_receipt(): void
    {
        $bill = $this->makeWaterBill();
        $intent = app(PaymentService::class)->createIntent($bill->account, 1_000, 'web', 'sandbox', 'payment-intent-test-7', $this->admin);

        $this->assertSame('pending', $intent->fresh()->status);
        $this->assertSame(0, Receipt::count());

        $this->get('/portal/pay/'.$intent->id)->assertRedirect();
        $this->assertSame(0, Receipt::count());
    }

    private function makeWaterBill(): Bill
    {
        $customer = $this->makeCustomer($this->council, $this->admin);
        $services = app(MasterDataService::class);

        $connection = $services->addConnection($customer['account'], 'water', $customer['property'], $this->admin);
        $meter = $services->addMeter($connection, 'WM-'.strtoupper(substr(md5(uniqid()), 0, 8)), '0', $this->admin);

        app(MeterReadingService::class)->record($meter, '25', '2026-03-20 10:00:00', 'actual', $this->admin);

        $tariff = $this->makeTariff($this->council, $this->admin, 'water');
        $services->assignTariff($customer['account'], $tariff, '2026-01-01');

        $this->useCouncil($this->council, $this->membership);

        $run = app(BillingService::class)->preview('water', '2026-03-01', '2026-03-31', ['idempotency_key' => 'bill-for-'.uniqid()], $this->admin);
        app(BillingService::class)->approve($run, $this->approver);
        app(BillingService::class)->post($run, $this->approver);

        return Bill::where('customer_account_id', $customer['account']->id)->firstOrFail();
    }

    /** @param array<string, mixed> $payload */
    private function postSandboxWebhook(array $payload)
    {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('manicerp.payments.sandbox.webhook_secret'));

        return $this->call('POST', '/api/v1/webhooks/payments/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SANDBOX_TIMESTAMP' => $timestamp,
            'HTTP_X_SANDBOX_SIGNATURE' => $signature,
        ], $body);
    }
}
