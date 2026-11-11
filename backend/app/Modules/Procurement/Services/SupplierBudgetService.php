<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Procurement\Models\Budget;
use App\Modules\Procurement\Models\BudgetCommitment;
use App\Modules\Procurement\Models\BudgetLine;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierBankAccount;
use App\Modules\Revenue\Models\Party;
use App\Modules\Revenue\Models\PartyRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** First procurement slice: supplier control and locked budget reservations. */
class SupplierBudgetService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function onboard(Party $party, string $supplierNumber, User $user): Supplier
    {
        if (! $party->isOrganisation() || $party->status !== 'active') {
            throw new \DomainException('An active organisation party is required for supplier onboarding.');
        }

        $supplier = Supplier::firstOrCreate(
            ['party_id' => $party->id],
            ['supplier_number' => $supplierNumber, 'status' => 'pending', 'created_by' => $user->getKey()],
        );
        if ($supplier->supplier_number !== $supplierNumber) {
            throw new \DomainException('This party is already onboarded under a different supplier number.');
        }
        PartyRole::firstOrCreate(['party_id' => $party->id, 'role' => 'supplier', 'effective_from' => now()->toDateString()], ['meta' => ['supplier_id' => $supplier->id]]);

        return $supplier;
    }

    public function approveSupplier(Supplier $supplier, User $approver): Supplier
    {
        if ($supplier->created_by === $approver->getKey()) {
            throw new \DomainException('Supplier onboarding requires an independent approver.');
        }
        if (! in_array($supplier->status, ['pending', 'under_review'], true)) {
            throw new \DomainException('Only pending supplier records can be approved.');
        }

        $supplier->forceFill(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
        $this->audit->record(action: 'procurement.supplier.approved', subject: $supplier, after: ['supplier_number' => $supplier->supplier_number], module: 'procurement');

        return $supplier->refresh();
    }

    public function requestBankDetails(Supplier $supplier, string $bank, string $accountName, string $accountNumber, User $requester): SupplierBankAccount
    {
        if ($supplier->status !== 'approved' || trim($accountNumber) === '') {
            throw new \DomainException('Approved suppliers and complete bank details are required.');
        }
        $active = $supplier->bankAccounts()->where('status', 'active')->first();
        $history = $active?->change_history ?? [];
        $history[] = [
            'changed_at' => now()->toIso8601String(),
            'previous_bank_name' => $active?->bank_name,
            'previous_account_name' => $active?->account_name,
            'previous_account_fingerprint' => $active ? hash('sha256', $active->account_number_encrypted) : null,
        ];

        $change = SupplierBankAccount::create([
            'supplier_id' => $supplier->id,
            'bank_name' => $bank,
            'account_name' => $accountName,
            'account_number_encrypted' => $accountNumber,
            'currency_code' => 'USD',
            'status' => 'pending_approval',
            'change_history' => $history,
            'submitted_by' => $requester->getKey(),
        ]);
        $this->audit->record(action: 'procurement.supplier.bank_details_submitted', subject: $change, after: ['supplier_id' => $supplier->id, 'change_id' => $change->id], module: 'procurement');

        return $change;
    }

    public function approveBankDetails(SupplierBankAccount $change, User $approver): SupplierBankAccount
    {
        return DB::connection('tenant')->transaction(function () use ($change, $approver) {
            $locked = SupplierBankAccount::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval' || $locked->submitted_by === $approver->getKey()) {
                throw new \DomainException('Bank-detail changes require a pending change and an independent approver.');
            }
            SupplierBankAccount::query()->where('supplier_id', $locked->supplier_id)->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
            $locked->forceFill(['status' => 'active', 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
            $this->audit->record(action: 'procurement.supplier.bank_details_approved', subject: $locked, after: ['supplier_id' => $locked->supplier_id, 'bank_account_id' => $locked->id], module: 'procurement');

            return $locked->refresh();
        });
    }

    /** @param array<int, array{account_code:string, dimensions?:array<string,string>, amount_minor:int}> $lines */
    public function createBudget(string $code, string $name, string $from, string $to, array $lines, User $preparer): Budget
    {
        if ($to < $from || $lines === []) {
            throw new \DomainException('A budget requires lines and a valid date range.');
        }

        return DB::connection('tenant')->transaction(function () use ($code, $name, $from, $to, $lines, $preparer) {
            $budget = Budget::create(['code' => $code, 'name' => $name, 'period_start' => $from, 'period_end' => $to, 'currency_code' => 'USD', 'status' => 'draft', 'version' => 1, 'created_by' => $preparer->getKey()]);
            foreach ($lines as $line) {
                if (($line['amount_minor'] ?? 0) <= 0) {
                    throw new \DomainException('Budget line amounts must be positive.');
                }
                BudgetLine::create(['budget_id' => $budget->id, 'account_code' => $line['account_code'], 'dimensions' => $line['dimensions'] ?? [], 'approved_minor' => $line['amount_minor']]);
            }

            return $budget->load('lines');
        });
    }

    public function approveBudget(Budget $budget, User $approver): Budget
    {
        if ($budget->status !== 'draft' || $budget->created_by === $approver->getKey()) {
            throw new \DomainException('Budget approval requires a draft and independent approver.');
        }
        $budget->forceFill(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
        $this->audit->record(action: 'procurement.budget.approved', subject: $budget, after: ['version' => $budget->version], module: 'procurement');

        return $budget->refresh();
    }

    public function reserve(BudgetLine $line, string $sourceType, string $sourceId, int $amount, string $key, User $user, ?User $overrideApprover = null, ?string $reason = null): BudgetCommitment
    {
        if ($amount <= 0) {
            throw new \DomainException('Commitment amount must be positive.');
        }

        return DB::connection('tenant')->transaction(function () use ($line, $sourceType, $sourceId, $amount, $key, $user, $overrideApprover, $reason) {
            $existing = BudgetCommitment::where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->budget_line_id !== $line->id || (int) $existing->reserved_minor !== $amount || $existing->source_id !== $sourceId) {
                    throw new \DomainException('Commitment key was reused with different inputs.');
                }

                return $existing;
            }
            $locked = BudgetLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            // The initial lookup is only a fast path. Recheck after taking the
            // shared budget-line lock so an identical concurrent retry returns
            // the row committed by the transaction ahead of it.
            $existing = BudgetCommitment::where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->budget_line_id !== $locked->id || (int) $existing->reserved_minor !== $amount || $existing->source_type !== $sourceType || $existing->source_id !== $sourceId) {
                    throw new \DomainException('Commitment key was reused with different inputs.');
                }

                return $existing;
            }
            if ($locked->budget->status !== 'approved') {
                throw new \DomainException('Only approved budget lines can be committed.');
            }
            if ($amount > $locked->availableMinor()) {
                if (! $overrideApprover || ! $reason || $overrideApprover->getKey() === $user->getKey()) {
                    throw new \DomainException('Insufficient available budget; an authorised reasoned independent override approval is required.');
                }
                $this->audit->record(action: 'procurement.budget.override_requested', subject: $locked, after: ['amount_minor' => $amount, 'reason' => $reason, 'approved_by' => $overrideApprover->getKey()], module: 'procurement');
            }
            $locked->increment('committed_minor', $amount);
            $commitment = BudgetCommitment::create(['budget_line_id' => $locked->id, 'source_type' => $sourceType, 'source_id' => $sourceId, 'status' => 'reserved', 'reserved_minor' => $amount, 'converted_minor' => 0, 'idempotency_key' => $key, 'reason' => $reason, 'created_by' => $user->getKey()]);
            $this->audit->record(action: 'procurement.commitment.reserved', subject: $commitment, after: ['amount_minor' => $amount, 'budget_line_id' => $locked->id], module: 'procurement');

            return $commitment;
        });
    }

    /** Convert a portion of reservation to actual expense without counting it twice. */
    public function convert(BudgetCommitment $commitment, int $amount): BudgetCommitment
    {
        return DB::connection('tenant')->transaction(function () use ($commitment, $amount) {
            $locked = BudgetCommitment::query()->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            $line = BudgetLine::query()->whereKey($locked->budget_line_id)->lockForUpdate()->firstOrFail();
            $remaining = (int) $locked->reserved_minor - (int) $locked->converted_minor;
            if ($amount <= 0 || $amount > $remaining) {
                throw new \DomainException('Conversion must be positive and no greater than the unconverted commitment.');
            }
            $locked->increment('converted_minor', $amount);
            $line->decrement('committed_minor', $amount);
            $line->increment('actual_minor', $amount);
            if ((int) $locked->fresh()->converted_minor === (int) $locked->reserved_minor) {
                $locked->forceFill(['status' => 'converted'])->save();
            }
            $this->audit->record(action: 'procurement.commitment.converted', subject: $locked, after: ['amount_minor' => $amount, 'remaining_minor' => $remaining - $amount], module: 'procurement');

            return $locked->refresh();
        });
    }

    public function release(BudgetCommitment $commitment): BudgetCommitment
    {
        return DB::connection('tenant')->transaction(function () use ($commitment) {
            $locked = BudgetCommitment::query()->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'released') {
                return $locked;
            }
            $remaining = (int) $locked->reserved_minor - (int) $locked->converted_minor;
            $line = BudgetLine::query()->whereKey($locked->budget_line_id)->lockForUpdate()->firstOrFail();
            $line->decrement('committed_minor', $remaining);
            $locked->forceFill(['status' => 'released', 'released_at' => now()])->save();
            $this->audit->record(action: 'procurement.commitment.released', subject: $locked, after: ['remaining_minor' => $remaining], module: 'procurement');

            return $locked->refresh();
        });
    }

    public function reverseActual(BudgetCommitment $commitment, int $amount): BudgetCommitment
    {
        return DB::connection('tenant')->transaction(function () use ($commitment, $amount) {
            $locked = BudgetCommitment::query()->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            $line = BudgetLine::query()->whereKey($locked->budget_line_id)->lockForUpdate()->firstOrFail();
            if ($amount <= 0 || $amount > (int) $locked->converted_minor || $amount > (int) $line->actual_minor) {
                throw new \DomainException('Credit reversal exceeds converted commitment actuals.');
            }
            $locked->decrement('converted_minor', $amount);
            $line->decrement('actual_minor', $amount);
            if ($locked->status === 'released') {
                $locked->save();
            } else {
                $locked->forceFill(['status' => 'reserved'])->save();
                $line->increment('committed_minor', $amount);
            }
            $this->audit->record(action: 'procurement.commitment.actual_reversed', subject: $locked, after: ['amount_minor' => $amount], module: 'procurement');

            return $locked->refresh();
        });
    }

    /** Shrink a requisition estimate to the independently awarded PO value. */
    public function reduceReservationTo(BudgetCommitment $commitment, int $newTotalMinor): BudgetCommitment
    {
        return $this->adjustReservationTo($commitment, $newTotalMinor);
    }

    /** Increase or release an unconverted reservation while checking the budget under its row lock. */
    public function adjustReservationTo(BudgetCommitment $commitment, int $newTotalMinor): BudgetCommitment
    {
        return DB::connection('tenant')->transaction(function () use ($commitment, $newTotalMinor) {
            $locked = BudgetCommitment::query()->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            $converted = (int) $locked->converted_minor;
            $reserved = (int) $locked->reserved_minor;
            if ($locked->status === 'released' || $newTotalMinor < $converted || $newTotalMinor <= 0) {
                throw new \DomainException('A reservation cannot be adjusted below actual expenditure or after release.');
            }
            $difference = $newTotalMinor - $reserved;
            if ($difference === 0) {
                return $locked;
            }
            $line = BudgetLine::query()->whereKey($locked->budget_line_id)->lockForUpdate()->firstOrFail();
            if ($difference > 0) {
                if ($difference > $line->availableMinor()) {
                    throw new \DomainException('Purchase-order increase exceeds the approved budget line available balance.');
                }
                $line->increment('committed_minor', $difference);
            } else {
                $line->decrement('committed_minor', abs($difference));
            }
            $locked->forceFill(['reserved_minor' => $newTotalMinor, 'status' => $newTotalMinor === $converted ? 'converted' : 'reserved'])->save();
            $this->audit->record(action: 'procurement.commitment.adjusted', subject: $locked, after: ['reserved_total_minor' => $newTotalMinor, 'difference_minor' => $difference], module: 'procurement');

            return $locked->refresh();
        });
    }

    public function prepareBudgetChange(Budget $budget, ?BudgetLine $from, BudgetLine $to, string $type, int $amount, string $reason, string $key, User $preparer): object
    {
        if ($budget->status !== 'approved' || ! in_array($type, ['transfer', 'amendment'], true) || $amount <= 0 || trim($reason) === '' || $to->budget_id !== $budget->id || ($from && $from->budget_id !== $budget->id) || ($type === 'transfer' && (! $from || $from->id === $to->id)) || ($type === 'amendment' && $from)) {
            throw new \DomainException('Budget change requires a reasoned change to valid lines in one approved budget.');
        }
        $existing = DB::connection('tenant')->table('procurement_budget_changes')->where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->budget_id !== $budget->id || $existing->from_line_id !== $from?->id || $existing->to_line_id !== $to->id || $existing->change_type !== $type || (int) $existing->amount_minor !== $amount) {
                throw new \DomainException('Budget change key was reused with different inputs.');
            }

            return $existing;
        }
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('procurement_budget_changes')->insert(['id' => $id, 'budget_id' => $budget->id, 'from_line_id' => $from?->id, 'to_line_id' => $to->id, 'change_type' => $type, 'amount_minor' => $amount, 'reason' => trim($reason), 'idempotency_key' => $key, 'prepared_by' => $preparer->getKey(), 'status' => 'pending_approval', 'created_at' => now(), 'updated_at' => now()]);

        return DB::connection('tenant')->table('procurement_budget_changes')->where('id', $id)->firstOrFail();
    }

    public function approveBudgetChange(string $id, User $approver): object
    {
        return DB::connection('tenant')->transaction(function () use ($id, $approver) {
            $change = DB::connection('tenant')->table('procurement_budget_changes')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($change->status === 'approved') {
                return $change;
            }
            if ($change->status !== 'pending_approval' || $change->prepared_by === $approver->getKey()) {
                throw new \DomainException('Budget change approval requires a pending change and independent approver.');
            }
            $ids = array_values(array_unique(array_filter([$change->from_line_id, $change->to_line_id])));
            sort($ids);
            $lines = BudgetLine::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $to = $lines->get($change->to_line_id);
            if (! $to || $to->budget_id !== $change->budget_id) {
                throw new \DomainException('Budget target line is missing or outside the approved budget.');
            }
            if ($change->change_type === 'transfer') {
                $from = $lines->get($change->from_line_id);
                if (! $from || $from->budget_id !== $change->budget_id || $from->availableMinor() < (int) $change->amount_minor) {
                    throw new \DomainException('Budget transfer exceeds the source line available balance.');
                }
                $from->increment('transferred_out_minor', (int) $change->amount_minor);
                $to->increment('transferred_in_minor', (int) $change->amount_minor);
            } else {
                $to->increment('approved_minor', (int) $change->amount_minor);
                DB::connection('tenant')->table('procurement_budgets')->where('id', $change->budget_id)->increment('version');
            }
            DB::connection('tenant')->table('procurement_budget_changes')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $approver->getKey(), 'approved_at' => now(), 'updated_at' => now()]);
            $this->audit->record(action: 'procurement.budget.'.$change->change_type.'_approved', subject: $to, after: ['budget_change_id' => $id, 'amount_minor' => (int) $change->amount_minor, 'reason' => $change->reason], module: 'procurement');

            return DB::connection('tenant')->table('procurement_budget_changes')->where('id', $id)->firstOrFail();
        });
    }
}
