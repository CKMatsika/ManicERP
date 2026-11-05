<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\ReportingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AccountingPostingTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $user;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $this->user = $this->makeUser('finance@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $this->membership = $this->makeMembership($this->user, $this->council, $role);

        $this->actingAsStaff($this->user, $this->council, $this->membership);
        $this->seedAccounting($this->council, $this->user);
    }

    public function test_balanced_journal_posts_and_trial_balance_balances(): void
    {
        $entry = $this->postSimpleEntry(150_000);

        $this->assertSame('posted', $entry->status);
        $this->assertSame(150_000, $entry->total_debit_minor);
        $this->assertSame(150_000, $entry->total_credit_minor);
        $this->assertCount(2, $entry->lines);

        $report = app(ReportingService::class)->trialBalance('2026-03-31');

        $this->assertTrue($report['totals']['balanced']);
        $this->assertSame($report['totals']['debit_minor'], $report['totals']['credit_minor']);
        $this->assertGreaterThan(0, $report['totals']['debit_minor']);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unbalanced');

        app(PostingService::class)->post([
            'journal_type' => 'manual',
            'entry_date' => '2026-03-15',
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_code' => '1110', 'debit_minor' => 100_000],
                ['account_code' => '4010', 'credit_minor' => 90_000],
            ],
        ]);
    }

    public function test_posting_to_a_closed_period_is_rejected(): void
    {
        FinancialPeriod::where('code', '2026-03')->update(['status' => 'locked']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('locked');

        $this->postSimpleEntry(10_000, '2026-03-15');
    }

    public function test_posted_entry_is_immutable_and_reversal_is_linked(): void
    {
        $entry = $this->postSimpleEntry(50_000);

        try {
            $entry->description = 'Changed description';
            $entry->save();
            $this->fail('Posted entry should not be editable.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $reversal = app(PostingService::class)->reverse($entry, 'Correction of misposting', '2026-03-16', $this->user->id);

        $entry->refresh();

        $this->assertNotNull($entry->reversed_by_id);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertSame($entry->total_debit_minor, $reversal->total_credit_minor);

        $report = app(ReportingService::class)->trialBalance('2026-03-31');
        $this->assertTrue($report['totals']['balanced']);
    }

    public function test_double_reversal_is_rejected(): void
    {
        $entry = $this->postSimpleEntry(20_000);
        app(PostingService::class)->reverse($entry, 'First reversal', '2026-03-16', $this->user->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been reversed');

        app(PostingService::class)->reverse($entry->fresh(), 'Second reversal', '2026-03-17', $this->user->id);
    }

    public function test_idempotency_key_returns_the_existing_entry(): void
    {
        $payload = [
            'journal_type' => 'manual',
            'entry_date' => '2026-03-15',
            'description' => 'Idempotent entry',
            'idempotency_key' => 'test-idempotency-key',
            'lines' => [
                ['account_code' => '1110', 'debit_minor' => 42_000],
                ['account_code' => '4090', 'credit_minor' => 42_000],
            ],
        ];

        $first = app(PostingService::class)->post($payload);
        $second = app(PostingService::class)->post($payload);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, JournalEntry::where('idempotency_key', 'test-idempotency-key')->count());
    }

    public function test_unmapped_account_code_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown chart account');

        app(PostingService::class)->post([
            'journal_type' => 'manual',
            'entry_date' => '2026-03-15',
            'description' => 'Bad account',
            'lines' => [
                ['account_code' => '9999', 'debit_minor' => 1_000],
                ['account_code' => '4010', 'credit_minor' => 1_000],
            ],
        ]);
    }

    private function postSimpleEntry(int $amountMinor, string $date = '2026-03-15'): JournalEntry
    {
        return app(PostingService::class)->post([
            'journal_type' => 'manual',
            'entry_date' => $date,
            'description' => 'Test journal entry',
            'lines' => [
                ['account_code' => '1110', 'debit_minor' => $amountMinor, 'memo' => 'Debit cash'],
                ['account_code' => '4010', 'credit_minor' => $amountMinor, 'memo' => 'Credit rates revenue'],
            ],
        ]);
    }
}
