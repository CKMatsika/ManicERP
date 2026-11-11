<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingRequirement;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\AccountingStandard;
use App\Modules\Ai\Models\PolicyDocument;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Seeds the source-backed requirements register from the supplied PDFs.
 *
 * It records five separate states and never promotes a requirement to
 * "verified" or a standard to "verified". A hash mismatch is a hard failure:
 * the register must not cite a document whose bytes differ from the examined
 * copy.
 */
class AccountingSourceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{documents: int, requirements: int, standards_linked: int}
     */
    public function seed(?string $fixturePath = null, ?string $sourceRoot = null): array
    {
        $fixture = require $fixturePath ?? database_path('seeders/data/accounting_sources.php');
        $sourceRoot = $sourceRoot ?? dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'sources';

        $documents = [];

        foreach ($fixture['documents'] as $key => $definition) {
            $fullPath = $sourceRoot.DIRECTORY_SEPARATOR.basename($definition['storage_path']);
            $exists = is_file($fullPath);
            $actualHash = $exists ? hash_file('sha256', $fullPath) : null;

            if ($exists && ! hash_equals(strtoupper($definition['file_hash']), strtoupper((string) $actualHash))) {
                throw new \DomainException("Source document [{$definition['file_name']}] hash does not match the examined copy; refusing to seed it.");
            }

            $document = AccountingSourceDocument::firstOrNew(['file_hash' => $definition['file_hash']]);
            $document->fill([
                'internal_title' => $definition['internal_title'],
                'file_name' => $definition['file_name'],
                'storage_path' => $definition['storage_path'],
                'document_type' => $definition['document_type'],
                'issuing_authority' => $definition['issuing_authority'],
                'edition' => $definition['edition'],
                'document_date' => $definition['document_date'],
                'page_count' => $definition['page_count'],
                'received_at' => $document->received_at ?? now(),
                'examination_status' => $exists ? 'examined' : 'missing',
                'currency_status' => $definition['currency_status'],
                'metadata' => $definition['metadata'],
                'notes' => $definition['notes'],
            ]);
            $document->save();

            $documents[$key] = $document;
        }

        $requirements = 0;

        foreach ($fixture['requirements'] as $definition) {
            $source = $documents[$definition['source']] ?? null;

            if (! $source) {
                continue;
            }

            $requirement = AccountingRequirement::firstOrNew(['requirement_code' => $definition['code']]);

            $requirement->fill([
                'source_document_id' => $source->id,
                'title' => $definition['title'],
                'section_ref' => $definition['section_ref'],
                'page_ref' => $definition['page_ref'],
                'requirement_text' => $definition['requirement_text'],
                'category' => $definition['category'],
                'jurisdiction' => 'zimbabwe',
                'applicability_status' => $definition['applicability_status'],
                'applicability_rationale' => $definition['applicability_rationale'],
                'requirement_extracted' => (bool) $definition['requirement_extracted'],
                'implementation_status' => $definition['implementation_status'],
                'affected_workflows' => $definition['affected_workflows'],
                'blocker' => $definition['blocker'],
                'notes' => $definition['notes'],
            ]);

            // Council-specific verification and approval states are preserved
            // on reseed; the fixture only establishes extraction baseline.
            if (! $requirement->exists) {
                $requirement->applicability_verified = (bool) $definition['applicability_verified'];
                $requirement->policy_approved = (bool) $definition['policy_approved'];
                $requirement->implementation_tested = (bool) $definition['implementation_tested'];
            }

            $requirement->save();
            $requirements++;
        }

        $linked = 0;

        foreach ($fixture['standards_links'] as $link) {
            $standard = AccountingStandard::where('code', $link['standard'])->first();

            if (! $standard) {
                continue;
            }

            $standard->forceFill([
                'source_document_id' => $documents['zfrm']->id,
                'source_section' => $link['section'],
                'source_page' => $link['page'],
                'zfrm_note' => $link['note'],
            ])->save();

            $linked++;
        }

        $this->audit->record(
            action: 'accounting.sources.seeded',
            after: [
                'documents' => count($documents),
                'requirements' => $requirements,
                'standards_linked' => $linked,
            ],
            metadata: ['note' => 'Source fields only; no standard or requirement marked verified.'],
            module: 'accounting',
        );

        return [
            'documents' => count($documents),
            'requirements' => $requirements,
            'standards_linked' => $linked,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function summary(): array
    {
        return AccountingRequirement::with('sourceDocument')
            ->orderBy('requirement_code')
            ->get()
            ->map(fn (AccountingRequirement $requirement) => [
                'code' => $requirement->requirement_code,
                'title' => $requirement->title,
                'section' => $requirement->section_ref,
                'page' => $requirement->page_ref,
                'source' => $requirement->sourceDocument?->internal_title,
                'states' => $requirement->states(),
                'applicability_status' => $requirement->applicability_status,
                'implementation_status' => $requirement->implementation_status,
                'blocker' => $requirement->blocker,
            ])
            ->all();
    }

    public function publishGuidance(User $user): int
    {
        if (! app(PermissionService::class)->allows($user, 'accounting.requirements.manage')) {
            throw new AuthorizationException;
        }
        $count = 0;
        foreach (AccountingRequirement::with('sourceDocument')->where('requirement_extracted', true)->get() as $requirement) {
            if ($requirement->sourceDocument->examination_status !== 'examined') {
                continue;
            }
            PolicyDocument::updateOrCreate([
                'source_document_id' => $requirement->source_document_id, 'section_ref' => $requirement->section_ref,
            ], [
                'title' => $requirement->sourceDocument->internal_title.' — '.$requirement->title,
                'version' => $requirement->sourceDocument->edition, 'status' => 'approved',
                'source_authority' => $requirement->sourceDocument->issuing_authority,
                'content' => $requirement->requirement_text."\n\nCurrent applicability is unverified. ".$requirement->blocker,
                'checksum' => hash('sha256', $requirement->requirement_text), 'page_ref' => $requirement->page_ref,
                'document_kind' => 'source_guidance', 'approved_policy' => false, 'approved_by' => $user->id, 'approved_at' => now(),
            ]);
            $count++;
        }
        $this->audit->record(action: 'accounting.sources.retrieval_approved', after: ['count' => $count, 'policy_approval' => false], module: 'accounting');

        return $count;
    }
}
