<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Services\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMunicipalContext;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use BuildsMunicipalContext;

    private Council $council;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->council = $this->makeCouncil();
        $this->admin = $this->makeUser('docs@test.local');
        $role = $this->makeRole($this->council, 'council_admin', ['*']);
        $membership = $this->makeMembership($this->admin, $this->council, $role);
        $this->actingAsStaff($this->admin, $this->council, $membership);
    }

    public function test_a_private_document_can_be_uploaded_and_downloaded_by_an_authorised_user(): void
    {
        $document = app(DocumentService::class)->store(
            UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf'),
            'confidential',
            null,
            $this->admin,
        );

        Storage::disk('documents')->assertExists($document->path);

        $response = $this->get('/documents/'.$document->id);

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_events', ['action' => 'document.downloaded'], 'tenant');
    }

    public function test_executable_uploads_are_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not permitted');

        app(DocumentService::class)->store(
            UploadedFile::fake()->create('payload.php', 5, 'application/x-php'),
            'internal',
            null,
            $this->admin,
        );
    }

    public function test_a_user_without_the_document_permission_is_denied(): void
    {
        $document = app(DocumentService::class)->store(
            UploadedFile::fake()->create('evidence.pdf', 5, 'application/pdf'),
            'internal',
            null,
            $this->admin,
        );

        $clerk = $this->makeUser('docs-clerk@test.local');
        $role = $this->makeRole($this->council, 'revenue_clerk', ['revenue.account.view']);
        $membership = $this->makeMembership($clerk, $this->council, $role);

        $this->actingAsStaff($clerk, $this->council, $membership);

        $this->get('/documents/'.$document->id)->assertStatus(403);
    }

    public function test_a_guest_cannot_download_a_document(): void
    {
        $document = app(DocumentService::class)->store(
            UploadedFile::fake()->create('evidence.pdf', 5, 'application/pdf'),
            'internal',
            null,
            $this->admin,
        );

        auth()->logout();

        $this->get('/documents/'.$document->id)->assertRedirect('/login');
    }
}
