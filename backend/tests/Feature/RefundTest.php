<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Revenue\Contracts\RefundGateway;
use App\Modules\Revenue\Gateways\SandboxRefundGateway;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Models\Receipt;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\AllocationService;
use App\Modules\Revenue\Services\CashierService;
use App\Modules\Revenue\Services\PaymentService;
use App\Modules\Revenue\Services\RefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $preparer;

    private User $approver;

    private $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 15));
        $this->council = $this->makeCouncil();
        $this->preparer = $this->makeUser('refund@test.local');
        $this->approver = $this->makeUser('refund-approver@test.local');
        $role = $this->makeRole($this->council, 'refund_admin', ['*']);
        $membership = $this->makeMembership($this->preparer, $this->council, $role);
        $this->makeMembership($this->approver, $this->council, $role);
        $this->actingAsStaff($this->preparer, $this->council, $membership);
        $this->seedAccounting($this->council, $this->preparer);
        $this->account = $this->makeCustomer($this->council, $this->preparer)['account'];
    }

    private function payment(int $amount = 10000): Payment
    {
        $session = app(CashierService::class)->open($this->preparer);

        return app(PaymentService::class)->captureCash($this->account, $amount, 'bank_transfer', 'refund-test', $session, $this->preparer);
    }

    private function requestRefund(Payment $payment, int $amount = 3000, string $key = 'refund-1'): Refund
    {
        return app(RefundService::class)->request($this->account->id, $amount, paymentId: $payment->id, requester: $this->preparer, idempotencyKey: $key);
    }

    public function test_partial_refund_is_traced_and_duplicate_settlement_preserves_receipt(): void
    {
        $payment = $this->payment();
        $service = app(RefundService::class);
        $refund = $this->requestRefund($payment);
        $this->assertTrue($refund->is($this->requestRefund($payment)));
        $service->approve($refund, $this->approver);
        $settled = $service->settle($refund, $this->approver);
        $this->assertSame('settled', $settled->status);
        $this->assertSame(7000, $payment->unallocatedMinor());
        $this->assertSame('issued', Receipt::firstOrFail()->status);
        $this->assertSame(1, Receipt::count());
        $this->assertTrue(JournalEntry::findOrFail($settled->journal_entry_id)->isBalanced());
        $this->assertTrue($settled->is($service->settle($refund, $this->approver)));
        $this->assertSame(1, JournalEntry::where('source_type', 'refund')->count());
        $this->assertSame(7000, $service->availableForAccount($this->account->id, $payment->id));
    }

    public function test_reserved_refunds_cannot_overspend_a_payment(): void
    {
        $payment = $this->payment();
        $this->requestRefund($payment, 7000);
        $this->expectException(\DomainException::class);
        $this->requestRefund($payment, 4000, 'refund-2');
    }

    public function test_stale_consecutive_approvals_cannot_approve_twice(): void
    {
        $refund = $this->requestRefund($this->payment());
        $stale = $refund->fresh();
        app(RefundService::class)->approve($refund, $this->approver);
        $this->expectException(\DomainException::class);
        app(RefundService::class)->approve($stale, $this->approver);
    }

    public function test_failed_sandbox_settlement_retains_reservation_and_can_retry(): void
    {
        $payment = $this->payment();
        $refund = $this->requestRefund($payment);
        app(RefundService::class)->approve($refund, $this->approver);
        $this->app->bind(RefundGateway::class, fn () => new class extends SandboxRefundGateway
        {
            public function settle(Refund $refund): array
            {
                throw new \RuntimeException('Simulated timeout');
            }
        });
        $failed = app(RefundService::class)->settle($refund, $this->approver);
        $this->assertSame('failed', $failed->status);
        $this->assertSame(7000, app(RefundService::class)->availableForAccount($this->account->id, $payment->id));
        $this->assertSame(0, JournalEntry::where('source_type', 'refund')->count());
        $this->app->bind(RefundGateway::class, SandboxRefundGateway::class);
        $this->assertSame('settled', app(RefundService::class)->settle($failed, $this->approver)->status);
    }

    public function test_requester_cannot_approve_and_unprivileged_actor_cannot_settle(): void
    {
        $refund = $this->requestRefund($this->payment());
        try {
            app(RefundService::class)->approve($refund, $this->preparer);
            $this->fail('Self approval allowed');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Separation of duties', $e->getMessage());
        }
        $outsider = $this->makeUser('refund-outsider@test.local');
        $this->expectException(AuthorizationException::class);
        app(RefundService::class)->settle($refund, $outsider);
    }

    public function test_locked_period_blocks_settlement_without_changing_approval_or_receipt(): void
    {
        $refund = $this->requestRefund($this->payment());
        app(RefundService::class)->approve($refund, $this->approver);
        FinancialPeriod::where('code', '2026-02')->update(['status' => 'locked']);
        try {
            app(RefundService::class)->settle($refund, $this->approver);
            $this->fail('Locked period allowed');
        } catch (\DomainException) {
            $this->assertSame('approved', $refund->fresh()->status);
        }
        $this->assertSame(0, JournalEntry::where('source_type', 'refund')->count());
    }

    public function test_allocated_payment_requires_explicit_reversal_then_refund_and_invoice_remains_due(): void
    {
        $bill = Bill::create(['bill_number' => 'REF-BILL', 'customer_account_id' => $this->account->id, 'service_type' => 'rates',
            'period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'issue_date' => '2026-02-01', 'due_date' => '2026-02-28',
            'status' => 'issued', 'currency_code' => 'USD', 'total_minor' => 10000, 'paid_minor' => 0, 'balance_minor' => 10000]);
        app(PostingService::class)->post(['journal_type' => 'billing', 'entry_date' => '2026-02-01', 'description' => 'Bill',
            'lines' => [['account_code' => '1310', 'debit_minor' => 10000], ['account_code' => '4010', 'credit_minor' => 10000]]]);
        $payment = $this->payment();
        try {
            $this->requestRefund($payment);
            $this->fail('Allocated money refundable');
        } catch (\DomainException) {
            $this->assertSame(0, $payment->unallocatedMinor());
        }
        $allocation = $payment->allocations()->firstOrFail();
        app(AllocationService::class)->reverseAllocation($allocation, 'Receipt allocated to wrong invoice', $this->approver);
        $this->assertSame(10000, $bill->fresh()->balance_minor);
        $refund = $this->requestRefund($payment, 4000);
        app(RefundService::class)->approve($refund, $this->approver);
        app(RefundService::class)->settle($refund, $this->approver);
        $this->assertSame(10000, $bill->fresh()->balance_minor);
        $this->assertSame(6000, $payment->unallocatedMinor());
        $this->assertSame('issued', $payment->receipt->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'allocation_reversal')->count());
        $this->assertSame(6000, app(AllocationService::class)->allocate($payment));
        $this->assertSame(4000, $bill->fresh()->balance_minor);
    }

    public function test_payment_refund_does_not_consume_opening_credit_or_deposits(): void
    {
        $credit = AccountCredit::create(['customer_account_id' => $this->account->id, 'type' => 'deposit', 'source' => 'opening_balance', 'amount_minor' => 9000,
            'remaining_minor' => 9000, 'currency_code' => 'USD', 'status' => 'open', 'effective_date' => '2026-02-01']);
        $refund = $this->requestRefund($this->payment());
        app(RefundService::class)->approve($refund, $this->approver);
        app(RefundService::class)->settle($refund, $this->approver);
        $this->assertSame(9000, $credit->fresh()->remaining_minor);
        $this->assertSame(0, app(RefundService::class)->availableForAccount($this->account->id));
    }

    public function test_high_value_refund_requires_the_high_approval_permission(): void
    {
        $refund = $this->requestRefund($this->payment(200000), 150000);
        $role = $this->makeRole($this->council, 'low_refund_approver', ['revenue.refund.approve']);
        Membership::where('user_id', $this->approver->id)->update(['role_id' => $role->id]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('threshold');
        app(RefundService::class)->approve($refund, $this->approver);
    }

    public function test_citizen_refund_status_is_only_visible_for_a_linked_account(): void
    {
        $refund = $this->requestRefund($this->payment());
        $citizen = $this->makeUser('refund-citizen@test.local');
        $role = $this->makeRole($this->council, 'refund_citizen', ['customer.portal.access']);
        $membership = $this->makeMembership($citizen, $this->council, $role, 'citizen');
        $this->actingAsCitizen($citizen, $this->council, $membership);
        $this->get('/portal/accounts/'.$this->account->id)->assertNotFound();
        PortalAccountLink::create(['user_id' => $citizen->id, 'customer_account_id' => $this->account->id,
            'relationship' => 'owner', 'status' => 'verified', 'verification_method' => 'staff', 'requested_at' => now(), 'verified_at' => now()]);
        $this->get('/portal/accounts/'.$this->account->id)->assertOk()->assertInertia(fn ($page) => $page->where('refunds.0.refund_number', $refund->refund_number)->where('refunds.0.sandbox', true));
    }
}
