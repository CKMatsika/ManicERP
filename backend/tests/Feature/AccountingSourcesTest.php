<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\AccountingRequirement;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\AccountingStandard;
use App\Modules\Accounting\Services\AccountingSourceService;
use App\Modules\Accounting\Services\AccountingStandardsService;
use App\Modules\Platform\Models\Council;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class AccountingSourcesTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    protected function setUp(): void
    {
        parent::setUp();

        $this->council = $this->makeCouncil();
        $admin = $this->makeUser('sources@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($admin, $this->council, $role);
        $this->actingAsStaff($admin, $this->council, $membership);

        app(AccountingStandardsService::class)->seedDefaults();
    }

    public function test_supplied_documents_are_recorded_with_hashes_and_internal_titles(): void
    {
        $result = app(AccountingSourceService::class)->seed();

        $this->assertSame(2, $result['documents']);
        $this->assertSame(15, $result['requirements']);

        $zfrm = AccountingSourceDocument::where('file_name', 'zfrm-soft-copy-1-1.pdf')->firstOrFail();
        $this->assertSame('Zimbabwe Financial Reporting Manual', $zfrm->internal_title);
        $this->assertSame('7EEA17A0F8B2983D523A83BFE7EBB614E14FA5F9F340F9D87DE50CE64AF648C9', $zfrm->file_hash);
        $this->assertSame(189, $zfrm->page_count);
        $this->assertSame('examined', $zfrm->examination_status);
        $this->assertStringContainsString('Zimbabwe Treasury', (string) $zfrm->issuing_authority);

        $financeAct = AccountingSourceDocument::where('file_name', 'finance-act-2023-no-13.pdf')->firstOrFail();
        $this->assertSame('Finance Act, 2023', $financeAct->internal_title);
        $this->assertSame('Act No. 13 of 2023', $financeAct->edition);
        $this->assertStringContainsString('Parliament', (string) $financeAct->issuing_authority);
        $this->assertArrayHasKey('received_filename', $financeAct->metadata);
        $this->assertStringContainsString('2024', $financeAct->metadata['received_filename']);
    }

    public function test_zfrm_scope_and_early_adoption_findings_are_extracted_with_section_and_page(): void
    {
        app(AccountingSourceService::class)->seed();

        $scope = AccountingRequirement::where('requirement_code', 'ZFRM-1.2.2')->firstOrFail();
        $this->assertSame('1.2.2', $scope->section_ref);
        $this->assertStringContainsString('PDF p.14', $scope->page_ref);
        $this->assertStringContainsString('Rural District Councils', $scope->requirement_text);
        $this->assertStringContainsString('Urban Councils', $scope->requirement_text);
        $this->assertTrue($scope->requirement_extracted);
        $this->assertFalse($scope->applicability_verified);
        $this->assertFalse($scope->policy_approved);
        $this->assertFalse($scope->implementation_tested);

        $early = AccountingRequirement::where('requirement_code', 'ZFRM-1.3.5')->firstOrFail();
        $this->assertSame('1.3.5', $early->section_ref);
        foreach (['IPSAS 43', 'IPSAS 44', 'IPSAS 45', 'IPSAS 46', 'IPSAS 47', 'IPSAS 48'] as $standard) {
            $this->assertStringContainsString($standard, $early->requirement_text);
        }
        $this->assertFalse($early->applicability_verified);
    }

    public function test_uploaded_documents_do_not_mark_the_twelve_standards_verified(): void
    {
        app(AccountingSourceService::class)->seed();

        $this->assertSame(13, AccountingStandard::count());
        $this->assertSame(0, AccountingStandard::where('verification_status', 'verified')->count());
        $this->assertSame(0, AccountingStandard::whereNotNull('approved_at')->count());

        $revenue = AccountingStandard::where('code', 'IPSAS 47')->firstOrFail();
        $this->assertSame('unverified', $revenue->verification_status);
        $this->assertSame('1.3.5', $revenue->source_section);
        $this->assertNotNull($revenue->source_document_id);
        $this->assertNotNull($revenue->zfrm_note);
    }

    public function test_historical_finance_act_rates_are_recorded_but_not_activated(): void
    {
        app(AccountingSourceService::class)->seed();

        $rates = AccountingRequirement::where('requirement_code', 'FIN-2023-RATES')->firstOrFail();
        $this->assertSame('historical_rates_not_activated', $rates->applicability_status);
        $this->assertStringContainsString('Not verified', $rates->blocker.' Not verified');
        $this->assertSame('not_implemented', $rates->implementation_status);

        $this->assertDatabaseCount('tariff_versions', 0, 'tenant');
        $this->assertDatabaseCount('revenue_policies', 0, 'tenant');
    }

    public function test_source_seeding_is_idempotent(): void
    {
        $first = app(AccountingSourceService::class)->seed();
        $second = app(AccountingSourceService::class)->seed();

        $this->assertSame($first, $second);
        $this->assertSame(2, AccountingSourceDocument::count());
        $this->assertSame(15, AccountingRequirement::count());
    }

    public function test_a_document_hash_mismatch_refuses_to_seed(): void
    {
        $fixture = require database_path('seeders/data/accounting_sources.php');
        $fixture['documents']['zfrm']['file_hash'] = str_repeat('0', 64);

        $path = storage_path('app/private/test-accounting-sources.php');
        file_put_contents($path, '<?php return '.var_export($fixture, true).';');

        try {
            $this->expectException(\DomainException::class);
            app(AccountingSourceService::class)->seed($path);
        } finally {
            @unlink($path);
        }
    }
}
