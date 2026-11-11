<?php

namespace App\Modules\Licensing\Contracts;

use App\Modules\Platform\Models\Document;

interface LicenceAiProvider
{
    /** @param array<int, array<string, mixed>> $fields @return array{fields: array<string, mixed>, metadata: array<string, mixed>} */
    public function extract(Document $document, array $fields): array;

    /** @param array<string, mixed> $context @return array{content: string, metadata: array<string, mixed>} */
    public function draft(string $draftType, array $context): array;
}
