<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private document storage. Files live on a non-public disk and are only ever
 * handed out through an authorised controller action.
 */
class DocumentService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function store(
        UploadedFile $file,
        string $classification = 'internal',
        ?Model $documentable = null,
        ?User $uploader = null,
    ): Document {
        $council = $this->tenants->requireCouncil();

        $maxKb = (int) config('manicerp.documents.max_kilobytes');
        $allowedMimes = config('manicerp.documents.allowed_mimes');
        $allowedExtensions = config('manicerp.documents.allowed_extensions');
        $classifications = ['public', 'internal', 'confidential', 'restricted'];

        if (! $file->isValid()) {
            throw new \DomainException('The upload failed or was interrupted.');
        }

        if ($file->getSize() > $maxKb * 1024) {
            throw new \DomainException("Files may not exceed {$maxKb} KB.");
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw new \DomainException("File type [.{$extension}] is not permitted.");
        }

        if (! in_array($mime, $allowedMimes, true)) {
            throw new \DomainException("Detected content type [{$mime}] is not permitted.");
        }

        if (! in_array($classification, $classifications, true)) {
            throw new \DomainException('Unknown document classification.');
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $id = (string) Str::ulid();
        $path = sprintf('%s/%s/%s.%s', $council->code, now()->format('Y/m'), $id, $extension);

        Storage::disk(config('manicerp.documents.disk'))->putFileAs(dirname($path), $file, basename($path));

        $document = Document::create([
            'id' => $id,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'path' => $path,
            'disk' => config('manicerp.documents.disk'),
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
            'classification' => $classification,
            'scan_status' => 'not_scanned',
            'status' => 'available',
            'documentable_type' => $documentable ? $documentable::class : null,
            'documentable_id' => $documentable?->getKey(),
            'uploaded_by' => $uploader?->getKey(),
            'metadata' => ['original_extension' => $extension],
        ]);

        $this->audit->record(
            action: 'document.uploaded',
            subject: $document,
            after: ['original_name' => $document->original_name, 'sha256' => $sha256],
            metadata: ['classification' => $classification],
            module: 'platform.core',
        );

        return $document;
    }

    public function download(Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->path)) {
            abort(404, 'The stored document could not be found.');
        }

        $this->audit->record(
            action: 'document.downloaded',
            subject: $document,
            module: 'platform.core',
        );

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);

        $this->audit->record(
            action: 'document.deleted',
            subject: $document,
            before: ['original_name' => $document->original_name],
            module: 'platform.core',
        );

        $document->delete();
    }
}
