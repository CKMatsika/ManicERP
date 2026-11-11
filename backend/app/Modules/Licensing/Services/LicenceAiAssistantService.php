<?php

namespace App\Modules\Licensing\Services;

use App\Models\User;
use App\Modules\Licensing\Contracts\LicenceAiProvider;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Models\LicenceApplicationDocument;
use App\Modules\Licensing\Models\LicenceAssistantDraft;
use App\Modules\Licensing\Models\LicenceDocumentExtraction;
use App\Modules\Platform\Models\AiUsageEvent;
use App\Modules\Platform\Models\Document;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Support\Facades\DB;

/** Human-reviewed AI drafts for municipal licensing. It never writes authoritative application fields. */
class LicenceAiAssistantService
{
    public function __construct(
        private readonly LicenceAiProvider $provider,
        private readonly PermissionService $permissions,
        private readonly TenantManager $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function extract(User $user, LicenceApplication $application, LicenceApplicationDocument $link): LicenceDocumentExtraction
    {
        $this->permissions->authorize('licensing.assistant.extract');
        $this->assertStaffOnApplication($user, $application);
        if ($link->licence_application_id !== $application->id) {
            abort(404);
        }
        $document = Document::findOrFail($link->document_id);
        if ($document->status !== 'available' || ! in_array($document->mime_type, ['application/pdf', 'text/plain', 'image/png', 'image/jpeg'], true)) {
            throw new \DomainException('Only available PDF, text or image evidence can be submitted for draft extraction.');
        }
        $this->assertAiBudget();
        $fields = $application->configuration_snapshot['application_fields'] ?? [];
        $result = $this->provider->extract($document, $fields);
        $proposals = $this->validateFields($result['fields'], $fields);
        $provenance = [];
        foreach (array_keys($proposals) as $field) {
            $provenance[$field] = ['document_id' => $document->id, 'document_version' => $link->version, 'sha256' => $document->sha256, 'source' => 'AI proposal; human verification required'];
        }
        $metadata = $result['metadata'];
        $extraction = LicenceDocumentExtraction::create([
            'licence_application_id' => $application->id, 'licence_application_document_id' => $link->id,
            'document_id' => $document->id, 'document_version' => $link->version, 'status' => 'proposed',
            'provider' => $metadata['provider'] ?? config('manicerp.ai.provider'), 'model' => $metadata['model'] ?? config('manicerp.ai.model'),
            'proposed_fields' => $proposals, 'field_provenance' => $provenance, 'created_by' => $user->getKey(),
        ]);
        $this->recordUsage($user, 'licence_extraction', $metadata);
        $this->audit->record(action: 'licensing.ai.extraction_proposed', subject: $extraction, after: ['document_id' => $document->id, 'version' => $link->version, 'field_count' => count($proposals)], module: 'licensing');

        return $extraction;
    }

    public function reviewExtraction(User $user, LicenceDocumentExtraction $extraction, string $decision, array $fields, string $note): LicenceDocumentExtraction
    {
        $this->permissions->authorize('licensing.assistant.extract');
        $application = $extraction->application;
        $this->assertStaffOnApplication($user, $application);
        if (! in_array($decision, ['accepted', 'corrected', 'rejected'], true) || trim($note) === '') {
            throw new \DomainException('Choose accept, correct or reject and record a review note.');
        }
        if ($extraction->status !== 'proposed') {
            throw new \DomainException('This extraction has already been reviewed.');
        }
        $reviewed = $decision === 'rejected' ? [] : $this->validateFields($decision === 'accepted' ? $extraction->proposed_fields : $fields, $application->configuration_snapshot['application_fields'] ?? []);

        return DB::connection('tenant')->transaction(function () use ($user, $extraction, $application, $decision, $reviewed, $note) {
            $locked = LicenceDocumentExtraction::query()->lockForUpdate()->findOrFail($extraction->id);
            if ($locked->status !== 'proposed') {
                throw new \DomainException('This extraction has already been reviewed.');
            }
            $locked->forceFill(['status' => $decision, 'reviewed_fields' => $reviewed, 'reviewed_by' => $user->getKey(), 'reviewed_at' => now(), 'review_note' => $note])->save();
            if (in_array($decision, ['accepted', 'corrected'], true)) {
                $application->forceFill(['staff_draft_fields' => array_merge($application->staff_draft_fields ?? [], $reviewed)])->save();
            }
            $this->audit->record(action: 'licensing.ai.extraction_'.$decision, subject: $locked, after: ['fields' => array_keys($reviewed), 'note' => $note], module: 'licensing');

            return $locked;
        });
    }

    public function draft(User $user, LicenceApplication $application, string $type): LicenceAssistantDraft
    {
        $this->permissions->authorize('licensing.assistant.draft');
        $this->assertStaffOnApplication($user, $application);
        if (! in_array($type, ['information_request', 'staff_correspondence'], true)) {
            throw new \DomainException('Choose an information request or staff correspondence draft.');
        }
        $this->assertAiBudget();
        $inspections = $application->inspections()->where('status', 'completed')->get(['id', 'result', 'findings'])->map(fn ($item) => ['id' => $item->id, 'result' => $item->result, 'recorded_findings' => $item->findings])->all();
        $context = [
            'application_reference' => $application->reference, 'current_status' => $application->status,
            'recorded_council_reason' => $application->decision_reason, 'recorded_inspection_findings' => $inspections,
            'applicant_form_fields' => $application->form_data, 'human_reviewed_draft_fields' => $application->staff_draft_fields,
            'approved_configuration_snapshot' => $application->configuration_snapshot,
            'source_version_id' => $application->licence_type_version_id,
        ];
        $result = $this->provider->draft($type, $context);
        if (trim($result['content']) === '') {
            throw new \DomainException('The AI provider returned an empty draft.');
        }
        $draft = LicenceAssistantDraft::create([
            'licence_application_id' => $application->id, 'draft_type' => $type, 'content' => mb_substr($result['content'], 0, 10000),
            'source_refs' => ['licence_type_version_id' => $application->licence_type_version_id, 'inspection_ids' => array_column($inspections, 'id'), 'basis' => 'Recorded findings and submitted version snapshot'],
            'status' => 'draft', 'created_by' => $user->getKey(),
        ]);
        $this->recordUsage($user, 'licence_correspondence_draft', $result['metadata']);
        $this->audit->record(action: 'licensing.ai.correspondence_drafted', subject: $draft, after: ['type' => $type, 'source_refs' => $draft->source_refs], module: 'licensing');

        return $draft;
    }

    public function reviewDraft(User $user, LicenceAssistantDraft $draft, string $decision, string $note): LicenceAssistantDraft
    {
        $this->permissions->authorize('licensing.assistant.draft');
        $this->assertStaffOnApplication($user, $draft->application);
        if (! in_array($decision, ['accepted', 'rejected'], true) || trim($note) === '') {
            throw new \DomainException('Choose accept or reject and record a review note.');
        }
        if ($draft->status !== 'draft') {
            throw new \DomainException('This correspondence draft has already been reviewed.');
        }
        $draft->forceFill(['status' => $decision, 'reviewed_by' => $user->getKey(), 'reviewed_at' => now(), 'review_note' => $note])->save();
        $this->audit->record(action: 'licensing.ai.correspondence_'.$decision, subject: $draft, after: ['note' => $note], module: 'licensing');

        return $draft;
    }

    private function assertStaffOnApplication(User $user, LicenceApplication $application): void
    {
        if ($application->submitted_by === $user->getKey() || ! $user->activeMemberships()->where('council_id', $this->tenants->requireCouncil()->id)->where('member_type', 'staff')->exists()) {
            abort(404);
        }
    }

    private function assertAiBudget(): void
    {
        if (! config('manicerp.ai.enabled') || ! filled(config('manicerp.ai.provider')) || ! filled(config('manicerp.ai.model'))) {
            throw new \DomainException('Licensing AI generation is disabled until a council provider and model are configured.');
        }
        $budget = (int) config('manicerp.ai.monthly_budget_minor');
        $spent = (int) AiUsageEvent::where('council_id', $this->tenants->requireCouncil()->id)->where('occurred_at', '>=', now()->startOfMonth())->sum('cost_minor');
        if ($budget > 0 && $spent >= $budget) {
            throw new \DomainException('The council AI budget for this month has been exhausted.');
        }
    }

    private function validateFields(array $input, array $definitions): array
    {
        $allowed = collect($definitions)->keyBy('name');
        $result = [];
        foreach ($input as $name => $value) {
            if (! is_string($name) || ! $allowed->has($name)) {
                throw new \DomainException('AI draft fields must match the submitted licence configuration.');
            }
            if ($value === null) {
                continue;
            }
            $field = $allowed->get($name);
            $type = $field['type'] ?? 'string';
            $valid = match ($type) {
                'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                'number' => is_numeric($value),
                'boolean' => is_bool($value),
                'date' => is_string($value) && strtotime($value) !== false,
                'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                default => is_string($value),
            };
            if (! $valid || (is_string($value) && isset($field['max_length']) && mb_strlen($value) > (int) $field['max_length'])) {
                throw new \DomainException('A proposed field does not match the configured field type or length: '.$name.'.');
            }
            $result[$name] = $value;
        }

        return $result;
    }

    private function recordUsage(User $user, string $feature, array $metadata): void
    {
        $inputTokens = (int) ($metadata['input_tokens'] ?? 0);
        $outputTokens = (int) ($metadata['output_tokens'] ?? 0);
        $costMinor = (int) ceil(($inputTokens + $outputTokens) / 1000 * (int) config('manicerp.ai.cost_per_1k_tokens_minor', 0));
        AiUsageEvent::create([
            'council_id' => $this->tenants->requireCouncil()->id, 'user_id' => $user->getKey(), 'feature' => $feature,
            'provider' => $metadata['provider'] ?? config('manicerp.ai.provider'), 'model' => $metadata['model'] ?? config('manicerp.ai.model'),
            'input_tokens' => $inputTokens, 'output_tokens' => $outputTokens, 'cost_minor' => max(0, $costMinor),
            'status' => 'succeeded', 'meta' => ['review_required' => true], 'occurred_at' => now(),
        ]);
    }
}
