<?php

namespace Tests\Feature;

use App\Modules\Platform\Models\Document;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\SourcingService;
use App\Modules\Procurement\Services\SupplierBudgetService;
use App\Modules\Revenue\Models\Party;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class ProcurementDocumentTest extends TestCase
{
    use BuildsMunicipalContext;

    protected bool $useTenantTransaction = false;

    public function test_private_supplier_documents_are_versioned_with_safe_keys_and_reasoned_replacement(): void
    {
        Storage::fake('documents');
        $council = $this->makeCouncil(['code' => 'DOCS']);
        $preparer = $this->makeUser('proc-doc-preparer@test.local');
        $approver = $this->makeUser('proc-doc-approver@test.local');
        $manager = $this->makeUser('proc-doc-manager@test.local');
        $role = $this->makeRole($council, 'procurement_doc_manager', ['*']);
        $membership = $this->makeMembership($manager, $council, $role);
        $this->actingAsStaff($manager, $council, $membership);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Private Evidence Supplier', 'status' => 'active', 'code' => 'DOC-SUP']);
        $supplier = app(SupplierBudgetService::class)->approveSupplier(app(SupplierBudgetService::class)->onboard($party, 'DOC-SUP-1', $preparer), $approver);

        $first = $this->from('/procurement')->post('/procurement/documents/supplier_evidence/'.$supplier->id, ['file' => UploadedFile::fake()->create('..\\secret\\supplier.pdf', 15, 'application/pdf'), 'path' => 'another-council/escape.pdf'])->assertRedirect('/procurement');
        $first->assertSessionHas('success');
        $record = DB::connection('tenant')->table('procurement_documents')->where('record_id', $supplier->id)->firstOrFail();
        $versions = DB::connection('tenant')->table('procurement_document_versions')->where('document_record_id', $record->id)->get();
        $this->assertCount(1, $versions);
        $document = Document::findOrFail($versions->first()->document_id);
        $this->assertSame('supplier.pdf', $document->original_name);
        $this->assertStringStartsWith($council->code.'/', $document->path);
        $this->assertStringNotContainsString('escape', $document->path);
        $this->assertSame('not_scanned', $document->scan_status);
        Storage::disk('documents')->assertExists($document->path);

        $this->post('/procurement/documents/supplier_evidence/'.$supplier->id, ['file' => UploadedFile::fake()->create('supplier-v2.pdf', 18, 'application/pdf')])->assertSessionHas('error');
        $this->post('/procurement/documents/supplier_evidence/'.$supplier->id, ['file' => UploadedFile::fake()->create('supplier-v2.pdf', 18, 'application/pdf'), 'replacement_reason' => 'Current insurance certificate replaces expired version.'])->assertSessionHas('success');
        $versions = DB::connection('tenant')->table('procurement_document_versions')->where('document_record_id', $record->id)->orderBy('version_number')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('Current insurance certificate replaces expired version.', $versions[1]->replacement_reason);
        $this->assertSame(2, (int) DB::connection('tenant')->table('procurement_documents')->where('id', $record->id)->value('current_version'));
        $this->assertNotSame($versions[0]->document_id, $versions[1]->document_id);
        $this->assertTrue(Document::whereKey($versions[0]->document_id)->exists());
        Storage::disk('documents')->assertExists(Document::findOrFail($versions[0]->document_id)->path);

        $this->get('/procurement/documents/'.$document->id.'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $preview = $this->get('/procurement/documents/'.$document->id.'/preview')->assertOk();
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));
        $this->post('/procurement/documents/'.$record->id.'/retention', ['retained_until' => '2030-01-01', 'reason' => 'Synthetic contract evidence retention date.'])->assertSessionHas('success');
        $this->assertSame('2030-01-01', substr((string) DB::connection('tenant')->table('procurement_documents')->where('id', $record->id)->value('retained_until'), 0, 10));

        $otherCouncil = $this->makeCouncil(['code' => 'DOCS2', 'database_name' => $this->secondaryTenantDatabase()]);
        $otherUser = $this->makeUser('proc-doc-other@test.local');
        $otherRole = $this->makeRole($otherCouncil, 'procurement_doc_other', ['*']);
        config(['database.connections.tenant.database' => $otherCouncil->database_name]);
        DB::purge('tenant');
        $this->actingAsStaff($otherUser, $otherCouncil, $this->makeMembership($otherUser, $otherCouncil, $otherRole));
        $this->get('/procurement/documents/'.$document->id.'/download')->assertNotFound();
    }

    public function test_confidential_quotation_documents_recheck_permissions_after_role_changes(): void
    {
        Storage::fake('documents');
        $council = $this->makeCouncil(['code' => 'QDOC']);
        $preparer = $this->makeUser('quote-doc-preparer@test.local');
        $approver = $this->makeUser('quote-doc-approver@test.local');
        $manager = $this->makeUser('quote-doc-manager@test.local');
        $managerRole = $this->makeRole($council, 'quote_doc_manager', ['*']);
        $managerMembership = $this->makeMembership($manager, $council, $managerRole);
        $this->actingAsStaff($manager, $council, $managerMembership);
        $controls = app(SupplierBudgetService::class);
        $sourcing = app(SourcingService::class);
        $budget = $controls->createBudget('QDOC-BUDGET', 'Quotation document test', '2026-01-01', '2026-12-31', [['account_code' => '5020', 'amount_minor' => 50000]], $preparer);
        $controls->approveBudget($budget, $approver);
        $requisition = $sourcing->prepareRequisition($budget->lines->first(), 'Confidential quotation test', 'Exercise document access', 20000, $preparer);
        $requisition = $sourcing->approveRequisition($sourcing->submitRequisition($requisition->id, $preparer)->id, $approver);
        $party = Party::create(['party_type' => 'organisation', 'display_name' => 'Quotation Supplier', 'status' => 'active', 'code' => 'QDOC-SUP']);
        $supplier = $controls->approveSupplier($controls->onboard($party, 'QDOC-SUP-1', $preparer), $approver);
        $quotation = $sourcing->submitQuotation($requisition->id, $supplier, 'QDOC-REF-1', 18000, 'Restricted evaluation record.', $preparer);
        $document = app(ProcurementDocumentService::class)->upload('quotation', $quotation->id, UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf'), $manager);

        $viewer = $this->makeUser('quote-doc-viewer@test.local');
        $viewerRole = $this->makeRole($council, 'quote_doc_viewer', ['procurement.documents.view', 'procurement.sourcing.view']);
        $viewerMembership = $this->makeMembership($viewer, $council, $viewerRole);
        $this->actingAsStaff($viewer, $council, $viewerMembership);
        $this->get('/procurement/documents/'.$document->id.'/preview')->assertNotFound();

        $viewerRole->forceFill(['permissions' => ['procurement.documents.view', 'procurement.documents.confidential.view', 'procurement.sourcing.view']])->save();
        $viewerMembership = $viewerMembership->fresh();
        $this->actingAsStaff($viewer, $council, $viewerMembership);
        $this->get('/procurement/documents/'.$document->id.'/preview')->assertOk();

        $viewerRole->forceFill(['permissions' => ['procurement.documents.view', 'procurement.sourcing.view']])->save();
        $this->actingAsStaff($viewer, $council, $viewerMembership->fresh());
        $this->get('/procurement/documents/'.$document->id.'/download')->assertNotFound();

        $this->actingAsStaff($manager, $council, $managerMembership);
        $this->post('/procurement/documents/quotation/'.Str::ulid(), ['file' => UploadedFile::fake()->create('guess.pdf', 1, 'application/pdf')])->assertNotFound();
    }
}
