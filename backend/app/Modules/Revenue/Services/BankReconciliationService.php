<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Revenue\Models\BankAccount;
use App\Modules\Revenue\Models\BankReconciliation;
use App\Modules\Revenue\Models\BankStatement;
use App\Modules\Revenue\Models\BankStatementLine;
use App\Modules\Revenue\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bank statement staging, duplicate detection, matching suggestions and a
 * reconciliation summary. Bank-confirmed money is recorded as such and is not
 * confused with internal cashbook entries.
 */
class BankReconciliationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function importCsv(
        BankAccount $bankAccount,
        string $statementNumber,
        string $statementDate,
        int $openingBalanceMinor,
        int $closingBalanceMinor,
        string $contents,
        ?User $user = null,
    ): BankStatement {
        $hash = hash('sha256', $contents);
        $existing = BankStatement::where('bank_account_id', $bankAccount->id)
            ->where('statement_number', $statementNumber)
            ->first();

        if ($existing) {
            if ($existing->file_sha256 === $hash) {
                return $existing->load('lines');
            }

            throw new \DomainException('A different statement with this number has already been imported.');
        }

        $lines = $this->parseCsv($contents);

        if ($lines === []) {
            throw new \DomainException('The statement contains no transaction lines.');
        }

        return DB::connection('tenant')->transaction(function () use ($bankAccount, $statementNumber, $statementDate, $openingBalanceMinor, $closingBalanceMinor, $hash, $lines, $user) {
            $statement = BankStatement::create([
                'bank_account_id' => $bankAccount->id,
                'statement_number' => $statementNumber,
                'statement_date' => $statementDate,
                'opening_balance_minor' => $openingBalanceMinor,
                'closing_balance_minor' => $closingBalanceMinor,
                'file_sha256' => $hash,
                'status' => 'imported',
                'imported_by' => $user?->getKey(),
            ]);

            foreach ($lines as $index => $line) {
                BankStatementLine::create([
                    'bank_statement_id' => $statement->id,
                    'line_number' => $index + 1,
                    'value_date' => $line['value_date'],
                    'amount_minor' => $line['amount_minor'],
                    'reference' => $line['reference'],
                    'narrative' => $line['narrative'],
                ]);
            }

            $this->audit->record(
                action: 'revenue.bank_statement.imported',
                subject: $statement,
                after: ['statement' => $statementNumber, 'lines' => count($lines), 'closing_minor' => $closingBalanceMinor],
                module: 'revenue',
            );

            return $statement->load('lines');
        });
    }

    /**
     * @return array<int, array{line: BankStatementLine, payment: Payment|null, confidence: int, reason: string}>
     */
    public function suggestions(BankStatement $statement): array
    {
        $suggestions = [];

        $lines = $statement->lines()->where('status', 'unmatched')->orderBy('line_number')->get();

        foreach ($lines as $line) {
            $candidates = Payment::query()
                ->where('status', 'verified')
                ->where('amount_minor', $line->amount_minor)
                ->whereBetween('received_at', [
                    CarbonImmutable::parse($line->value_date)->subDays(7)->startOfDay(),
                    CarbonImmutable::parse($line->value_date)->addDays(7)->endOfDay(),
                ])
                ->get();

            $best = null;
            $confidence = 0;
            $reason = 'No amount/date match.';

            foreach ($candidates as $candidate) {
                $score = 70;
                $candidateReason = 'Amount and date proximity match.';

                if ($line->reference && (
                    str_contains((string) $candidate->external_reference, $line->reference)
                    || str_contains((string) $candidate->payment_number, $line->reference)
                )) {
                    $score = 100;
                    $candidateReason = 'Amount, date and reference match.';
                }

                if ($score > $confidence) {
                    $best = $candidate;
                    $confidence = $score;
                    $reason = $candidateReason;
                }
            }

            $suggestions[] = ['line' => $line, 'payment' => $best, 'confidence' => $confidence, 'reason' => $reason];
        }

        return $suggestions;
    }

    public function confirmMatch(BankStatementLine $line, Payment $payment, ?User $user = null): void
    {
        if ($line->status !== 'unmatched') {
            throw new \DomainException('This statement line is not open for matching.');
        }

        if ($line->amount_minor !== $payment->amount_minor) {
            throw new \DomainException('The statement amount does not match the payment amount.');
        }

        DB::connection('tenant')->transaction(function () use ($line, $payment, $user) {
            $line->status = 'matched';
            $line->matched_payment_id = $payment->id;
            $line->matched_at = now();
            $line->matched_by = $user?->getKey();
            $line->save();

            if ($payment->verification_source !== 'bank_import') {
                $payment->verification_source = 'bank_import';
                $payment->verified_at = $payment->verified_at ?? now();
                $payment->save();
            }

            $this->audit->record(
                action: 'revenue.bank_line.matched',
                subject: $line,
                after: ['payment' => $payment->payment_number, 'amount_minor' => $line->amount_minor],
                module: 'revenue',
            );
        });
    }

    public function ignore(BankStatementLine $line, string $reason, ?User $user = null): void
    {
        $line->status = 'ignored';
        $line->save();

        $this->audit->record(
            action: 'revenue.bank_line.ignored',
            subject: $line,
            after: ['reason' => $reason],
            module: 'revenue',
        );
    }

    public function reconcile(BankAccount $bankAccount, BankStatement $statement, ?User $user = null): BankReconciliation
    {
        $cashbook = (int) DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('a.code', $bankAccount->gl_account_code)
            ->where('e.status', 'posted')
            ->whereDate('e.entry_date', '<=', $statement->statement_date)
            ->selectRaw('COALESCE(SUM(l.debit_minor) - SUM(l.credit_minor), 0) as balance')
            ->value('balance');

        $variance = $statement->closing_balance_minor - $cashbook;

        $reconciliation = BankReconciliation::create([
            'bank_account_id' => $bankAccount->id,
            'bank_statement_id' => $statement->id,
            'period_start' => CarbonImmutable::parse($statement->statement_date)->startOfMonth()->toDateString(),
            'period_end' => $statement->statement_date->toDateString(),
            'status' => $variance === 0 ? 'completed' : 'draft',
            'statement_closing_minor' => $statement->closing_balance_minor,
            'cashbook_balance_minor' => $cashbook,
            'variance_minor' => $variance,
            'reconciled_at' => $variance === 0 ? now() : null,
            'reconciled_by' => $variance === 0 ? $user?->getKey() : null,
        ]);

        $this->audit->record(
            action: 'revenue.bank.reconciled',
            subject: $reconciliation,
            after: [
                'statement' => $statement->statement_number,
                'cashbook_minor' => $cashbook,
                'statement_minor' => $statement->closing_balance_minor,
                'variance_minor' => $variance,
            ],
            module: 'revenue',
        );

        return $reconciliation;
    }

    /** @return array<int, array{value_date: string, amount_minor: int, reference: string|null, narrative: string|null}> */
    private function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($contents)) ?: []);

        if ($rows === []) {
            return [];
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($rows));
        $index = array_flip($header);
        $lines = [];

        foreach ($rows as $row) {
            if (array_filter($row, fn ($cell) => trim((string) $cell) !== '') === []) {
                continue;
            }

            $valueDate = trim((string) ($row[$index['value_date'] ?? 0] ?? ''));

            if ($valueDate === '') {
                continue;
            }

            $amountRaw = trim((string) ($row[$index['amount'] ?? 1] ?? '0'));
            $amount = Money::fromDecimal($amountRaw, 'USD');

            $lines[] = [
                'value_date' => CarbonImmutable::parse($valueDate)->toDateString(),
                'amount_minor' => $amount->minor,
                'reference' => isset($index['reference']) ? trim((string) ($row[$index['reference']] ?? '')) : null,
                'narrative' => isset($index['narrative']) ? trim((string) ($row[$index['narrative']] ?? '')) : null,
            ];
        }

        return $lines;
    }
}
