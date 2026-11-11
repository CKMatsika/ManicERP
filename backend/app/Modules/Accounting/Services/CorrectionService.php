<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Controlled correction workflow for posted operational journals. */
class CorrectionService
{
    public function __construct(private readonly PostingService $posting, private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{account_code: string, debit_minor?: int, credit_minor?: int, party_id?: string|null, dimensions?: array, memo?: string|null}>  $lines
     */
    public function prepareCorrection(string $reason, array $lines, ?string $originalJournalEntryId, User $preparer, ?string $idempotencyKey = null, ?string $entryDate = null): object
    {
        if (trim($reason) === '' || count($lines) < 2) {
            throw new \DomainException('A correction requires a reason and at least two lines.');
        }
        if ($idempotencyKey !== null) {
            $existing = DB::connection('tenant')->table('accounting_corrections')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }
        if ($originalJournalEntryId) {
            $original = DB::connection('tenant')->table('journal_entries')->where('id', $originalJournalEntryId)->first();
            if (! $original || $original->status !== 'posted') {
                throw new \DomainException('The original journal entry must exist and be posted.');
            }
        }
        $id = (string) Str::ulid();
        $reference = 'CORR-'.strtoupper((string) Str::ulid());
        DB::connection('tenant')->transaction(function () use ($id, $reference, $reason, $lines, $originalJournalEntryId, $preparer, $idempotencyKey) {
            DB::connection('tenant')->table('accounting_corrections')->insert([
                'id' => $id, 'reference' => $reference, 'status' => 'pending_approval', 'reason' => trim($reason),
                'original_journal_entry_id' => $originalJournalEntryId, 'idempotency_key' => $idempotencyKey,
                'prepared_by' => $preparer->getKey(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::connection('tenant')->table('accounting_correction_lines')->insert([
                    'id' => (string) Str::ulid(), 'correction_id' => $id,
                    'account_code' => $line['account_code'],
                    'debit_minor' => (int) ($line['debit_minor'] ?? 0),
                    'credit_minor' => (int) ($line['credit_minor'] ?? 0),
                    'party_id' => $line['party_id'] ?? null,
                    'dimensions' => json_encode($line['dimensions'] ?? []),
                    'memo' => $line['memo'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit->record(action: 'accounting.correction.prepared', subject: null, after: ['correction_id' => $id, 'reference' => $reference, 'original_journal_entry_id' => $originalJournalEntryId, 'lines' => count($lines)], module: 'accounting');
        });

        return DB::connection('tenant')->table('accounting_corrections')->where('id', $id)->firstOrFail();
    }

    public function approveCorrection(string $correctionId, User $approver, ?string $entryDate = null): object
    {
        return DB::connection('tenant')->transaction(function () use ($correctionId, $approver, $entryDate) {
            $correction = DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->lockForUpdate()->firstOrFail();
            if ($correction->status !== 'pending_approval' || $correction->prepared_by === $approver->getKey()) {
                throw new \DomainException('Correction approval requires an independent approver.');
            }
            $storedLines = DB::connection('tenant')->table('accounting_correction_lines')->where('correction_id', $correctionId)->get();
            if ($storedLines->isEmpty()) {
                throw new \DomainException('Correction has no lines.');
            }
            $lines = [];
            $debitTotal = 0;
            $creditTotal = 0;
            foreach ($storedLines as $line) {
                $debit = (int) $line->debit_minor;
                $credit = (int) $line->credit_minor;
                if ($debit < 0 || $credit < 0 || ($debit === 0 && $credit === 0) || ($debit > 0 && $credit > 0)) {
                    throw new \DomainException('Each correction line must have exactly one non-negative side.');
                }
                $debitTotal += $debit;
                $creditTotal += $credit;
                $lines[] = [
                    'account_code' => $line->account_code,
                    'debit_minor' => $debit,
                    'credit_minor' => $credit,
                    'party_id' => $line->party_id,
                    'dimensions' => json_decode($line->dimensions ?? '{}', true) ?: [],
                    'memo' => $line->memo,
                ];
            }
            if ($debitTotal !== $creditTotal || $debitTotal === 0) {
                throw new \DomainException('Correction lines must balance.');
            }
            $date = $entryDate ?: now()->toDateString();
            $journal = $this->posting->post([
                'journal_type' => 'correction',
                'entry_date' => $date,
                'description' => 'Controlled correction '.$correction->reference.': '.$correction->reason,
                'currency_code' => 'USD',
                'lines' => $lines,
                'source_type' => 'accounting_correction',
                'source_id' => $correction->id,
                'idempotency_key' => 'accounting:correction:'.$correction->id,
                'created_by' => $approver->getKey(),
            ]);
            DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->update([
                'status' => 'applied', 'approved_by' => $approver->getKey(), 'approved_at' => now(),
                'applied_at' => now(), 'correction_journal_entry_id' => $journal->id, 'updated_at' => now(),
            ]);
            $this->audit->record(action: 'accounting.correction.applied', after: ['correction_id' => $correctionId, 'reference' => $correction->reference, 'journal_id' => $journal->id, 'original_journal_entry_id' => $correction->original_journal_entry_id], module: 'accounting');

            return DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->firstOrFail();
        });
    }

    public function rejectCorrection(string $correctionId, string $reason, User $approver): object
    {
        if (trim($reason) === '') {
            throw new \DomainException('A rejection reason is required.');
        }
        $correction = DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->lockForUpdate()->firstOrFail();
        if ($correction->status !== 'pending_approval' || $correction->prepared_by === $approver->getKey()) {
            throw new \DomainException('Correction rejection requires an independent approver.');
        }
        DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->update([
            'status' => 'rejected', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record(action: 'accounting.correction.rejected', after: ['correction_id' => $correctionId, 'reason' => trim($reason)], module: 'accounting');

        return DB::connection('tenant')->table('accounting_corrections')->where('id', $correctionId)->firstOrFail();
    }
}
