<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\DocumentService;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Procurement\Models\ProcurementQuotation;
use App\Modules\Procurement\Models\ProcurementReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Council-scoped, versioned procurement attachments with permission checks on every read. */
class ProcurementDocumentService
{
    private const TYPES = [
        'supplier_evidence' => ['record' => 'supplier', 'model' => Supplier::class, 'classification' => 'confidential'],
        'quotation' => ['record' => 'quotation', 'model' => ProcurementQuotation::class, 'classification' => 'restricted'],
        'purchase_order' => ['record' => 'purchase_order', 'model' => PurchaseOrder::class, 'classification' => 'confidential'],
        'receiving_evidence' => ['record' => 'receipt', 'model' => ProcurementReceipt::class, 'classification' => 'confidential'],
        'invoice' => ['record' => 'invoice', 'model' => SupplierInvoice::class, 'classification' => 'confidential'],
    ];

    public function __construct(
        private readonly DocumentService $documents,
        private readonly PermissionService $permissions,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(string $type, string $recordId, UploadedFile $file, User $user, ?string $replacementReason = null): Document
    {
        $definition = self::TYPES[$type] ?? throw new \DomainException('Unsupported procurement document type.');
        $this->authorizeType($type, $user, 'manage');

        return DB::connection('tenant')->transaction(function () use ($type, $recordId, $file, $user, $replacementReason, $definition) {
            $target = $definition['model']::query()->findOrFail($recordId);
            $record = DB::connection('tenant')->table('procurement_documents')
                ->where('document_type', $type)->where('record_type', $definition['record'])->where('record_id', $recordId)->lockForUpdate()->first();
            if (! $record) {
                $recordIdInternal = (string) Str::ulid();
                DB::connection('tenant')->table('procurement_documents')->insertOrIgnore([
                    'id' => $recordIdInternal, 'document_type' => $type, 'record_type' => $definition['record'], 'record_id' => $recordId,
                    'classification' => $definition['classification'], 'current_version' => 0, 'status' => 'active', 'created_by' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $record = DB::connection('tenant')->table('procurement_documents')
                    ->where('document_type', $type)->where('record_type', $definition['record'])->where('record_id', $recordId)->lockForUpdate()->firstOrFail();
            }
            if ((int) $record->current_version > 0 && trim((string) $replacementReason) === '') {
                throw new \DomainException('Replacing a document version requires a reason; prior versions are retained.');
            }
            $document = $this->documents->store($file, $definition['classification'], $target, $user);
            $version = (int) $record->current_version + 1;
            DB::connection('tenant')->table('procurement_document_versions')->insert([
                'id' => (string) Str::ulid(), 'document_record_id' => $record->id, 'document_id' => $document->id,
                'version_number' => $version, 'sha256' => $document->sha256, 'scan_status' => $document->scan_status,
                'replacement_reason' => $version > 1 ? trim((string) $replacementReason) : null,
                'uploaded_by' => $user->getKey(), 'uploaded_at' => now(),
            ]);
            DB::connection('tenant')->table('procurement_documents')->where('id', $record->id)->update(['current_version' => $version, 'updated_at' => now()]);
            $this->audit->record(action: 'procurement.document.version_uploaded', subject: $document, after: ['document_type' => $type, 'record_type' => $definition['record'], 'record_id' => $recordId, 'version' => $version, 'sha256' => $document->sha256, 'malware_scan' => 'not_scanned'], module: 'procurement');

            return $document;
        });
    }

    public function setRetention(string $recordId, ?string $retainUntil, string $reason, User $user): object
    {
        $this->permissions->authorize('procurement.documents.retain');
        if (trim($reason) === '') {
            throw new \DomainException('A reason is required when changing document retention.');
        }
        $record = DB::connection('tenant')->table('procurement_documents')->where('id', $recordId)->lockForUpdate()->firstOrFail();
        $date = $retainUntil ? now()->parse($retainUntil)->toDateString() : null;
        if ($date && $date < now()->toDateString()) {
            throw new \DomainException('Retention date cannot be in the past.');
        }
        DB::connection('tenant')->table('procurement_documents')->where('id', $recordId)->update(['retained_until' => $date, 'updated_at' => now()]);
        $this->audit->record(action: 'procurement.document.retention_updated', after: ['record_id' => $recordId, 'retained_until' => $date, 'reason' => trim($reason)], module: 'procurement');

        return DB::connection('tenant')->table('procurement_documents')->where('id', $recordId)->firstOrFail();
    }

    public function canView(Document $document, User $user): bool
    {
        $version = DB::connection('tenant')->table('procurement_document_versions as v')->join('procurement_documents as d', 'd.id', '=', 'v.document_record_id')->where('v.document_id', $document->id)->first(['d.document_type', 'd.classification', 'd.status']);
        if (! $version || $version->status !== 'active') {
            return false;
        }
        $required = $version->classification === 'restricted' ? 'procurement.documents.confidential.view' : 'procurement.documents.view';

        return $this->permissions->allows($user, $required) && $this->permissions->allows($user, 'procurement.'.$this->permissionArea($version->document_type).'.view');
    }

    public function download(Document $document, User $user): StreamedResponse
    {
        abort_unless($this->canView($document, $user), 404);

        return $this->documents->download($document);
    }

    public function preview(Document $document, User $user): StreamedResponse
    {
        abort_unless($this->canView($document, $user), 404);

        return $this->documents->preview($document);
    }

    /** @return array<int, array<string, mixed>> */
    public function visibleVersions(User $user): array
    {
        return DB::connection('tenant')->table('procurement_documents as d')
            ->join('procurement_document_versions as v', 'v.document_record_id', '=', 'd.id')
            ->join('documents as f', 'f.id', '=', 'v.document_id')
            ->where('d.status', 'active')->orderByDesc('v.uploaded_at')
            ->get(['d.id as record_id', 'd.document_type', 'd.record_type', 'd.record_id as target_id', 'd.classification', 'd.current_version', 'd.retained_until', 'v.document_id', 'v.version_number', 'v.sha256', 'v.scan_status', 'v.replacement_reason', 'v.uploaded_at', 'f.original_name'])
            ->filter(function ($row) use ($user) {
                $type = $row->document_type;
                $required = $row->classification === 'restricted' ? 'procurement.documents.confidential.view' : 'procurement.documents.view';

                return $this->permissions->allows($user, $required) && $this->permissions->allows($user, 'procurement.'.$this->permissionArea($type).'.view');
            })->values()->map(fn ($row) => (array) $row)->all();
    }

    private function authorizeType(string $type, User $user, string $action): void
    {
        $permission = match ($action) {
            'manage' => match ($type) {
                'supplier_evidence' => 'procurement.supplier.manage',
                'quotation' => 'procurement.sourcing.manage',
                'purchase_order' => 'procurement.purchase_order.amend',
                'receiving_evidence' => 'procurement.receipt.accept',
                'invoice' => 'procurement.invoice.manage',
            },
            default => 'procurement.documents.view',
        };
        if (! $this->permissions->allows($user, $permission)) {
            abort(403, 'You are not authorised to access this procurement document.');
        }
    }

    private function permissionArea(string $type): string
    {
        return match ($type) {
            'supplier_evidence' => 'supplier',
            'quotation' => 'sourcing',
            'purchase_order' => 'purchase_order',
            'receiving_evidence' => 'receipt',
            'invoice' => 'invoice',
        };
    }
}
