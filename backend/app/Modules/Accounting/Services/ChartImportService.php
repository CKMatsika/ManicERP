<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\ChartImportBatch;
use App\Modules\Accounting\Models\ChartImportRow;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Platform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Staged chart-of-accounts import: parse -> validate -> preview -> approve.
 * Codes are kept as strings (leading zeros preserved) and nothing is written
 * to gl_accounts until a batch is approved.
 */
class ChartImportService
{
    private const HEADER_ALIASES = [
        'code' => 'code',
        'account_code' => 'code',
        'head_code' => 'code',
        'name' => 'name',
        'account_name' => 'name',
        'class' => 'account_class',
        'account_class' => 'account_class',
        'type' => 'account_type',
        'account_type' => 'account_type',
        'parent' => 'parent_code',
        'parent_code' => 'parent_code',
        'normal_balance' => 'normal_balance',
        'balance' => 'normal_balance',
        'is_control' => 'is_control',
        'control' => 'is_control',
        'active_from' => 'active_from',
        'effective_from' => 'active_from',
        'active_to' => 'active_to',
        'effective_to' => 'active_to',
        'description' => 'description',
        'gfsm_code' => 'gfsm_code',
        'source_code' => 'source_code',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function importFile(UploadedFile $file, array $options = [], ?User $user = null): ChartImportBatch
    {
        $format = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $contents = file_get_contents($file->getRealPath());

        return $this->importContents($file->getClientOriginalName(), $contents, $format, $options, $user);
    }

    public function importContents(string $fileName, string $contents, string $format, array $options = [], ?User $user = null): ChartImportBatch
    {
        $hash = hash('sha256', $contents);
        $rows = $this->parse($contents, $format);

        if ($rows === []) {
            throw new \DomainException('The import file contains no data rows.');
        }

        $name = $options['name'] ?? pathinfo($fileName, PATHINFO_FILENAME);
        $sourceType = $options['source_type'] ?? 'demo';

        if (! in_array($sourceType, ['demo', 'official'], true)) {
            throw new \DomainException('Chart source type must be demo or official.');
        }

        $versionNo = (int) ChartVersion::where('name', $name)->max('version_no') + 1;

        $chartVersion = ChartVersion::create([
            'name' => $name,
            'version_no' => $versionNo,
            'status' => 'draft',
            'source_type' => $sourceType,
            'source_authority' => $options['source_authority'] ?? null,
            'source_document' => $options['source_document'] ?? $fileName,
            'source_hash' => $hash,
            'effective_from' => $options['effective_from'] ?? null,
            'effective_to' => $options['effective_to'] ?? null,
            'production_blocker' => $sourceType !== 'official',
            'notes' => $options['notes'] ?? null,
            'created_by' => $user?->getKey() ?? auth()->id(),
        ]);

        Storage::disk('local')->put("imports/charts/{$hash}.{$format}", $contents);

        $batch = ChartImportBatch::create([
            'chart_version_id' => $chartVersion->id,
            'file_name' => $fileName,
            'file_sha256' => $hash,
            'format' => $format,
            'status' => 'validated',
            'uploaded_by' => $user?->getKey() ?? auth()->id(),
        ]);

        $validated = $this->validateRows($rows);

        DB::connection('tenant')->transaction(function () use ($batch, $validated) {
            foreach ($validated as $row) {
                ChartImportRow::create([...$row, 'batch_id' => $batch->id]);
            }

            $errors = count(array_filter($validated, fn ($row) => $row['status'] === 'invalid'));

            $batch->forceFill([
                'row_count' => count($validated),
                'valid_count' => count($validated) - $errors,
                'error_count' => $errors,
                'status' => $errors === 0 ? 'validated' : 'validated_with_errors',
                'preview' => array_slice($validated, 0, 25),
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ])->save();
        });

        $this->audit->record(
            action: 'accounting.chart.import_validated',
            subject: $batch,
            after: ['file' => $fileName, 'rows' => count($validated), 'errors' => $batch->error_count],
            module: 'accounting',
        );

        return $batch->refresh();
    }

    public function approve(ChartImportBatch $batch, ?User $user = null): ChartVersion
    {
        if ($batch->status === 'approved') {
            throw new \DomainException('This import batch has already been approved.');
        }

        if ($batch->error_count > 0 || $batch->status === 'validated_with_errors') {
            throw new \DomainException('Fix the validation errors before approving this chart import.');
        }

        $chartVersion = ChartVersion::findOrFail($batch->chart_version_id);

        return DB::connection('tenant')->transaction(function () use ($batch, $chartVersion, $user) {
            ChartVersion::where('name', $chartVersion->name)
                ->where('status', 'active')
                ->where('id', '!=', $chartVersion->id)
                ->update(['status' => 'superseded']);

            $rows = $batch->rows()->orderBy('row_number')->get();

            foreach ($rows as $row) {
                GlAccount::updateOrCreate(
                    ['chart_version_id' => $chartVersion->id, 'code' => $row->code],
                    [
                        'name' => $row->name,
                        'account_class' => $row->account_class,
                        'account_type' => $row->account_type,
                        'normal_balance' => $row->normal_balance ?? 'debit',
                        'is_control' => (bool) $row->is_control,
                        'active_from' => $row->active_from,
                        'active_to' => $row->active_to,
                        'description' => $row->description,
                        'gfsm_code' => $row->gfsm_code,
                        'source_code' => $row->code,
                    ],
                );
            }

            $byCode = GlAccount::where('chart_version_id', $chartVersion->id)->get()->keyBy('code');

            foreach ($rows as $row) {
                if (blank($row->parent_code)) {
                    continue;
                }

                $parent = $byCode[$row->parent_code] ?? null;
                $account = $byCode[$row->code] ?? null;

                if ($parent && $account) {
                    $account->parent_id = $parent->id;
                    $account->save();
                }
            }

            $chartVersion->status = 'active';
            $chartVersion->approved_by = $user?->getKey() ?? auth()->id();
            $chartVersion->approved_at = now();
            $chartVersion->save();

            $batch->status = 'approved';
            $batch->approved_by = $user?->getKey() ?? auth()->id();
            $batch->approved_at = now();
            $batch->save();

            $this->audit->record(
                action: 'accounting.chart.approved',
                subject: $chartVersion,
                before: ['status' => 'draft'],
                after: ['status' => 'active', 'accounts' => count($rows), 'source_type' => $chartVersion->source_type],
                module: 'accounting',
            );

            return $chartVersion;
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function parse(string $contents, string $format): array
    {
        if ($format === 'csv') {
            return $this->parseCsv($contents);
        }

        return $this->parseXlsx($contents);
    }

    /** @return array<int, array<string, string>> */
    private function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $records = array_map(fn (string $line) => str_getcsv($line, ',', '"', '\\'), $lines);

        return $this->mapRecords($records);
    }

    /** @return array<int, array<string, string>> */
    private function parseXlsx(string $contents): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'chart').'.xlsx';
        file_put_contents($temp, $contents);

        try {
            $sheet = IOFactory::load($temp)->getActiveSheet();
        } finally {
            @unlink($temp);
        }

        $records = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = trim((string) $cell->getFormattedValue());
            }
            $records[] = $cells;
        }

        return $this->mapRecords($records);
    }

    /**
     * @param  array<int, array<int, string>>  $records
     * @return array<int, array<string, string>>
     */
    private function mapRecords(array $records): array
    {
        $records = array_values(array_filter($records, fn ($row) => array_filter((array) $row, fn ($cell) => trim((string) $cell) !== '') !== []));

        if ($records === []) {
            return [];
        }

        $header = array_map(fn ($cell) => self::HEADER_ALIASES[strtolower(trim((string) $cell))] ?? null, $records[0]);
        $rows = [];

        foreach (array_slice($records, 1) as $index => $record) {
            $mapped = [];
            foreach ($header as $column => $field) {
                if ($field) {
                    $mapped[$field] = trim((string) ($record[$column] ?? ''));
                }
            }
            $mapped['row_number'] = $index + 2;
            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function validateRows(array $rows): array
    {
        $codes = [];
        $validated = [];

        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $codes[$code] = ($codes[$code] ?? 0) + 1;
            }
        }

        $parentMap = [];
        foreach ($rows as $row) {
            if (! empty($row['code'])) {
                $parentMap[$row['code']] = $row['parent_code'] ?? '';
            }
        }

        foreach ($rows as $row) {
            $errors = [];
            $code = (string) ($row['code'] ?? '');
            $class = strtolower((string) ($row['account_class'] ?? ''));

            if ($code === '') {
                $errors[] = 'Account code is required.';
            } elseif (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,31}$/', $code)) {
                $errors[] = 'Account code contains invalid characters or is too long (max 32).';
            } elseif (($codes[$code] ?? 0) > 1) {
                $errors[] = "Duplicate account code [{$code}] in this file.";
            }

            if (trim((string) ($row['name'] ?? '')) === '') {
                $errors[] = 'Account name is required.';
            }

            if (! in_array($class, GlAccount::CLASSES, true)) {
                $errors[] = 'Account class must be one of: '.implode(', ', GlAccount::CLASSES).'.';
            }

            $type = strtolower((string) ($row['account_type'] ?? 'posting'));
            if (! in_array($type, GlAccount::TYPES, true)) {
                $errors[] = 'Account type must be one of: '.implode(', ', GlAccount::TYPES).'.';
            }

            $parent = (string) ($row['parent_code'] ?? '');
            if ($parent !== '' && ! isset($codes[$parent])) {
                $errors[] = "Parent code [{$parent}] does not exist in this file.";
            }

            $normal = strtolower((string) ($row['normal_balance'] ?? ''));
            if ($normal !== '' && ! in_array($normal, ['debit', 'credit'], true)) {
                $errors[] = 'Normal balance must be debit or credit.';
            }

            $from = $this->parseDate($row['active_from'] ?? '');
            $to = $this->parseDate($row['active_to'] ?? '');

            if ($from === false || $to === false) {
                $errors[] = 'Active dates must be valid dates (YYYY-MM-DD).';
            } elseif ($from && $to && $from > $to) {
                $errors[] = 'Active-from date cannot be after active-to date.';
            }

            $cycle = $this->detectCycle($code, $parentMap);
            if ($cycle !== null) {
                $errors[] = "Parent relationship forms a cycle involving [{$cycle}].";
            }

            $validated[] = [
                'row_number' => (int) $row['row_number'],
                'code' => $code,
                'name' => $row['name'] ?? null,
                'account_class' => $class,
                'account_type' => $type,
                'parent_code' => $parent ?: null,
                'normal_balance' => $normal ?: $this->defaultNormalBalance($class),
                'is_control' => in_array(strtolower((string) ($row['is_control'] ?? '')), ['1', 'true', 'yes', 'y'], true),
                'active_from' => $from ?: null,
                'active_to' => $to ?: null,
                'description' => $row['description'] ?? null,
                'gfsm_code' => $row['gfsm_code'] ?? null,
                'errors' => $errors,
                'status' => $errors === [] ? 'valid' : 'invalid',
            ];
        }

        return $validated;
    }

    private function detectCycle(string $code, array $parentMap): ?string
    {
        $visited = [];
        $current = $code;

        while ($current !== '' && isset($parentMap[$current])) {
            if (isset($visited[$current])) {
                return $current;
            }
            $visited[$current] = true;
            $current = $parentMap[$current];
        }

        return null;
    }

    private function parseDate(string $value): string|false|null
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return false;
        }
    }

    private function defaultNormalBalance(string $class): string
    {
        return in_array($class, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }
}
