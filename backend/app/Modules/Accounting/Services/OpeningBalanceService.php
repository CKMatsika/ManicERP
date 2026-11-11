<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSetting;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\MigrationBatch;
use App\Modules\Accounting\Models\MigrationRow;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Revenue\Models\AccountCredit;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Staged opening balances and migration cutover.
 *
 * This is migration cutover only. It never modifies the council's IPSAS
 * adoption dates; those are set explicitly and separately.
 */
class OpeningBalanceService
{
    public const TYPE_GL = 'opening_gl';

    public const TYPE_CUSTOMER = 'opening_customer';

    public function __construct(
        private readonly PostingService $posting,
        private readonly PeriodService $periods,
        private readonly NumberSequenceService $sequences,
        private readonly TenantManager $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function template(string $type): string
    {
        return match ($type) {
            self::TYPE_GL => "account_code,debit,credit,description,dimensions\n"
                ."1110,1000.00,0.00,Opening cash,\n"
                ."3010,0.00,1000.00,Opening accumulated surplus,\n",
            self::TYPE_CUSTOMER => "account_number,type,reference,amount,due_date,description,service_type\n"
                ."ACC-0000001,invoice,OB-INV-0001,1700.00,2026-01-31,Opening rates invoice,rates\n"
                ."ACC-0000001,unapplied,OB-UNP-0001,50.00,,Opening unapplied credit,\n",
            default => throw new \DomainException('Unknown opening balance type.'),
        };
    }

    public function import(
        string $type,
        string $contents,
        string $fileName,
        string $cutoverDate,
        string $currency,
        ?User $user = null,
    ): MigrationBatch {
        if (! in_array($type, [self::TYPE_GL, self::TYPE_CUSTOMER], true)) {
            throw new \DomainException('Unknown opening balance type.');
        }

        $cutover = CarbonImmutable::parse($cutoverDate)->toDateString();
        if ($cutover !== $cutoverDate) {
            throw new \DomainException('Cutover must be a valid YYYY-MM-DD date.');
        }
        $currency = strtoupper($currency);
        $this->periods->forPosting($cutover);

        $council = $this->tenants->requireCouncil();

        if (strtoupper($currency) !== $council->currency_code) {
            throw new \DomainException("Opening balances must use the council currency [{$council->currency_code}].");
        }

        $hash = hash('sha256', $contents);
        $existing = MigrationBatch::where('type', $type)->where('file_hash', $hash)->first();

        if ($existing) {
            if ($existing->cutover_date->toDateString() !== $cutover || $existing->currency_code !== strtoupper($currency)) {
                throw new \DomainException('This file was already staged with a different cutover date or currency.');
            }

            return $existing->load('rows');
        }

        $rows = $this->parse($contents);

        if ($rows === []) {
            throw new \DomainException('The import file contains no data rows.');
        }

        $validated = $type === self::TYPE_GL
            ? $this->validateGlRows($rows, $cutover)
            : $this->validateCustomerRows($rows, $cutover, $currency);

        $errors = count(array_filter($validated, fn (array $row) => $row['status'] === 'invalid'));
        $year = CarbonImmutable::parse($cutover)->format('Y');
        $batchNumber = $this->sequences->next("migration:{$year}", "MIG-{$year}", 5, $year);

        return DB::connection('tenant')->transaction(function () use ($type, $fileName, $hash, $cutover, $currency, $validated, $errors, $user, $batchNumber) {
            $batch = MigrationBatch::create([
                'batch_number' => $batchNumber,
                'type' => $type,
                'status' => $errors === 0 ? 'validated' : 'validated_with_errors',
                'file_name' => $fileName,
                'file_hash' => $hash,
                'cutover_date' => $cutover,
                'currency_code' => $currency,
                'row_count' => count($validated),
                'valid_count' => count($validated) - $errors,
                'error_count' => $errors,
                'totals' => $this->totals($validated),
                'uploaded_by' => $user?->getKey(),
                'idempotency_key' => "migration:{$type}:{$hash}",
            ]);

            foreach ($validated as $row) {
                MigrationRow::create([...$row, 'batch_id' => $batch->id]);
            }

            $this->audit->record(
                action: 'accounting.opening_balance.imported',
                subject: $batch,
                after: ['batch' => $batchNumber, 'type' => $type, 'rows' => count($validated), 'errors' => $errors],
                module: 'accounting',
            );

            return $batch->load('rows');
        });
    }

    public function approve(MigrationBatch $batch, User $approver): MigrationBatch
    {
        if (! app(PermissionService::class)->allows($approver, 'accounting.opening_balance.approve')) {
            throw new AuthorizationException;
        }

        return DB::connection('tenant')->transaction(function () use ($batch, $approver) {
            $batch = MigrationBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status === 'approved' || $batch->status === 'posted') {
                return $batch;
            }

            if ($batch->error_count > 0) {
                throw new \DomainException('Resolve row errors before approving this opening balance batch.');
            }

            if ($batch->uploaded_by === $approver->getKey()) {
                throw new \DomainException('Separation of duties: the opening balance batch must be approved by a different officer.');
            }

            $batch->forceFill([
                'status' => 'approved',
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ])->save();

            $this->audit->record(
                action: 'accounting.opening_balance.approved',
                subject: $batch,
                before: ['status' => 'validated'],
                after: ['status' => 'approved'],
                module: 'accounting',
            );

            return $batch;
        });
    }

    public function post(MigrationBatch $batch, User $user): MigrationBatch
    {
        if (! app(PermissionService::class)->allows($user, 'accounting.opening_balance.approve')) {
            throw new AuthorizationException;
        }
        if ($batch->status === 'posted') {
            return $batch;
        }

        $this->periods->forPosting($batch->cutover_date->toDateString());

        return DB::connection('tenant')->transaction(function () use ($batch, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('opening-balance-cutover'))");
            $locked = MigrationBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($locked->status === 'posted') {
                return $locked;
            }
            if ($locked->status !== 'approved') {
                throw new \DomainException('Only an approved opening balance batch can be posted.');
            }

            $duplicate = MigrationBatch::where('type', $locked->type)
                ->whereDate('cutover_date', $locked->cutover_date)
                ->where('status', 'posted')
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($duplicate) {
                throw new \DomainException('An opening balance batch of this type has already been posted for the cutover date.');
            }

            $this->guardDoubleCounting($locked);

            if ($locked->type === self::TYPE_GL) {
                $this->postGl($locked, $user);
            } else {
                $this->postCustomer($locked, $user);
            }

            $locked->refresh();
            $locked->forceFill([
                'status' => 'posted',
                'posted_at' => now(),
                'reconciliation' => $this->reconcile($locked),
            ])->save();

            $this->audit->record(
                action: 'accounting.opening_balance.posted',
                subject: $locked,
                after: [
                    'batch' => $locked->batch_number,
                    'type' => $locked->type,
                    'journal_entry_id' => $locked->journal_entry_id,
                    'reconciliation' => $locked->reconciliation,
                ],
                module: 'accounting',
            );

            return $locked->load('rows');
        });
    }

    /** @return array<string, mixed> */
    public function reconcile(MigrationBatch $batch): array
    {
        $batch->refresh();

        if ($batch->type === self::TYPE_CUSTOMER) {
            $invoices = (int) $batch->rows()->where('row_type', 'invoice')->sum('amount_minor');
            $credits = (int) $batch->rows()->whereIn('row_type', ['credit', 'deposit', 'unapplied'])->sum('amount_minor');
            $journal = $batch->journal_entry_id
                ? JournalEntry::with('lines')->find($batch->journal_entry_id)
                : null;

            $receivableLines = $journal?->lines->filter(fn ($line) => in_array((string) $line->account?->code, array_values(config('accounting.receivables')), true))->sum('debit_minor') ?? 0;
            $liabilityLines = $journal?->lines->filter(fn ($line) => $line->account?->code === config('accounting.receipts.unapplied'))->sum('credit_minor') ?? 0;

            return [
                'invoice_total_minor' => $invoices,
                'credit_total_minor' => $credits,
                'journal_receivable_minor' => (int) $receivableLines,
                'journal_entry_id' => $batch->journal_entry_id,
                'balanced' => $journal ? $journal->isBalanced() : false,
                'receivable_reconciles' => $journal !== null && (int) $receivableLines === $invoices,
                'credit_reconciles' => $journal !== null && (int) $liabilityLines === $credits,
                'bills_created' => $batch->rows()->where('row_type', 'invoice')->whereNotNull('bill_id')->count(),
                'credits_created' => $batch->rows()->whereNotNull('credit_id')->count(),
                'payments_created' => 0,
            ];
        }

        $debit = (int) $batch->rows()->sum('debit_minor');
        $credit = (int) $batch->rows()->sum('credit_minor');

        return [
            'debit_minor' => $debit,
            'credit_minor' => $credit,
            'balanced' => $debit === $credit && $debit > 0,
            'journal_entry_id' => $batch->journal_entry_id,
            'control_accounts_included' => $batch->rows()->whereIn('account_code', array_values(config('accounting.receivables')))->count(),
        ];
    }

    public function adoption(): ?AccountingSetting
    {
        return AccountingSetting::adoption();
    }

    /**
     * Migration cutover is deliberately separate from IPSAS first-time adoption.
     * Importing or posting opening balances never calls this method.
     */
    public function recordAdoption(string $adoptionStart, string $cutover, User $approver): AccountingSetting
    {
        return AccountingSetting::updateOrCreate(
            ['key' => 'adoption'],
            [
                'value' => [
                    'adoption_start_date' => CarbonImmutable::parse($adoptionStart)->toDateString(),
                    'migration_cutover_date' => CarbonImmutable::parse($cutover)->toDateString(),
                ],
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ],
        );
    }

    private function postGl(MigrationBatch $batch, User $user): void
    {
        $lines = $batch->rows()->where('status', 'valid')->get()->map(fn (MigrationRow $row) => [
            'account_code' => $row->account_code,
            'debit_minor' => $row->debit_minor,
            'credit_minor' => $row->credit_minor,
            'memo' => $row->description ?? 'Opening balance',
            'dimensions' => $row->dimensions ?? [],
        ])->all();

        $journal = $this->posting->post([
            'journal_type' => 'opening_balance',
            'entry_date' => $batch->cutover_date->toDateString(),
            'description' => "Opening GL balances {$batch->batch_number}",
            'currency_code' => $batch->currency_code,
            'lines' => $lines,
            'source_type' => 'opening_gl_batch',
            'source_id' => $batch->id,
            'idempotency_key' => 'opening-gl:'.$batch->id,
            'created_by' => $user->getKey(),
        ]);

        $batch->forceFill([
            'journal_entry_id' => $journal->id,
            'control_totals' => [
                'debit_minor' => $journal->total_debit_minor,
                'credit_minor' => $journal->total_credit_minor,
                'lines' => count($lines),
            ],
        ])->save();

        $batch->rows()->update(['status' => 'posted']);
    }

    private function postCustomer(MigrationBatch $batch, User $user): void
    {
        $receivables = config('accounting.receivables');
        $receivableByService = [
            'rates' => $receivables['rates'],
            'water' => $receivables['water'],
            'refuse' => $receivables['refuse'],
            'rental' => $receivables['rental'],
        ];

        $debits = [];
        $credits = 0;

        foreach ($batch->rows()->where('status', 'valid')->get() as $row) {
            $account = CustomerAccount::where('account_number', $row->account_number)->firstOrFail();

            if ($row->row_type === 'invoice') {
                $serviceType = $row->data['service_type'] ?? $account->account_type;

                if (! isset($receivableByService[$serviceType])) {
                    $serviceType = 'rates';
                }

                $bill = Bill::create([
                    'bill_number' => 'OB-'.$batch->batch_number.'-'.str_pad((string) $row->row_number, 4, '0', STR_PAD_LEFT),
                    'customer_account_id' => $account->id,
                    'billing_run_id' => null,
                    'service_type' => $serviceType,
                    'period_start' => $batch->cutover_date,
                    'period_end' => $batch->cutover_date,
                    'issue_date' => $batch->cutover_date,
                    'due_date' => $row->due_date ?? $batch->cutover_date,
                    'status' => 'issued',
                    'currency_code' => $batch->currency_code,
                    'total_minor' => $row->amount_minor,
                    'paid_minor' => 0,
                    'balance_minor' => $row->amount_minor,
                    'calculation_snapshot' => [
                        'origin' => 'opening_balance',
                        'batch' => $batch->batch_number,
                        'reference' => $row->reference,
                        'migration_note' => 'Imported opening receivable; no historic cash receipt was created.',
                    ],
                    'approved_by' => $user->getKey(),
                    'approved_at' => now(),
                    'origin' => 'opening_balance',
                    'migration_batch_id' => $batch->id,
                ]);

                $debits[$receivableByService[$serviceType]] = ($debits[$receivableByService[$serviceType]] ?? 0) + $row->amount_minor;
                $row->forceFill(['bill_id' => $bill->id, 'status' => 'posted'])->save();

                continue;
            }

            $credit = AccountCredit::create([
                'customer_account_id' => $account->id,
                'type' => $row->row_type,
                'source' => 'opening_balance',
                'migration_batch_id' => $batch->id,
                'amount_minor' => $row->amount_minor,
                'remaining_minor' => $row->amount_minor,
                'currency_code' => $batch->currency_code,
                'reference' => $row->reference,
                'status' => 'open',
                'effective_date' => $batch->cutover_date,
            ]);

            $credits += $row->amount_minor;
            $row->forceFill(['credit_id' => $credit->id, 'status' => 'posted'])->save();
        }

        $lines = [];

        foreach ($debits as $code => $amount) {
            $lines[] = ['account_code' => $code, 'debit_minor' => $amount, 'memo' => "Opening receivables {$batch->batch_number}"];
        }

        if ($credits > 0) {
            $lines[] = ['account_code' => config('accounting.receipts.unapplied'), 'credit_minor' => $credits, 'memo' => 'Opening unapplied funds and deposits'];
        }

        $totalDebit = array_sum($debits);
        $difference = $totalDebit - $credits;

        if ($difference > 0) {
            $lines[] = ['account_code' => '3010', 'credit_minor' => $difference, 'memo' => 'Opening accumulated surplus'];
        } elseif ($difference < 0) {
            $lines[] = ['account_code' => '3010', 'debit_minor' => abs($difference), 'memo' => 'Opening accumulated deficit'];
        }

        if (count($lines) < 2) {
            throw new \DomainException('Customer opening batch produced no balanced journal.');
        }

        $journal = $this->posting->post([
            'journal_type' => 'opening_balance',
            'entry_date' => $batch->cutover_date->toDateString(),
            'description' => "Opening customer balances {$batch->batch_number}",
            'currency_code' => $batch->currency_code,
            'lines' => $lines,
            'source_type' => 'opening_customer_batch',
            'source_id' => $batch->id,
            'idempotency_key' => 'opening-customer:'.$batch->id,
            'created_by' => $user->getKey(),
        ]);

        $batch->forceFill([
            'journal_entry_id' => $journal->id,
            'control_totals' => [
                'invoice_minor' => $totalDebit,
                'credit_minor' => $credits,
                'equity_minor' => $difference,
                'journal_total_minor' => $journal->total_debit_minor,
            ],
        ])->save();
    }

    private function guardDoubleCounting(MigrationBatch $batch): void
    {
        if ($batch->type === self::TYPE_GL) {
            $controlCodes = [...array_values(config('accounting.receivables')), config('accounting.receipts.unapplied')];

            $hasControl = $batch->rows()->whereIn('account_code', $controlCodes)->exists();
            $customerBatch = MigrationBatch::where('type', self::TYPE_CUSTOMER)
                ->whereDate('cutover_date', $batch->cutover_date)
                ->whereIn('status', ['approved', 'posted'])
                ->exists();

            if ($hasControl && $customerBatch) {
                throw new \DomainException('This GL opening batch includes receivable control accounts while a customer opening batch exists for the cutover date. Import customer debt through the customer balance batch instead.');
            }

            return;
        }

        $glWithControl = MigrationBatch::where('type', self::TYPE_GL)
            ->whereDate('cutover_date', $batch->cutover_date)
            ->where('status', 'posted')
            ->whereHas('rows', fn ($query) => $query->whereIn('account_code', [...array_values(config('accounting.receivables')), config('accounting.receipts.unapplied')]))
            ->exists();

        if ($glWithControl) {
            throw new \DomainException('A posted GL opening batch already contains receivable control balances for this cutover. Correct that batch instead of double-counting through customer balances.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $records = [];
        while (($record = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $records[] = $record;
        }
        fclose($stream);
        $records = array_values(array_filter($records, fn ($row) => array_filter((array) $row, fn ($cell) => trim((string) $cell) !== '') !== []));

        if ($records === []) {
            return [];
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($records));
        if (count($header) !== count(array_unique($header))) {
            throw new \DomainException('Duplicate CSV column headings are not allowed.');
        }
        $rows = [];

        foreach ($records as $index => $record) {
            $row = ['row_number' => $index + 2];

            foreach ($header as $column => $field) {
                $row[$field] = trim((string) ($record[$column] ?? ''));
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function validateGlRows(array $rows, string $cutover): array
    {
        $chart = ChartVersion::where('status', 'active')->first();

        if (! $chart) {
            throw new \DomainException('No active chart of accounts.');
        }

        $accounts = GlAccount::where('chart_version_id', $chart->id)->get()->keyBy('code');
        $validated = [];
        $seen = [];

        foreach ($rows as $row) {
            $errors = [];
            $code = $row['account_code'] ?? '';
            $debit = $this->amount($row['debit'] ?? '0', $errors);
            $credit = $this->amount($row['credit'] ?? '0', $errors);

            $account = $accounts[$code] ?? null;

            if (! $account) {
                $errors[] = "Unknown account code [{$code}].";
            } elseif (! $account->isPostable()) {
                $errors[] = "Account [{$code}] is not postable.";
            } elseif (! $account->isActiveOn($cutover)) {
                $errors[] = "Account [{$code}] is not active on the cutover date.";
            }

            if ($debit < 0 || $credit < 0) {
                $errors[] = 'Debit and credit cannot be negative.';
            }

            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                $errors[] = 'Each row must contain exactly one non-zero debit or credit.';
            }

            $dimensions = $this->parseDimensions($row['dimensions'] ?? '', $errors);
            ksort($dimensions);
            $identity = $code.':'.json_encode($dimensions);
            if (isset($seen[$identity])) {
                $errors[] = 'Duplicate GL account and dimension combination.';
            }
            $seen[$identity] = true;
            try {
                $this->posting->validateDimensions($dimensions, $cutover);
            } catch (\DomainException $exception) {
                $errors[] = $exception->getMessage();
            }

            $validated[] = [
                'row_number' => $row['row_number'],
                'row_type' => 'gl',
                'account_code' => $code,
                'description' => $row['description'] ?? null,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'amount_minor' => $debit > 0 ? $debit : $credit,
                'dimensions' => $dimensions,
                'data' => $row,
                'errors' => $errors,
                'status' => $errors === [] ? 'valid' : 'invalid',
            ];
        }

        $totalDebit = array_sum(array_column(array_filter($validated, fn ($row) => $row['status'] === 'valid'), 'debit_minor'));
        $totalCredit = array_sum(array_column(array_filter($validated, fn ($row) => $row['status'] === 'valid'), 'credit_minor'));

        if ($totalDebit !== $totalCredit || $totalDebit === 0) {
            foreach ($validated as &$row) {
                if ($row['status'] === 'valid') {
                    $row['errors'][] = "Opening GL batch is unbalanced: debits {$totalDebit} vs credits {$totalCredit}.";
                    $row['status'] = 'invalid';
                }
            }
        }

        return $validated;
    }

    /** @return array<int, array<string, mixed>> */
    private function validateCustomerRows(array $rows, string $cutover, string $currency): array
    {
        $accounts = CustomerAccount::all()->keyBy('account_number');
        $references = [];
        $validated = [];

        foreach ($rows as $row) {
            $errors = [];
            $accountNumber = $row['account_number'] ?? '';
            $type = strtolower($row['type'] ?? '');
            $reference = $row['reference'] ?? '';
            $amount = $this->amount($row['amount'] ?? '0', $errors);

            $account = $accounts[$accountNumber] ?? null;

            if (! $account) {
                $errors[] = "Unknown customer account [{$accountNumber}].";
            } elseif ($account->status !== 'active') {
                $errors[] = "Customer account [{$accountNumber}] is not active.";
            } elseif (strtoupper($account->currency_code) !== strtoupper($currency)) {
                $errors[] = "Account currency [{$account->currency_code}] differs from the batch currency [{$currency}].";
            }

            if (! in_array($type, ['invoice', 'credit', 'deposit', 'unapplied'], true)) {
                $errors[] = 'Type must be invoice, credit, deposit or unapplied.';
            }

            if ($reference === '') {
                $errors[] = 'Reference is required.';
            } elseif (isset($references[$reference])) {
                $errors[] = "Duplicate reference [{$reference}] in this file.";
            }

            $references[$reference] = true;
            if (MigrationRow::where('account_number', $accountNumber)->where('reference', $reference)->whereHas('batch', fn ($q) => $q->whereIn('status', ['approved', 'posted']))->exists()) {
                $errors[] = 'Reference already belongs to an approved or posted migration.';
            }
            if ($type === 'invoice' && ! array_key_exists($row['service_type'] ?? $account?->account_type ?? '', config('accounting.receivables'))) {
                $errors[] = 'Invoice service type has no receivable control mapping.';
            }

            if ($amount <= 0) {
                $errors[] = 'Amount must be greater than zero.';
            }

            $dueDate = $row['due_date'] ?? '';

            if ($type === 'invoice' && $dueDate === '') {
                $errors[] = 'An invoice requires a due date.';
            }
            if ($dueDate !== '') {
                try {
                    if (CarbonImmutable::parse($dueDate)->toDateString() !== $dueDate) {
                        throw new \DomainException;
                    }
                } catch (\Throwable) {
                    $errors[] = 'Due date must be a valid YYYY-MM-DD date.';
                    $dueDate = '';
                }
            }

            $validated[] = [
                'row_number' => $row['row_number'],
                'row_type' => $type,
                'account_number' => $accountNumber,
                'reference' => $reference,
                'description' => $row['description'] ?? null,
                'amount_minor' => $amount,
                'due_date' => $dueDate !== '' ? CarbonImmutable::parse($dueDate)->toDateString() : null,
                'data' => ['service_type' => $row['service_type'] ?? null],
                'errors' => $errors,
                'status' => $errors === [] ? 'valid' : 'invalid',
            ];
        }

        return $validated;
    }

    private function amount(string $value, array &$errors): int
    {
        try {
            return Money::fromDecimal($value === '' ? '0' : $value)->minor;
        } catch (\Throwable) {
            $errors[] = 'Invalid monetary amount.';

            return 0;
        }
    }

    /** @return array<string, string> */
    private function parseDimensions(string $json, array &$errors): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            $errors[] = 'Dimensions must be a JSON object.';

            return [];
        }

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                $errors[] = 'Dimension keys and values must be strings.';
            }
        }

        return $decoded;
    }

    /** @return array<string, int> */
    private function totals(array $rows): array
    {
        $valid = array_filter($rows, fn ($row) => $row['status'] === 'valid');

        return [
            'debit_minor' => array_sum(array_column($valid, 'debit_minor')),
            'credit_minor' => array_sum(array_column($valid, 'credit_minor')),
            'amount_minor' => array_sum(array_column($valid, 'amount_minor')),
        ];
    }
}
