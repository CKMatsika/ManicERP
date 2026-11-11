<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\FinancialYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSourceService;
use App\Modules\Accounting\Services\RevenuePolicyService;
use App\Modules\Ai\Services\PolicyAssistantService;
use App\Modules\Customer\Models\PortalAccountLink;
use App\Modules\Licensing\Contracts\LicenceAiProvider;
use App\Modules\Licensing\Contracts\LicenceNotificationAdapter;
use App\Modules\Licensing\Jobs\LicenceMaintenanceJob;
use App\Modules\Licensing\Jobs\SendLicenceNotification;
use App\Modules\Licensing\Models\Licence;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceApplicationDocument;
use App\Modules\Licensing\Models\LicenceCertificate;
use App\Modules\Licensing\Models\LicenceCharge;
use App\Modules\Licensing\Models\LicenceNotification;
use App\Modules\Licensing\Services\EmailLicenceNotificationAdapter;
use App\Modules\Licensing\Services\LicenceAiAssistantService;
use App\Modules\Licensing\Services\LicenceChargeService;
use App\Modules\Licensing\Services\LicenceNotificationService;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\Payment;
use App\Modules\Revenue\Services\PaymentService;
use App\Modules\Revenue\Services\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class LicensingWorkflowTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $citizen;

    private User $preparer;

    private User $inspector;

    private User $approver;

    private User $issuer;

    private Membership $citizenMembership;

    private $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 15));
        $this->council = $this->makeCouncil(['code' => 'LIC']);
        $this->council->forceFill(['is_demo' => true])->save();
        $this->citizen = $this->makeUser('lic-citizen@test.local', 'Licence Applicant');
        $this->preparer = $this->makeUser('lic-reviewer@test.local', 'Licence Reviewer');
        $this->inspector = $this->makeUser('lic-inspector@test.local', 'Licence Inspector');
        $this->approver = $this->makeUser('lic-approver@test.local', 'Licence Approver');
        $this->issuer = $this->makeUser('lic-issuer@test.local', 'Certificate Issuer');
        $citizenRole = $this->makeRole($this->council, 'lic_citizen', ['*']);
        $staffRole = $this->makeRole($this->council, 'lic_staff', ['*']);
        $this->citizenMembership = $this->makeMembership($this->citizen, $this->council, $citizenRole, 'citizen');
        foreach ([$this->preparer, $this->inspector, $this->approver, $this->issuer] as $staff) {
            $this->makeMembership($staff, $this->council, $staffRole);
        }
        $this->seedAccounting($this->council, $this->preparer);
        $this->account = $this->makeCustomer($this->council, $this->preparer)['account'];
        PortalAccountLink::create([
            'user_id' => $this->citizen->id, 'customer_account_id' => $this->account->id,
            'relationship' => 'representative', 'status' => 'verified', 'verification_method' => 'test',
            'requested_at' => now(), 'verified_at' => now(),
        ]);
        Storage::fake('documents');
    }

    public function test_citizen_to_certificate_to_public_verification_and_renewal_preserves_history(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $form = ['trading_name' => 'Harare Demo Traders', 'activity' => 'Demonstration retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $this->uploadEvidence($draft, 'identity');
        $this->assertSame(2, LicenceApplicationDocument::where('licence_application_id', $draft->id)->where('document_type', 'identity')->max('version'));

        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-e2e-submit', $draft->id);
        $this->assertSame('submitted', $application->status);
        $this->assertSame(2500, $application->fee_amount_minor);
        $this->assertSame($application->id, $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-e2e-submit', $draft->id)->id);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->requestInformation($application, 'Please confirm the proposed activity details.', $this->preparer);
        $this->assertSame('information_requested', $application->fresh()->status);
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $application = $service->respondToInformation($this->citizen, $this->account->id, $application->fresh(), $form);
        $this->assertSame('submitted', $application->status);
        $citizenToken = $this->citizen->createToken('licensing-api-test', ['council:'.$this->council->code, 'portal'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$citizenToken)->getJson('/api/v1/licensing/types')->assertOk()->assertJsonPath('data.0.code', 'DEMO_TRADER');
        $this->getJson('/api/v1/licensing/applications')->assertOk()->assertJsonPath('data.0.reference', $application->reference);
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);

        $newConfiguration = $type->versions()->first()->configuration;
        $newConfiguration['charges']['application_minor'] = 3000;
        $service->reviseType($type, ['configuration' => $newConfiguration, 'approval_status' => 'demonstration'], $this->preparer);
        $this->assertSame(2500, $application->fresh()->configuration_snapshot['charges']['application_minor']);
        $this->assertSame(2500, $application->fresh()->fee_amount_minor);

        $bill = Bill::findOrFail($application->fee_bill_id);
        $intent = $service->applicationPaymentIntent($this->citizen, $application, 'licence-e2e-payment')['intent'];
        $this->assertSame('pending', $intent->status);
        $payment = app(PaymentService::class)->settle($intent, 'succeeded', 'LIC-SBX-E2E', ['amount_minor' => 2500]);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(0, $bill->fresh()->balance_minor);
        $this->assertSame(1, $payment->receipt()->count());
        $this->assertSame('submitted', $application->fresh()->status, 'Verified payment must not approve an application.');

        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($application, $this->preparer);
        $inspection = $service->assignInspection($application->fresh(), $this->inspector, '2026-02-16 10:00:00');
        $this->actingAsStaff($this->inspector, $this->council, $this->inspector->memberships()->first());
        $service->completeInspection($inspection, ['site_accessible'], 'pass: demonstration check completed', $this->inspector);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->decide($application->fresh(), 'approved', 'Demonstration requirements reviewed', ['Display certificate on site'], $this->approver);
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        $certificate = $service->issue($application->fresh(), $this->issuer);
        $this->assertSame($certificate->id, $service->issue($application->fresh(), $this->issuer)->id);
        $this->assertSame('issued', $application->fresh()->status);
        $this->assertSame(1, LicenceCertificate::where('licence_id', $certificate->licence_id)->count());

        $verification = $service->publicVerification($certificate->verification_id);
        $this->assertTrue($verification['authentic']);
        $this->assertTrue($verification['currently_valid']);
        $this->assertArrayNotHasKey('identity_document', $verification);
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $this->get('/portal/licensing/certificates/'.$certificate->id)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$citizenToken)->get('/api/v1/licensing/certificates/'.$certificate->id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $this->get('/portal/licensing')->assertOk()->assertSee($application->reference);
        $this->get('/councils/LIC/licences/verify/'.$certificate->verification_id)->assertOk()->assertSee('VALID');

        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->lifecycle($certificate->licence, 'suspend', 'Demonstration suspension', $this->approver);
        $this->assertFalse($service->publicVerification($certificate->verification_id)['currently_valid']);
        $this->get('/councils/LIC/licences/verify/'.$certificate->verification_id)->assertOk()->assertSee('NOT CURRENTLY VALID')->assertHeader('cache-control', 'must-revalidate, no-cache, no-store, private');

        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->lifecycle($certificate->licence->fresh(), 'reinstate', 'Demonstration reinstatement before expiry', $this->approver);
        $this->travelTo(now()->setDate(2027, 1, 20));
        $yearStart = CarbonImmutable::create(2027, 1, 1);
        $financialYear = FinancialYear::create(['code' => '2027', 'start_date' => $yearStart->toDateString(), 'end_date' => $yearStart->addYear()->subDay()->toDateString(), 'status' => 'open']);
        for ($month = 0; $month < 12; $month++) {
            $start = $yearStart->addMonths($month);
            FinancialPeriod::create(['financial_year_id' => $financialYear->id, 'sequence' => $month + 1, 'code' => '2027-'.str_pad((string) ($month + 1), 2, '0', STR_PAD_LEFT), 'start_date' => $start->toDateString(), 'end_date' => $start->addMonth()->subDay()->toDateString(), 'status' => 'open']);
        }
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $renewalDraft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form, licenceId: $certificate->licence_id);
        $this->uploadEvidence($renewalDraft, 'identity');
        $this->uploadEvidence($renewalDraft, 'premises');
        $renewal = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-e2e-renewal', $renewalDraft->id);
        $this->assertSame('renewal', $renewal->attempt_type);
        $this->assertSame($certificate->licence_id, $renewal->licence_id);
        $this->assertSame(1, LicenceCertificate::where('licence_id', $certificate->licence_id)->count());
        $this->assertTrue($renewal->fresh()->configuration_snapshot['charges']['application_minor'] === 3000);
        $this->travelTo(now()->setDate(2027, 2, 15));
        LicenceMaintenanceJob::dispatchSync($this->council->id);
        $this->useCouncil($this->council);
        $this->assertFalse($service->publicVerification($certificate->verification_id)['currently_valid']);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->lifecycle($certificate->licence->fresh(), 'revoke', 'Demonstration revocation after expiry', $this->approver);
        $verificationAfterRevocation = $service->publicVerification($certificate->verification_id);
        $this->assertFalse($verificationAfterRevocation['currently_valid']);
        $this->assertSame('revoked', $verificationAfterRevocation['current_status']);
    }

    public function test_unlinked_representative_cannot_read_or_pay_an_application(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $type = app(LicensingService::class)->seedDemonstrationType($this->preparer);
        $draft = app(LicensingService::class)->saveDraft($this->citizen, $this->account->id, $type->code, ['trading_name' => 'Test', 'activity' => 'Retail']);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = app(LicensingService::class)->submit($this->citizen, $this->account->id, $type->code, ['trading_name' => 'Test', 'activity' => 'Retail'], 'licence-unauth', $draft->id);
        $evidenceId = LicenceApplicationDocument::where('licence_application_id', $application->id)->firstOrFail()->document_id;

        $intruder = $this->makeUser('lic-intruder@test.local');
        $role = $this->makeRole($this->council, 'lic_intruder', ['*']);
        $membership = $this->makeMembership($intruder, $this->council, $role, 'citizen');
        $this->actingAsCitizen($intruder, $this->council, $membership);
        $this->get('/portal/licensing')->assertOk()->assertDontSee($application->reference);
        $this->post('/portal/licensing/applications/'.$application->id.'/payment', ['idempotency_key' => 'intruder'])->assertNotFound();
        $this->get('/documents/'.$evidenceId)->assertRedirect();
        $token = $intruder->createToken('licensing-test', ['council:'.$this->council->code, 'portal'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/licensing/applications')->assertOk()->assertJsonCount(0, 'data');

        $unrelatedStaff = $this->makeUser('lic-unrelated-staff@test.local');
        $documentsOnly = $this->makeRole($this->council, 'documents_only', ['platform.documents.view']);
        $unrelatedMembership = $this->makeMembership($unrelatedStaff, $this->council, $documentsOnly);
        $this->actingAsStaff($unrelatedStaff, $this->council, $unrelatedMembership);
        $this->get('/documents/'.$evidenceId)->assertNotFound();
    }

    public function test_another_council_cannot_read_licensing_evidence_or_notifications(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $form = ['trading_name' => 'Tenant Isolation Demo', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-tenant-isolation', $draft->id);
        $evidenceId = LicenceApplicationDocument::where('licence_application_id', $application->id)->firstOrFail()->document_id;

        $otherCouncil = $this->makeCouncil(['code' => 'LICOTHER', 'database_name' => $this->secondaryTenantDatabase()]);
        $otherCitizenRole = $this->makeRole($otherCouncil, 'lic_other_citizen', [
            'customer.portal.access', 'licensing.notifications.view',
        ]);
        $otherMembership = $this->makeMembership($this->citizen, $otherCouncil, $otherCitizenRole, 'citizen');
        DB::connection('tenant')->commit();
        $this->useTenantTransaction = false;
        config(['database.connections.tenant.database' => $otherCouncil->database_name]);
        DB::purge('tenant');
        try {
            $otherStaff = $this->makeUser('lic-other-council-staff@test.local');
            $documentRole = $this->makeRole($otherCouncil, 'lic_other_document_reader', ['platform.documents.view']);
            $documentMembership = $this->makeMembership($otherStaff, $otherCouncil, $documentRole);
            $this->actingAsStaff($otherStaff, $otherCouncil, $documentMembership)
                ->get('/documents/'.$evidenceId)
                ->assertNotFound();

            $this->actingAsCitizen($this->citizen, $otherCouncil, $otherMembership);
            $this->get('/portal/licensing/notifications')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Licensing/Notifications')->has('notifications', 0));
            $token = $this->citizen->createToken('other-council-licensing', ['council:'.$otherCouncil->code, 'portal'])->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/v1/licensing/applications')
                ->assertOk()
                ->assertJsonCount(0, 'data');
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/v1/licensing/notifications')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        } finally {
            config(['database.connections.tenant.database' => $this->tenantDatabase()]);
            DB::purge('tenant');
            $tenant = DB::connection('tenant');
            $tables = $tenant->select("select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations'");
            $quotedTables = array_map(fn (object $row): string => '"'.str_replace('"', '""', $row->tablename).'"', $tables);
            if ($quotedTables !== []) {
                $tenant->statement('TRUNCATE TABLE '.implode(', ', $quotedTables).' CASCADE');
            }
        }
    }

    public function test_charge_waivers_require_authority_and_a_reason(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $application = $this->submitApplication($service, $type->code, [
            'trading_name' => 'Waiver Control Demo', 'activity' => 'Retail',
        ], 'licence-waiver-control');
        $charge = $application->charges()->firstOrFail();

        $reviewer = $this->makeUser('lic-waiver-reviewer@test.local');
        $reviewerRole = $this->makeRole($this->council, 'lic_waiver_reviewer', [
            'licensing.applications.view', 'licensing.applications.review',
        ]);
        $reviewerMembership = $this->makeMembership($reviewer, $this->council, $reviewerRole);
        $this->actingAsStaff($reviewer, $this->council, $reviewerMembership)
            ->post('/licensing/charges/'.$charge->id.'/waive', ['reason' => 'Not authorised'])
            ->assertForbidden();

        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        try {
            app(LicenceChargeService::class)->waive($charge, ' ', $this->approver);
            $this->fail('A waiver without a reason must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('reason is required', $exception->getMessage());
        }

        $waived = app(LicenceChargeService::class)->waive($charge, 'Approved demonstration hardship waiver', $this->approver);
        $this->assertSame('waived', $waived->fresh()->status);
        $this->assertSame('Approved demonstration hardship waiver', $waived->fresh()->waiver_reason);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'licensing.charge.waived', 'subject_id' => $charge->id,
        ], 'tenant');
        $creditNote = JournalEntry::where('source_type', 'licence_charge_credit_note')->where('source_id', $charge->id)->firstOrFail();
        $this->assertTrue($creditNote->isBalanced());
    }

    public function test_assistant_cites_only_independently_approved_effective_council_configuration(): void
    {
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $configuration = [
            'label' => 'Approved council configuration test', 'application_fields' => [['name' => 'trading_name', 'label' => 'Trading name', 'required' => true]],
            'required_documents' => [['code' => 'identity', 'label' => 'Identity evidence']], 'eligibility' => ['description' => 'Council review required'],
            'inspection' => ['required' => false], 'charges' => ['application_minor' => 2500, 'inspection_minor' => 0, 'issuance_minor' => 0, 'renewal_minor' => 2500, 'currency' => 'USD', 'refundable' => false],
            'validity_days' => 365, 'renewal_window_days' => 30, 'expiry_rules' => ['timezone' => 'Africa/Harare'],
            'revenue_recognition' => ['charge_type' => 'licence_application', 'method' => 'immediate'],
        ];
        $type = app(LicensingService::class)->createType([
            'code' => 'CITED_TRADER', 'name' => 'Cited trader licence', 'authority' => 'Council resolution test only',
            'effective_from' => '2026-01-01', 'approval_status' => 'draft', 'status' => 'draft', 'configuration' => $configuration,
        ], $this->preparer);
        $version = $type->versions()->firstOrFail();
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        app(LicensingService::class)->approveTypeVersion($version, $this->approver);

        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $guidance = app(PolicyAssistantService::class)->ask('Cited trader application trading name', $this->citizen);
        $this->assertNotEmpty($guidance['sources']);
        $this->assertSame('Approved licence requirements: Cited trader licence', $guidance['sources'][0]['title']);
        $this->assertSame('Council configuration version 1', $guidance['sources'][0]['page_ref']);

        $unsupported = app(PolicyAssistantService::class)->ask('Does a past conviction make me eligible?', $this->citizen);
        $this->assertNull($unsupported['answer']);
        $this->assertSame([], $unsupported['sources']);
        $this->assertSame('no_sources', $unsupported['notice']);
    }

    public function test_decision_is_blocked_until_fee_is_paid_and_inspection_passes(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $form = ['trading_name' => 'Blocked Demo', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-blocked', $draft->id);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($application, $this->preparer);
        $inspection = $service->assignInspection($application->fresh(), $this->inspector, null);
        $this->actingAsStaff($this->inspector, $this->council, $this->inspector->memberships()->first());
        $service->completeInspection($inspection, [], 'pass: inspected', $this->inspector);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        try {
            $service->decide($application->fresh(), 'approved', 'Ready', [], $this->approver);
            $this->fail('Application approval proceeded with an unpaid fee.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('fully paid', $exception->getMessage());
        }
        $this->assertSame('inspection_pending', $application->fresh()->status);
    }

    public function test_rejected_refundable_application_uses_shared_credit_note_and_sandbox_refund_workflows(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $configuration = $type->versions()->first()->configuration;
        $configuration['charges']['refundable'] = true;
        $configuration['refund_policy'] = ['refundable' => true, 'reason_required' => true];
        $configuration['inspection']['required'] = false;
        $configuration['approval_stages'] = ['review', 'approval', 'issuance'];
        $service->reviseType($type, ['configuration' => $configuration, 'approval_status' => 'demonstration'], $this->preparer);
        $form = ['trading_name' => 'Refund Demo', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-refund-flow', $draft->id);
        $intent = $service->applicationPaymentIntent($this->citizen, $application, 'licence-refund-payment')['intent'];
        $payment = app(PaymentService::class)->settle($intent, 'succeeded', 'LIC-SBX-REFUND', ['amount_minor' => 2500]);

        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($application, $this->preparer);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->decide($application->fresh(), 'rejected', 'Demonstration rejection', [], $this->approver);
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        $service->cancelApplicationFeeForRefund($application->fresh(), 'Approved fee cancellation after rejection', $this->issuer);
        $this->assertSame('void', Bill::findOrFail($application->fee_bill_id)->status);
        $this->assertSame(1, JournalEntry::where('source_type', 'licence_charge_credit_note')->count());

        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $refund = $service->requestApplicationRefund($this->citizen, $application->fresh(), 2500, $payment->id, 'licence-refund-request', 'Application rejected');
        app(RefundService::class)->approve($refund, $this->approver);
        $settled = app(RefundService::class)->settle($refund, $this->approver);
        $this->assertSame('settled', $settled->status);
        $this->assertSame('issued', $payment->receipt()->firstOrFail()->status);
        $this->assertTrue(JournalEntry::findOrFail($settled->journal_entry_id)->isBalanced());
        $this->assertSame($application->id, $settled->metadata['licence_application_id']);
    }

    public function test_expiry_uses_council_local_date_and_reminders_are_deduplicated(): void
    {
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $type = app(LicensingService::class)->seedDemonstrationType($this->preparer);
        $licence = Licence::create([
            'reference' => 'LIC-EXPIRY-1', 'licence_type_id' => $type->id, 'customer_account_id' => $this->account->id,
            'holder_name' => 'Expiry Test', 'validity_status' => 'active', 'valid_from' => '2025-02-16', 'valid_to' => '2026-02-15',
        ]);
        LicenceMaintenanceJob::dispatchSync($this->council->id);
        $this->assertSame('active', $licence->fresh()->validity_status);
        $this->travelTo(now()->setDate(2026, 2, 16));
        LicenceMaintenanceJob::dispatchSync($this->council->id);
        LicenceMaintenanceJob::dispatchSync($this->council->id);
        $this->assertSame('expired', $licence->fresh()->validity_status);
        $this->assertSame(1, LicenceNotification::where('dedupe_key', 'expiry:'.$licence->id.':2026-02-15:in_app')->count());
    }

    public function test_inspection_and_issuance_charges_use_snapshot_and_gate_their_stages(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $configuration = $type->versions()->first()->configuration;
        $configuration['inspection']['required'] = true;
        $configuration['charges']['inspection_minor'] = 1000;
        $configuration['charges']['issuance_minor'] = 500;
        $service->reviseType($type, ['configuration' => $configuration, 'approval_status' => 'demonstration'], $this->preparer);
        $form = ['trading_name' => 'Staged Fees Demo', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-stage-fees', $draft->id);
        $this->assertCount(1, $application->charges);
        $this->assertSame(2500, $application->charges->first()->amount_minor);
        $this->assertSame($application->licence_type_version_id, $application->charges->first()->licence_type_version_id);

        $intent = $service->applicationPaymentIntent($this->citizen, $application, 'stage-fee-app')['intent'];
        app(PaymentService::class)->settle($intent, 'succeeded', 'LIC-STAGE-APP', ['amount_minor' => 2500]);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($application, $this->preparer);
        $inspection = $service->assignInspection($application->fresh(), $this->inspector, null);
        $inspectionCharge = LicenceCharge::where('licence_application_id', $application->id)->where('stage', 'inspection')->firstOrFail();
        $this->assertSame(1000, $inspectionCharge->amount_minor);
        $this->actingAsStaff($this->inspector, $this->council, $this->inspector->memberships()->first());
        try {
            $service->completeInspection($inspection, ['site_accessible'], 'pass: test inspection', $this->inspector);
            $this->fail('Inspection proceeded with an unpaid stage charge.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('inspection charge', $exception->getMessage());
        }
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $inspectionIntent = $service->applicationPaymentIntent($this->citizen, $application, 'stage-fee-inspection', $inspectionCharge->id)['intent'];
        app(PaymentService::class)->settle($inspectionIntent, 'succeeded', 'LIC-STAGE-INSP', ['amount_minor' => 1000]);
        $this->actingAsStaff($this->inspector, $this->council, $this->inspector->memberships()->first());
        $service->completeInspection($inspection, ['site_accessible'], 'pass: test inspection', $this->inspector);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->decide($application->fresh(), 'approved', 'Approved after review', [], $this->approver);
        $issuanceCharge = LicenceCharge::where('licence_application_id', $application->id)->where('stage', 'issuance')->firstOrFail();
        $this->assertSame(500, $issuanceCharge->amount_minor);
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        try {
            $service->issue($application->fresh(), $this->issuer);
            $this->fail('Certificate issued with an unpaid issuance charge.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('issuance charge', $exception->getMessage());
        }
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $issuanceIntent = $service->applicationPaymentIntent($this->citizen, $application, 'stage-fee-issuance', $issuanceCharge->id)['intent'];
        app(PaymentService::class)->settle($issuanceIntent, 'succeeded', 'LIC-STAGE-ISSUE', ['amount_minor' => 500]);
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        $certificate = $service->issue($application->fresh(), $this->issuer);
        $this->assertSame('settled', app(LicenceChargeService::class)->view($issuanceCharge)['status']);
        $this->assertSame(3, $application->charges()->count());
        $this->assertTrue(JournalEntry::findOrFail(Bill::findOrFail($issuanceCharge->bill_id)->journal_entry_id)->isBalanced());
        $this->assertNotEmpty($certificate->certificate_reference);
    }

    public function test_deferred_fee_releases_once_and_rejected_refund_corrects_the_liability(): void
    {
        app(AccountingSourceService::class)->seed();
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $policy = app(RevenuePolicyService::class)->draft([
            'charge_type' => 'licence_application', 'recognition_method' => 'deferred', 'receivable_account_code' => '1320',
            'deferred_account_code' => '2050', 'revenue_account_code' => '4020', 'effective_from' => '2026-02-15',
            'source_document_id' => AccountingSourceDocument::where('internal_title', 'Zimbabwe Financial Reporting Manual')->value('id'),
            'source_ref' => 'Test council assessment, §22.4', 'notes' => 'Demonstration obligation is not satisfied until the configured certificate issue event.',
            'recognition_rule' => ['trigger_event' => 'certificate_issued', 'description' => 'Recognise after certificate issue with evidence.', 'evidence_required' => true],
        ], $this->preparer);
        app(RevenuePolicyService::class)->approve($policy, $this->approver);
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $configuration = $type->versions()->first()->configuration;
        $configuration['revenue_recognition']['method'] = 'deferred';
        $configuration['charges']['refundable'] = true;
        $configuration['refund_policy'] = ['refundable' => true, 'reason_required' => true];
        $configuration['inspection']['required'] = false;
        $configuration['approval_stages'] = ['review', 'approval', 'issuance'];
        $service->reviseType($type, ['configuration' => $configuration, 'approval_status' => 'demonstration'], $this->preparer);
        $form = ['trading_name' => 'Deferred Demo', 'activity' => 'Retail'];
        $first = $this->submitApplication($service, $type->code, $form, 'licence-deferred-release');
        $charge = $first->charges()->firstOrFail();
        $originalBill = Bill::findOrFail($charge->bill_id);
        $originalJournal = JournalEntry::with('lines.account')->findOrFail($originalBill->journal_entry_id);
        $this->assertSame(2500, $originalJournal->lines->where('account.code', '2050')->sum('credit_minor'));
        $this->assertSame(0, $originalJournal->lines->where('account.code', '4020')->sum('credit_minor'));
        $intent = $service->applicationPaymentIntent($this->citizen, $first, 'deferred-release-pay')['intent'];
        app(PaymentService::class)->settle($intent, 'succeeded', 'LIC-DEFERRED-ISSUE', ['amount_minor' => 2500]);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($first, $this->preparer);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->decide($first->fresh(), 'approved', 'Demonstration decision', [], $this->approver);
        try {
            app(LicenceChargeService::class)->releaseDeferred($charge, 2500, 'certificate_issued', 'licence-release-too-early', 'Certificate not issued yet', $this->approver);
            $this->fail('The configured release trigger has not occurred yet.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('not occurred', $exception->getMessage());
        }
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        $service->issue($first->fresh(), $this->issuer);
        $release = app(LicenceChargeService::class)->releaseDeferred($charge, 2500, 'certificate_issued', 'licence-release-once', 'Certificate issued with reference '.$first->reference, $this->issuer);
        $retry = app(LicenceChargeService::class)->releaseDeferred($charge, 2500, 'certificate_issued', 'licence-release-once', 'Certificate issued with reference '.$first->reference, $this->issuer);
        $this->assertSame($release->id, $retry->id);
        $releaseJournal = JournalEntry::with('lines.account')->findOrFail($release->journal_entry_id);
        $this->assertTrue($releaseJournal->isBalanced());
        $this->assertSame(2500, $releaseJournal->lines->where('account.code', '2050')->sum('debit_minor'));
        $this->assertSame(2500, $releaseJournal->lines->where('account.code', '4020')->sum('credit_minor'));

        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $second = $this->submitApplication($service, $type->code, $form, 'licence-deferred-reject');
        $secondCharge = $second->charges()->firstOrFail();
        $secondIntent = $service->applicationPaymentIntent($this->citizen, $second, 'deferred-reject-pay')['intent'];
        $payment = app(PaymentService::class)->settle($secondIntent, 'succeeded', 'LIC-DEFERRED-REFUND', ['amount_minor' => 2500]);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $service->review($second, $this->preparer);
        $this->actingAsStaff($this->approver, $this->council, $this->approver->memberships()->first());
        $service->decide($second->fresh(), 'rejected', 'Not eligible under demonstration configuration', [], $this->approver);
        $this->actingAsStaff($this->issuer, $this->council, $this->issuer->memberships()->first());
        $service->cancelApplicationFeeForRefund($second->fresh(), 'Rejected application credit note', $this->issuer, $secondCharge->id);
        $credit = JournalEntry::where('source_type', 'licence_charge_credit_note')->where('source_id', $secondCharge->id)->firstOrFail();
        $this->assertTrue($credit->isBalanced());
        $creditLines = $credit->lines()->with('account')->get();
        $this->assertSame(2500, $creditLines->where('account.code', '2050')->sum('debit_minor'));
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $refund = $service->requestApplicationRefund($this->citizen, $second->fresh(), 2500, $payment->id, 'licence-deferred-refund', 'Rejected by council', $secondCharge->id);
        app(RefundService::class)->approve($refund, $this->approver);
        $settledRefund = app(RefundService::class)->settle($refund, $this->approver);
        $this->assertSame('settled', $settledRefund->status);
        $this->assertSame($secondCharge->id, $settledRefund->metadata['licence_charge_id']);
        $this->assertTrue(JournalEntry::findOrFail($settledRefund->journal_entry_id)->isBalanced());
    }

    public function test_ai_extraction_and_correspondence_are_review_only_and_version_linked(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $form = ['trading_name' => 'Submitted Value', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-ai-review-only', $draft->id);
        $link = $application->documents()->where('document_type', 'identity')->firstOrFail();
        config(['manicerp.ai.enabled' => true, 'manicerp.ai.provider' => 'openai', 'manicerp.ai.model' => 'fixture', 'manicerp.ai.monthly_budget_minor' => 1000]);
        $fake = new class implements LicenceAiProvider
        {
            public function extract(Document $document, array $fields): array
            {
                return ['fields' => ['trading_name' => 'Extracted Candidate'], 'metadata' => ['provider' => 'fixture', 'model' => 'fixture']];
            }

            public function draft(string $draftType, array $context): array
            {
                return ['content' => 'Please clarify the recorded activity field.', 'metadata' => ['provider' => 'fixture', 'model' => 'fixture']];
            }
        };
        $this->app->instance(LicenceAiProvider::class, $fake);
        $this->actingAsStaff($this->preparer, $this->council, $this->preparer->memberships()->first());
        $assistant = app(LicenceAiAssistantService::class);
        $extraction = $assistant->extract($this->preparer, $application, $link);
        $this->assertSame($link->version, $extraction->document_version);
        $this->assertSame($link->document_id, $extraction->field_provenance['trading_name']['document_id']);
        $assistant->reviewExtraction($this->preparer, $extraction, 'accepted', [], 'Compared to evidence and accepted as a staff draft.');
        $this->assertSame('Submitted Value', $application->fresh()->form_data['trading_name']);
        $this->assertSame('Extracted Candidate', $application->fresh()->staff_draft_fields['trading_name']);
        $correspondence = $assistant->draft($this->preparer, $application->fresh(), 'information_request');
        $this->assertSame('draft', $correspondence->status);
        $this->assertSame($application->licence_type_version_id, $correspondence->source_refs['licence_type_version_id']);
        $assistant->reviewDraft($this->preparer, $correspondence, 'accepted', 'Reviewed by an officer; send separately if authorised.');
        $this->assertSame('accepted', $correspondence->fresh()->status);
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_in_app_notifications_are_deduplicated_and_user_scoped(): void
    {
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $service = app(LicensingService::class);
        $type = $service->seedDemonstrationType($this->preparer);
        $form = ['trading_name' => 'Inbox Demo', 'activity' => 'Retail'];
        $draft = $service->saveDraft($this->citizen, $this->account->id, $type->code, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');
        $application = $service->submit($this->citizen, $this->account->id, $type->code, $form, 'licence-notification-inbox', $draft->id);
        $inbox = app(LicenceNotificationService::class)->inbox($this->citizen);
        $this->assertNotEmpty($inbox);
        $this->assertSame('in_app', $inbox[0]['channel']);
        $this->assertSame('delivered', $inbox[0]['status']);
        $this->assertStringNotContainsString('Inbox Demo', $inbox[0]['preview']);
        $this->actingAsCitizen($this->citizen, $this->council, $this->citizenMembership);
        $this->get('/portal/licensing/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Licensing/Notifications')->where('portal', true)->has('notifications', 1));
        $other = $this->makeUser('lic-notification-intruder@test.local');
        $role = $this->makeRole($this->council, 'lic_notification_intruder', ['licensing.notifications.view']);
        $membership = $this->makeMembership($other, $this->council, $role, 'citizen');
        $this->actingAsCitizen($other, $this->council, $membership);
        $notificationId = LicenceNotification::where('recipient_user_id', $this->citizen->id)->firstOrFail()->id;
        $token = $other->createToken('notification-test', ['council:'.$this->council->code, 'portal'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/licensing/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/licensing/notifications/'.$notificationId.'/read')->assertNotFound();
        $this->assertSame($application->id, LicenceNotification::where('recipient_user_id', $this->citizen->id)->firstOrFail()->licence_application_id);
        $external = LicenceNotification::where('recipient_user_id', $this->citizen->id)->where('channel', 'email')->firstOrFail();
        $external->forceFill(['status' => 'queued', 'last_error' => null])->save();
        $adapter = new class implements LicenceNotificationAdapter
        {
            public int $attempts = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function unavailableReason(): string
            {
                return 'fixture';
            }

            public function deliver(LicenceNotification $notification): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new \RuntimeException('temporary provider outage');
                }
            }
        };
        $this->app->instance(EmailLicenceNotificationAdapter::class, $adapter);
        try {
            SendLicenceNotification::dispatchSync($this->council->id, $external->id);
            $this->fail('A temporary adapter failure should remain retryable.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('temporary provider outage', $exception->getMessage());
        }
        $this->useCouncil($this->council);
        $this->assertSame('failed', $external->fresh()->status);
        $this->assertSame(1, $external->fresh()->attempts);
        SendLicenceNotification::dispatchSync($this->council->id, $external->id);
        $this->useCouncil($this->council);
        $this->assertSame('delivered', $external->fresh()->status);
        $this->assertSame(2, $external->fresh()->attempts);
    }

    private function submitApplication(LicensingService $service, string $typeCode, array $form, string $key): LicenceApplication
    {
        $draft = $service->saveDraft($this->citizen, $this->account->id, $typeCode, $form);
        $this->uploadEvidence($draft, 'identity');
        $this->uploadEvidence($draft, 'premises');

        return $service->submit($this->citizen, $this->account->id, $typeCode, $form, $key, $draft->id);
    }

    private function uploadEvidence(LicenceApplication $application, string $type): void
    {
        $user = auth()->user();
        $document = app(DocumentService::class)->store(UploadedFile::fake()->create($type.'.txt', 1, 'text/plain'), 'confidential', $application, $user);
        app(LicensingService::class)->attachDocument($user, $this->account->id, $application, $document, $type);
    }
}
