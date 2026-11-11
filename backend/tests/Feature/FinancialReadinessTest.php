<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Models\RevenuePolicy;
use App\Modules\Accounting\Services\AccountingSourceService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\ReportMappingService;
use App\Modules\Accounting\Services\RevenuePolicyService;
use App\Modules\Ai\Models\PolicyDocument;
use App\Modules\Ai\Services\PolicyAssistantService;
use App\Modules\Platform\Services\TenantManager;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class FinancialReadinessTest extends TestCase
{
    use BuildsMunicipalContext;

    private User $preparer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $council = $this->makeCouncil();
        $this->preparer = $this->makeUser('readiness@test.local');
        $this->approver = $this->makeUser('readiness-approver@test.local');
        $role = $this->makeRole($council, 'readiness_admin', ['*']);
        $member = $this->makeMembership($this->preparer, $council, $role);
        $this->makeMembership($this->approver, $council, $role);
        $this->actingAsStaff($this->preparer, $council, $member);
        $this->seedAccounting($council, $this->preparer);
        $this->useCouncil($council, $member);
        app(AccountingSourceService::class)->seed();
    }

    private function definition(string $code = 'performance'): ReportDefinition
    {
        app(ReportMappingService::class)->templates($this->preparer);
        $definition = ReportDefinition::where('code', $code)->firstOrFail();
        $definition->update(['effective_from' => '2026-01-01']);

        return $definition;
    }

    private function mapping(ReportDefinition $definition, string $line, string $account): void
    {
        app(ReportMappingService::class)->map($definition->lines()->where('line_code', $line)->firstOrFail(), ['gl_account_code' => $account, 'effective_from' => '2026-01-01'], $this->preparer);
    }

    private function journal(): void
    {
        app(PostingService::class)->post(['journal_type' => 'general', 'entry_date' => '2026-02-10', 'description' => 'Report fixture',
            'lines' => [['account_code' => '1110', 'debit_minor' => 10000], ['account_code' => '4010', 'credit_minor' => 10000]]]);
        app(PostingService::class)->post(['journal_type' => 'general', 'entry_date' => '2026-02-11', 'description' => 'Nature expense fixture',
            'lines' => [['account_code' => '5010', 'debit_minor' => 2500], ['account_code' => '1110', 'credit_minor' => 2500]]]);
    }

    public function test_mapped_performance_reconciles_to_trial_balance_with_nature_and_function_notes(): void
    {
        $definition = $this->definition();
        $this->mapping($definition, 'taxes', '4010');
        $this->mapping($definition, 'employee', '5010');
        app(ReportMappingService::class)->approve($definition, $this->approver);
        $this->journal();
        $report = app(ReportMappingService::class)->statement($definition->fresh(), '2026-02-01', '2026-02-28', '2026-01-01', '2026-01-28');
        $this->assertTrue($report['current']['reconciles']);
        $this->assertSame(10000, $report['current']['lines']['taxes']['closing_minor']);
        $this->assertSame(2500, $report['current']['lines']['employee']['closing_minor']);
        $this->assertSame(-7500, $report['current']['trial_balance_net_minor']);
        $this->assertSame(2500, $report['current']['functional_expense_notes']['UNMAPPED FUNCTION']);
        $this->assertNull($report['compliance_statement']);
        $this->assertStringContainsString('DEMONSTRATION', implode(' ', $report['blockers']));
    }

    public function test_unmapped_and_ambiguous_accounts_are_reported_instead_of_dropped(): void
    {
        $definition = $this->definition();
        $this->journal();
        $report = app(ReportMappingService::class)->statement($definition, '2026-02-01', '2026-02-28');
        $this->assertFalse($report['current']['reconciles']);
        $this->assertContains('4010', $report['current']['unmapped_accounts']);
        $this->mapping($definition, 'taxes', '4010');
        $this->mapping($definition, 'other', '4010');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ambiguous');
        app(ReportMappingService::class)->approve($definition, $this->approver);
    }

    public function test_approved_mapping_is_versioned_and_cannot_be_changed_in_place(): void
    {
        $definition = $this->definition();
        $this->mapping($definition, 'taxes', '4010');
        $approved = app(ReportMappingService::class)->approve($definition, $this->approver);
        $copy = app(ReportMappingService::class)->nextVersion($approved, $this->preparer);
        $this->assertSame(2, $copy->version);
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $approved->version);
        $this->expectException(\DomainException::class);
        $this->mapping($approved, 'other', '4020');
    }

    public function test_invalid_comparative_periods_are_rejected_and_budget_is_explicitly_unavailable(): void
    {
        $definition = $this->definition('budget');
        $report = app(ReportMappingService::class)->statement($definition, '2026-02-01', '2026-02-28');
        $this->assertFalse($report['current']['budget_amounts_available']);
        $this->assertStringContainsString('Budget register', implode(' ', $report['blockers']));
        $this->expectException(\DomainException::class);
        app(ReportMappingService::class)->statement($definition, '2026-02-01', '2026-02-28', '2026-02-01', '2026-02-28');
    }

    private function policy(array $overrides = []): RevenuePolicy
    {
        return app(RevenuePolicyService::class)->draft([...[
            'charge_type' => 'water', 'recognition_method' => 'deferred', 'receivable_account_code' => '1320', 'revenue_account_code' => '4020', 'deferred_account_code' => '2050',
            'source_document_id' => AccountingSourceDocument::where('internal_title', 'Zimbabwe Financial Reporting Manual')->value('id'),
            'source_ref' => '§22.4 / PDF pp.96-97, IPSAS 47 assessment', 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30',
            'notes' => 'Test council assessment: binding arrangement with an unsatisfied service compliance obligation. Recognition requires evidence of fulfilment.',
            'recognition_rule' => ['trigger_event' => 'manual_evidence', 'description' => 'Recognize upon evidenced satisfaction of the service obligation.', 'evidence_required' => true],
        ], ...$overrides], $this->preparer);
    }

    public function test_revenue_policies_are_effective_dated_independently_approved_versions(): void
    {
        $first = $this->policy();
        app(RevenuePolicyService::class)->approve($first, $this->approver);
        $second = $this->policy(['effective_from' => '2026-07-01', 'effective_to' => null, 'recognition_method' => 'immediate']);
        app(RevenuePolicyService::class)->approve($second, $this->approver);
        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, app(RevenuePolicyService::class)->resolve('water', '2026-06-30')->id);
        $this->assertSame($second->id, app(RevenuePolicyService::class)->resolve('water', '2026-07-01')->id);
        $overlap = $this->policy();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('overlap');
        app(RevenuePolicyService::class)->approve($overlap, $this->approver);
    }

    public function test_revenue_policy_requires_separation_of_duties(): void
    {
        $this->expectException(\DomainException::class);
        app(RevenuePolicyService::class)->approve($this->policy(), $this->preparer);
    }

    public function test_successor_closes_open_ended_policy_without_rewriting_its_accounts(): void
    {
        $first = $this->policy(['effective_to' => null]);
        app(RevenuePolicyService::class)->approve($first, $this->approver);
        $second = $this->policy(['effective_from' => '2026-07-01', 'effective_to' => null, 'recognition_method' => 'immediate']);
        app(RevenuePolicyService::class)->approve($second, $this->approver);
        $this->assertSame('2026-06-30', $first->fresh()->effective_to->toDateString());
        $this->assertSame('deferred', $first->fresh()->recognition_method);
        $this->expectException(\DomainException::class);
        $first->fresh()->update(['deferred_account_code' => '2100']);
    }

    public function test_position_and_equity_reconcile_including_the_unclosed_result(): void
    {
        $position = $this->definition('position');
        $this->mapping($position, 'cash', '1110');
        app(ReportMappingService::class)->approve($position, $this->approver);
        $equity = $this->definition('equity');
        app(ReportMappingService::class)->approve($equity, $this->approver);
        $this->journal();
        $p = app(ReportMappingService::class)->statement($position->fresh(), '2026-02-01', '2026-02-28')['current'];
        $e = app(ReportMappingService::class)->statement($equity->fresh(), '2026-02-01', '2026-02-28')['current'];
        $this->assertTrue($p['reconciles']);
        $this->assertSame(7500, $p['lines']['cash']['closing_minor']);
        $this->assertSame(7500, $p['unclosed_result_minor']);
        $this->assertSame(7500, $e['lines']['unclosed_result']['movement_minor']);
        $this->assertSame(7500, $e['lines']['unclosed_result']['closing_minor']);
    }

    public function test_invalid_report_sign_cannot_be_approved(): void
    {
        $definition = $this->definition();
        $definition->lines()->firstOrFail()->update(['sign' => 'invert']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sign');
        app(ReportMappingService::class)->approve($definition, $this->approver);
    }

    public function test_assistant_guidance_has_section_page_and_is_not_council_policy(): void
    {
        app(AccountingSourceService::class)->publishGuidance($this->approver);
        $sources = app(PolicyAssistantService::class)->retrieve('early adopted IPSAS');
        $this->assertNotEmpty($sources);
        $this->assertSame('Source guidance; not an approved council policy', $sources[0]['authority_label']);
        $this->assertNotEmpty($sources[0]['section_ref']);
        $this->assertNotEmpty($sources[0]['page_ref']);
        $this->assertNotEmpty($sources[0]['source_hash']);
        $this->assertSame(0, PolicyDocument::where('approved_policy', true)->count());
        PolicyDocument::query()->update(['effective_to' => now()->subDay()->toDateString()]);
        $this->assertSame([], app(PolicyAssistantService::class)->retrieve('early adopted IPSAS'));
    }

    public function test_staff_pages_and_new_actions_enforce_permissions(): void
    {
        $this->get('/accounting/readiness')->assertOk();
        $this->get('/revenue/refunds')->assertOk();
        $council = app(TenantManager::class)->requireCouncil();
        $user = $this->makeUser('readiness-denied@test.local');
        $role = $this->makeRole($council, 'no_finance', ['revenue.account.view']);
        $membership = $this->makeMembership($user, $council, $role);
        $this->actingAsStaff($user, $council, $membership);
        $this->get('/accounting/readiness')->assertForbidden();
        $this->post('/accounting/opening-balances/missing/post')->assertForbidden();
        $this->post('/revenue/refunds/missing/settle')->assertForbidden();
    }
}
