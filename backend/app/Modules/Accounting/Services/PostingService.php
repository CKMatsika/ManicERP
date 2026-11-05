<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\Dimension;
use App\Modules\Accounting\Models\DimensionValue;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\PostingRule;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deterministic double-entry posting engine. All amounts are integer minor
 * units; entries are immutable once posted and corrected only by linked
 * reversals.
 */
class PostingService
{
    public function __construct(
        private readonly PeriodService $periods,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *   journal_type: string,
     *   entry_date: string,
     *   description: string,
     *   currency_code?: string,
     *   lines: array<int, array{account_code?: string, gl_account_id?: string, debit_minor?: int, credit_minor?: int, dimensions?: array<string, string>, memo?: string, party_id?: string}>,
     *   source_type?: string,
     *   source_id?: string,
     *   idempotency_key?: string,
     *   created_by?: string
     * }  $payload
     */
    public function post(array $payload): JournalEntry
    {
        $entryDate = CarbonImmutable::parse($payload['entry_date'])->toDateString();
        $currency = $payload['currency_code'] ?? 'USD';

        if (! empty($payload['idempotency_key'])) {
            $existing = JournalEntry::where('idempotency_key', $payload['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($payload['source_type']) && ! empty($payload['source_id'])) {
            $existing = JournalEntry::where('source_type', $payload['source_type'])
                ->where('source_id', $payload['source_id'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $chart = ChartVersion::query()->where('status', 'active')->first();

        if (! $chart) {
            throw new \DomainException('No active chart of accounts. Approve a chart version before posting.');
        }

        if ($chart->production_blocker) {
            // Demonstration charts may still be used for non-production pilots,
            // and the status is surfaced in reports and UI.
        }

        $lines = $payload['lines'] ?? [];

        if (count($lines) < 2) {
            throw new \DomainException('A journal entry requires at least two lines.');
        }

        $prepared = [];
        $totalDebit = 0;
        $totalCredit = 0;
        $lineNo = 1;

        foreach ($lines as $line) {
            $account = $this->resolveAccount($chart, $line);
            $debit = (int) ($line['debit_minor'] ?? 0);
            $credit = (int) ($line['credit_minor'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new \DomainException('Debit and credit amounts cannot be negative.');
            }

            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new \DomainException('Each journal line must contain exactly one non-zero debit or credit.');
            }

            if (! $account->isPostable()) {
                throw new \DomainException("Account [{$account->code}] is a {$account->account_type} account and cannot be posted to directly.");
            }

            if (! $account->isActiveOn($entryDate)) {
                throw new \DomainException("Account [{$account->code}] is not active on {$entryDate}.");
            }

            $dimensions = $this->validateDimensions($line['dimensions'] ?? [], $entryDate);

            $prepared[] = [
                'line_no' => $lineNo++,
                'gl_account_id' => $account->id,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'dimensions' => $dimensions,
                'memo' => $line['memo'] ?? null,
                'party_id' => $line['party_id'] ?? null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit !== $totalCredit) {
            throw new \DomainException("Journal entry is unbalanced: debits {$totalDebit} vs credits {$totalCredit}.");
        }

        if ($totalDebit === 0) {
            throw new \DomainException('Journal entry total must be greater than zero.');
        }

        $period = $this->periods->forPosting($entryDate);

        return DB::connection('tenant')->transaction(function () use ($payload, $currency, $entryDate, $period, $prepared, $totalDebit, $totalCredit) {
            $year = CarbonImmutable::parse($entryDate)->format('Y');
            $number = $this->sequences->next("journal:{$year}", "JE-{$year}", 6, $year);

            $entry = JournalEntry::create([
                'entry_number' => $number,
                'journal_type' => $payload['journal_type'],
                'source_type' => $payload['source_type'] ?? null,
                'source_id' => $payload['source_id'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'entry_date' => $entryDate,
                'posting_date' => $entryDate,
                'period_id' => $period->id,
                'currency_code' => $currency,
                'exchange_rate' => 1,
                'description' => $payload['description'],
                'status' => JournalEntry::STATUS_POSTED,
                'total_debit_minor' => $totalDebit,
                'total_credit_minor' => $totalCredit,
                'posted_at' => now(),
                'posted_by' => $payload['created_by'] ?? auth()->id(),
                'created_by' => $payload['created_by'] ?? auth()->id(),
            ]);

            foreach ($prepared as $line) {
                JournalLine::create([...$line, 'journal_entry_id' => $entry->id]);
            }

            $this->audit->record(
                action: 'accounting.journal.posted',
                subject: $entry,
                after: [
                    'entry_number' => $entry->entry_number,
                    'description' => $entry->description,
                    'total_minor' => $totalDebit,
                    'currency' => $currency,
                    'line_count' => count($prepared),
                ],
                module: 'accounting',
            );

            return $entry->load('lines');
        });
    }

    /**
     * Post a simple two-line entry from an approved posting rule.
     *
     * @param  array<string, string>  $dimensions
     */
    public function postFromRule(
        string $eventCode,
        int $amountMinor,
        string $description,
        string $entryDate,
        string $sourceType,
        string $sourceId,
        array $dimensions = [],
        ?string $debitOverride = null,
        ?string $creditOverride = null,
        ?string $idempotencyKey = null,
    ): JournalEntry {
        if ($amountMinor <= 0) {
            throw new \DomainException('Posting amount must be greater than zero.');
        }

        $rule = PostingRule::where('event_code', $eventCode)
            ->where('status', 'approved')
            ->orderByDesc('version')
            ->first();

        if (! $rule) {
            throw new \DomainException("No approved posting rule for event [{$eventCode}].");
        }

        $debit = $debitOverride ?? $rule->debit_account_code;
        $credit = $creditOverride ?? $rule->credit_account_code;

        foreach ($rule->required_dimensions ?? [] as $required) {
            if (empty($dimensions[$required])) {
                throw new \DomainException("Posting rule [{$eventCode}] requires dimension [{$required}].");
            }
        }

        return $this->post([
            'journal_type' => $eventCode,
            'entry_date' => $entryDate,
            'description' => $description,
            'lines' => [
                ['account_code' => $debit, 'debit_minor' => $amountMinor, 'dimensions' => $dimensions],
                ['account_code' => $credit, 'credit_minor' => $amountMinor, 'dimensions' => $dimensions],
            ],
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function reverse(JournalEntry $entry, string $reason, string $reversalDate, ?string $userId = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw new \DomainException('Only posted journal entries can be reversed.');
        }

        if ($entry->reversed_by_id) {
            throw new \DomainException('This journal entry has already been reversed.');
        }

        if (trim($reason) === '') {
            throw new \DomainException('A reversal reason is required.');
        }

        $this->periods->forPosting($reversalDate);

        return DB::connection('tenant')->transaction(function () use ($entry, $reason, $reversalDate, $userId) {
            $lines = $entry->lines()->get();

            $reversal = $this->post([
                'journal_type' => 'reversal',
                'entry_date' => $reversalDate,
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'currency_code' => $entry->currency_code,
                'lines' => $lines->map(fn (JournalLine $line) => [
                    'gl_account_id' => $line->gl_account_id,
                    'debit_minor' => $line->credit_minor,
                    'credit_minor' => $line->debit_minor,
                    'dimensions' => $line->dimensions ?? [],
                    'memo' => 'Reversal',
                    'party_id' => $line->party_id,
                ])->all(),
                'source_type' => 'reversal',
                'source_id' => $entry->id,
                'idempotency_key' => 'reversal:'.$entry->id,
                'created_by' => $userId,
            ]);

            $reversal->reversal_of_id = $entry->id;
            $reversal->save();

            $fresh = $entry->fresh();
            $fresh->reversed_by_id = $reversal->id;
            $fresh->save();

            $this->audit->record(
                action: 'accounting.journal.reversed',
                subject: $entry,
                before: ['status' => 'posted'],
                after: ['status' => 'posted', 'reversed_by' => $reversal->entry_number, 'reason' => Str::limit($reason, 200)],
                module: 'accounting',
            );

            return $reversal;
        });
    }

    private function resolveAccount(ChartVersion $chart, array $line): GlAccount
    {
        if (! empty($line['gl_account_id'])) {
            $account = GlAccount::where('chart_version_id', $chart->id)->find($line['gl_account_id']);
        } elseif (! empty($line['account_code'])) {
            $account = GlAccount::where('chart_version_id', $chart->id)->where('code', $line['account_code'])->first();
        } else {
            throw new \DomainException('Every journal line needs an account code or account id.');
        }

        if (! $account) {
            throw new \DomainException('Unknown chart account ['.($line['account_code'] ?? $line['gl_account_id']).'].');
        }

        return $account;
    }

    /**
     * @param  array<string, string>  $dimensions
     * @return array<string, string>
     */
    private function validateDimensions(array $dimensions, string $entryDate): array
    {
        if ($dimensions === []) {
            return [];
        }

        $valid = [];
        $known = Dimension::query()->pluck('id', 'code');

        foreach ($dimensions as $code => $value) {
            if (! isset($known[$code])) {
                throw new \DomainException("Unknown accounting dimension [{$code}].");
            }

            if (! is_string($value) || trim($value) === '') {
                throw new \DomainException("Dimension [{$code}] requires a value.");
            }

            $match = DimensionValue::query()
                ->where('dimension_id', $known[$code])
                ->where('code', $value)
                ->where(function ($query) use ($entryDate) {
                    $query->whereNull('active_from')->orWhereDate('active_from', '<=', $entryDate);
                })
                ->where(function ($query) use ($entryDate) {
                    $query->whereNull('active_to')->orWhereDate('active_to', '>=', $entryDate);
                })
                ->exists();

            if (! $match) {
                throw new \DomainException("Unknown value [{$value}] for dimension [{$code}].");
            }

            $valid[$code] = $value;
        }

        return $valid;
    }
}
