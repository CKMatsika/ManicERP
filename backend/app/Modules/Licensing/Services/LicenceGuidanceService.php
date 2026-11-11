<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Models\LicenceType;
use App\Modules\Platform\Services\TenantManager;

/** Supplies the assistant only currently effective, independently approved type configurations. */
class LicenceGuidanceService
{
    public function __construct(private readonly TenantManager $tenants) {}

    /** @return array<int, array<string, mixed>> */
    public function retrieve(string $question, int $limit = 3): array
    {
        $this->tenants->requireCouncil();
        $terms = array_values(array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower($question)) ?: [], fn (string $term) => mb_strlen($term) > 2));
        if ($terms === []) {
            return [];
        }
        $matches = [];
        $types = LicenceType::query()->where('status', 'published')->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))->with('versions')->get();
        foreach ($types as $type) {
            $version = $type->versions->first();
            if (! $version || $version->approval_status !== 'approved' || ! $version->approved_by || ! $version->approved_at) {
                continue;
            }
            $content = json_encode($version->configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            $haystack = mb_strtolower($type->name.' '.$type->code.' '.$content);
            $score = 0;
            foreach ($terms as $term) {
                $score += substr_count($haystack, $term);
            }
            if ($score === 0) {
                continue;
            }
            $matches[] = [
                'id' => 'licence-type:'.$type->id.':'.$version->id,
                'title' => 'Approved licence requirements: '.$type->name,
                'version' => (string) $version->version,
                'effective_from' => $type->effective_from?->toDateString(),
                'source_url' => null,
                'source_authority' => $type->configuration_authority,
                'section_ref' => $type->code,
                'page_ref' => 'Council configuration version '.$version->version,
                'source_hash' => hash('sha256', $content),
                'document_kind' => 'council_licence_configuration',
                'authority_label' => 'Independently approved council licence configuration',
                'applicability_status' => 'council-approved; verify effective date and current status',
                'excerpt' => mb_substr($content, 0, 1200),
                'score' => $score,
            ];
        }
        usort($matches, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }
}
