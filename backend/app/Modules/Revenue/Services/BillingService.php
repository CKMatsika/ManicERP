<?php

namespace App\Modules\Revenue\Services;

use App\Models\User;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\RevenuePolicyService;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\NumberSequenceService;
use App\Modules\Revenue\Models\Bill;
use App\Modules\Revenue\Models\BillingRun;
use App\Modules\Revenue\Models\BillingRunItem;
use App\Modules\Revenue\Models\BillLine;
use App\Modules\Revenue\Models\CustomerAccount;
use App\Modules\Revenue\Models\MeterReading;
use App\Modules\Revenue\Models\ServiceConnection;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Preview -> approve -> post billing runs. Calculations are snapshotted,
 * posting is chunked and restart-safe, and the database enforces one active
 * bill per account and period.
 */
class BillingService
{
    public const ENGINE_VERSION = 'billing-engine/1';

    public function __construct(
        private readonly TariffCalculator $tariffs,
        private readonly PostingService $posting,
        private readonly NumberSequenceService $sequences,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Issue an idempotent one-off licence fee through the shared bill and
     * journal pipeline. Licence-specific fee configuration supplies the
     * amount; this service still owns the bill and accounting entry.
     */
    public function issueLicenceFee(CustomerAccount $account, int $amountMinor, string $chargeType, string $description, string $idempotencyKey, ?User $user = null): Bill
    {
        if ($amountMinor <= 0 || $account->status !== 'active') {
            throw new \DomainException('A positive licence fee and active customer account are required.');
        }

        $existing = Bill::where('licence_application_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::connection('tenant')->transaction(function () use ($account, $amountMinor, $chargeType, $description, $idempotencyKey, $user) {
            $existing = Bill::where('licence_application_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $date = CarbonImmutable::now()->toDateString();
            $year = CarbonImmutable::parse($date)->format('Y');
            $bill = Bill::create([
                'bill_number' => $this->sequences->next("bill:{$year}", "BILL-{$year}", 6, $year),
                'customer_account_id' => $account->id,
                'service_type' => 'licence',
                'period_start' => $date,
                'period_end' => $date,
                'issue_date' => $date,
                'due_date' => CarbonImmutable::parse($date)->addDays((int) config('accounting.bill_default_due_days', 30))->toDateString(),
                'status' => 'issued',
                'currency_code' => $account->currency_code,
                'total_minor' => $amountMinor,
                'paid_minor' => 0,
                'balance_minor' => $amountMinor,
                'calculation_snapshot' => ['licence_application_key' => $idempotencyKey, 'one_off' => true],
                'licence_application_key' => $idempotencyKey,
                'approved_by' => $user?->getKey(),
                'approved_at' => now(),
            ]);
            $line = [
                'charge_type' => $chargeType,
                'description' => $description,
                'quantity' => 1,
                'unit_rate_minor' => $amountMinor,
                'amount_minor' => $amountMinor,
                'dimensions' => [],
            ];
            BillLine::create(['bill_id' => $bill->id, ...$line]);
            $journal = $this->postBillJournal($bill, [$line]);
            $bill->forceFill(['journal_entry_id' => $journal->id])->save();
            $this->audit->record(action: 'revenue.billing.one_off_issued', subject: $bill, after: ['bill_number' => $bill->bill_number, 'amount_minor' => $amountMinor, 'charge_type' => $chargeType], module: 'revenue');

            return $bill;
        });
    }

    public function preview(
        string $serviceType,
        string $periodStart,
        string $periodEnd,
        array $options = [],
        ?User $user = null,
    ): BillingRun {
        $start = CarbonImmutable::parse($periodStart)->toDateString();
        $end = CarbonImmutable::parse($periodEnd)->toDateString();

        if ($end < $start) {
            throw new \DomainException('Billing period end cannot be before its start.');
        }

        $key = $options['idempotency_key'] ?? hash('sha256', "run|{$serviceType}|{$start}|{$end}");

        $existing = BillingRun::where('idempotency_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        $accounts = $this->eligibleAccounts($serviceType);

        if ($accounts->isEmpty()) {
            throw new \DomainException("No active accounts are eligible for [{$serviceType}] billing in this period.");
        }

        return DB::connection('tenant')->transaction(function () use ($serviceType, $start, $end, $key, $accounts, $user) {
            $year = CarbonImmutable::parse($start)->format('Y');
            $runNumber = $this->sequences->next("billing_run:{$year}", "BR-{$year}", 5, $year);

            $run = BillingRun::create([
                'run_number' => $runNumber,
                'service_type' => $serviceType,
                'period_start' => $start,
                'period_end' => $end,
                'status' => 'previewed',
                'idempotency_key' => $key,
                'created_by' => $user?->getKey(),
                'notes' => $options['notes'] ?? null,
            ]);

            $included = 0;
            $skipped = 0;
            $total = 0;
            $estimated = 0;

            foreach ($accounts as $account) {
                $charges = $this->buildCharges($account, $serviceType, $start, $end);

                if ($charges['lines'] === []) {
                    BillingRunItem::create([
                        'billing_run_id' => $run->id,
                        'customer_account_id' => $account->id,
                        'amount_minor' => 0,
                        'status' => 'skipped',
                        'error_message' => $charges['reason'] ?? 'No charge could be calculated.',
                        'charge_type' => $serviceType,
                    ]);
                    $skipped++;

                    continue;
                }

                $amount = array_sum(array_column($charges['lines'], 'amount_minor'));

                BillingRunItem::create([
                    'billing_run_id' => $run->id,
                    'customer_account_id' => $account->id,
                    'amount_minor' => $amount,
                    'consumption' => $charges['consumption'] ?? null,
                    'status' => 'included',
                    'charge_type' => $serviceType,
                    'tariff_version_id' => $charges['tariff_version_id'] ?? null,
                    'calculation' => [
                        'engine' => self::ENGINE_VERSION,
                        'period' => ['start' => $start, 'end' => $end],
                        'service_type' => $serviceType,
                        'tariff_code' => $charges['tariff_code'] ?? null,
                        'tariff_version_id' => $charges['tariff_version_id'] ?? null,
                        'consumption' => $charges['consumption'] ?? null,
                        'reading_id' => $charges['reading_id'] ?? null,
                        'source' => $charges['source'] ?? 'calculated',
                        'lines' => $charges['lines'],
                    ],
                ]);

                $included++;
                $total += $amount;

                if (($charges['source'] ?? null) === 'estimated') {
                    $estimated++;
                }
            }

            $run->forceFill([
                'control_totals' => [
                    'eligible_accounts' => $accounts->count(),
                    'included' => $included,
                    'skipped' => $skipped,
                    'estimated' => $estimated,
                    'total_minor' => $total,
                ],
            ])->save();

            $this->audit->record(
                action: 'revenue.billing.previewed',
                subject: $run,
                after: ['run_number' => $run->run_number, 'total_minor' => $total, 'included' => $included, 'skipped' => $skipped],
                module: 'revenue',
            );

            return $run->load('items');
        });
    }

    public function approve(BillingRun $run, ?User $user = null): BillingRun
    {
        if (in_array($run->status, ['posting', 'posted', 'approved'], true)) {
            return $run;
        }

        if ($run->status !== 'previewed') {
            throw new \DomainException("Billing run [{$run->run_number}] cannot be approved from status [{$run->status}].");
        }

        if ($user && $run->created_by && $run->created_by === $user->getKey()) {
            throw new \DomainException('Separation of duties: a billing run must be approved by a different officer from the one who prepared it.');
        }

        $run->status = 'approved';
        $run->approved_by = $user?->getKey();
        $run->approved_at = now();
        $run->save();

        $this->audit->record(
            action: 'revenue.billing.approved',
            subject: $run,
            before: ['status' => 'previewed'],
            after: ['status' => 'approved', 'total_minor' => $run->control_totals['total_minor'] ?? 0],
            module: 'revenue',
        );

        return $run;
    }

    public function post(BillingRun $run, ?User $user = null): BillingRun
    {
        if ($run->status === 'posted') {
            return $run;
        }

        if ($run->status !== 'approved') {
            throw new \DomainException("Billing run [{$run->run_number}] must be approved before posting.");
        }

        $run->items()->where('status', 'included')->orderBy('id')->chunkById(100, function ($items) use ($run, $user) {
            foreach ($items as $item) {
                $this->postItem($run, $item, $user);
            }
        });

        $billed = $run->items()->where('status', 'billed')->count();
        $total = (int) $run->items()->where('status', 'billed')->sum('amount_minor');

        $run->status = 'posted';
        $run->posted_at = now();
        $run->forceFill([
            'control_totals' => array_merge($run->control_totals ?? [], [
                'posted_bills' => $billed,
                'posted_total_minor' => $total,
                'posted_at' => now()->toIso8601String(),
            ]),
        ])->save();

        $this->audit->record(
            action: 'revenue.billing.posted',
            subject: $run,
            after: ['status' => 'posted', 'bills' => $billed, 'total_minor' => $total],
            module: 'revenue',
        );

        return $run;
    }

    private function postItem(BillingRun $run, BillingRunItem $item, ?User $user): void
    {
        if ($item->bill_id) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($run, $item, $user) {
            $locked = BillingRunItem::query()->lockForUpdate()->find($item->id);

            if ($locked->bill_id) {
                return;
            }

            $account = CustomerAccount::findOrFail($locked->customer_account_id);

            $existing = Bill::where('customer_account_id', $account->id)
                ->where('service_type', $run->service_type)
                ->whereDate('period_start', $run->period_start)
                ->whereDate('period_end', $run->period_end)
                ->where('status', '!=', 'void')
                ->first();

            if ($existing) {
                $locked->forceFill(['bill_id' => $existing->id, 'status' => 'billed'])->save();

                return;
            }

            $calculation = $locked->calculation ?? [];
            $lines = $calculation['lines'] ?? [];

            if ($lines === []) {
                $locked->forceFill(['status' => 'error', 'error_message' => 'Snapshot missing calculation lines.'])->save();

                throw new \DomainException('Billing snapshot is incomplete; re-run the preview.');
            }

            $issueDate = CarbonImmutable::parse($run->period_end)->toDateString();
            $dueDate = CarbonImmutable::parse($issueDate)->addDays((int) config('accounting.bill_default_due_days', 30))->toDateString();
            $year = CarbonImmutable::parse($issueDate)->format('Y');
            $billNumber = $this->sequences->next("bill:{$year}", "BILL-{$year}", 6, $year);

            $total = array_sum(array_column($lines, 'amount_minor'));

            $bill = Bill::create([
                'bill_number' => $billNumber,
                'customer_account_id' => $account->id,
                'billing_run_id' => $run->id,
                'service_type' => $run->service_type,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => 'issued',
                'currency_code' => $account->currency_code,
                'total_minor' => $total,
                'paid_minor' => 0,
                'balance_minor' => $total,
                'calculation_snapshot' => $calculation,
                'approved_by' => $user?->getKey(),
                'approved_at' => now(),
            ]);

            foreach ($lines as $line) {
                BillLine::create([
                    'bill_id' => $bill->id,
                    'charge_type' => $line['charge_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_rate_minor' => $line['unit_rate_minor'] ?? 0,
                    'amount_minor' => $line['amount_minor'],
                    'tariff_version_id' => $line['tariff_version_id'] ?? null,
                    'meter_reading_id' => $line['meter_reading_id'] ?? null,
                    'dimensions' => $line['dimensions'] ?? [],
                ]);
            }

            $journal = $this->postBillJournal($bill, $lines);

            $bill->forceFill(['journal_entry_id' => $journal->id])->save();

            $locked->forceFill(['bill_id' => $bill->id, 'status' => 'billed'])->save();
        });
    }

    private function postBillJournal(Bill $bill, array $lines): JournalEntry
    {
        $receivableByType = config('accounting.receivables');
        $revenueByType = config('accounting.revenue');

        $debits = [];
        $credits = [];
        $policySnapshot = [];

        foreach ($lines as $line) {
            $chargeType = $line['charge_type'] ?? 'rates';
            $policy = app(RevenuePolicyService::class)->resolve($chargeType, $bill->issue_date->toDateString());
            if (! $policy && ! ChartVersion::where('status', 'active')->where('production_blocker', true)->exists()) {
                throw new \DomainException('An approved effective revenue policy is required for production billing.');
            }
            $receivable = $policy?->receivable_account_code ?? ($receivableByType[$chargeType] ?? $receivableByType['rates']);
            $revenue = $policy
                ? ($policy->recognition_method === 'immediate' ? $policy->revenue_account_code : $policy->deferred_account_code)
                : ($revenueByType[$chargeType] ?? $revenueByType['rates']);
            $policySnapshot[] = ['charge_type' => $chargeType, 'policy_id' => $policy?->id, 'version' => $policy?->version,
                'recognition_method' => $policy?->recognition_method ?? 'demonstration_immediate', 'debit' => $receivable, 'credit' => $revenue,
                'revenue_account_code' => $policy?->revenue_account_code ?? $revenue, 'recognition_rule' => $policy?->recognition_rule];

            $debits[$receivable] = ($debits[$receivable] ?? 0) + (int) $line['amount_minor'];
            $credits[$revenue] = ($credits[$revenue] ?? 0) + (int) $line['amount_minor'];
        }

        $journalLines = [];
        $bill->forceFill(['calculation_snapshot' => [...($bill->calculation_snapshot ?? []), 'revenue_policies' => $policySnapshot],
            'revenue_policy_id' => count($policySnapshot) === 1 ? $policySnapshot[0]['policy_id'] : null,
            'revenue_recognition_method' => count($policySnapshot) === 1 ? $policySnapshot[0]['recognition_method'] : 'mixed'])->save();

        foreach ($debits as $code => $amount) {
            $journalLines[] = ['account_code' => $code, 'debit_minor' => $amount, 'memo' => "Bill {$bill->bill_number}"];
        }

        foreach ($credits as $code => $amount) {
            $journalLines[] = ['account_code' => $code, 'credit_minor' => $amount, 'memo' => "Bill {$bill->bill_number}"];
        }

        return $this->posting->post([
            'journal_type' => 'billing',
            'entry_date' => $bill->issue_date->toDateString(),
            'description' => "Billing {$bill->bill_number} for account",
            'currency_code' => $bill->currency_code,
            'lines' => $journalLines,
            'source_type' => 'bill',
            'source_id' => $bill->id,
            'idempotency_key' => 'bill:'.$bill->id,
        ]);
    }

    /** @return Collection<int, CustomerAccount> */
    private function eligibleAccounts(string $serviceType)
    {
        $query = CustomerAccount::query()->where('status', 'active');

        if ($serviceType === 'rates' || $serviceType === 'rental') {
            $query->where('account_type', $serviceType)->whereNotNull('property_id');
        } elseif ($serviceType === 'water') {
            $query->whereHas('connections', fn ($q) => $q->where('service_type', 'water')->where('status', 'active'));
        } elseif ($serviceType === 'refuse') {
            $query->where(function ($q) {
                $q->whereIn('account_type', ['refuse', 'combined'])
                    ->orWhereHas('connections', fn ($c) => $c->where('service_type', 'refuse')->where('status', 'active'));
            });
        } else {
            $query->where('account_type', $serviceType);
        }

        return $query->with(['property.ward', 'connections.meters'])->orderBy('account_number')->get();
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, reason?: string, consumption?: string, source?: string, reading_id?: string, tariff_code?: string, tariff_version_id?: string}
     */
    private function buildCharges(CustomerAccount $account, string $serviceType, string $start, string $end): array
    {
        $tariff = $this->tariffs->effectiveTariff($account->id, $serviceType, $end);

        if (! $tariff) {
            return ['lines' => [], 'reason' => "No approved {$serviceType} tariff is effective for this period."];
        }

        $dimensions = [];
        if ($account->property?->ward) {
            $dimensions['ward'] = $account->property->ward->code;
        }

        if ($serviceType === 'rates') {
            $valuation = $account->property?->approvedValuation();

            if (! $valuation) {
                return ['lines' => [], 'reason' => 'No approved property valuation for rates billing.'];
            }

            $result = $this->tariffs->calculate($tariff, null, (int) $valuation->total_value_minor, $start, $end);

            return [
                'lines' => [[
                    'charge_type' => 'rates',
                    'description' => "Property rates {$start} to {$end}",
                    'quantity' => 1,
                    'unit_rate_minor' => 0,
                    'amount_minor' => $result['amount_minor'],
                    'tariff_version_id' => $tariff->id,
                    'dimensions' => $dimensions,
                    'explanation' => $result['breakdown'],
                ]],
                'source' => 'valuation',
                'tariff_code' => $tariff->code,
                'tariff_version_id' => $tariff->id,
            ];
        }

        if ($serviceType === 'water') {
            $connection = $account->connections
                ->first(fn (ServiceConnection $c) => $c->service_type === 'water' && $c->status === 'active');

            if (! $connection) {
                return ['lines' => [], 'reason' => 'No active water connection.'];
            }

            $reading = MeterReading::where('service_connection_id', $connection->id)
                ->whereIn('status', ['confirmed', 'captured'])
                ->whereBetween('read_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->orderByDesc('read_at')
                ->first();

            $source = 'actual';
            $consumption = $reading?->consumption;

            if (! $reading || $consumption === null) {
                $estimated = $this->estimateConsumption($connection);
                if ($estimated === null) {
                    return ['lines' => [], 'reason' => 'No meter reading and no reading history to estimate from.'];
                }
                $consumption = $estimated;
                $source = 'estimated';
            }

            $result = $this->tariffs->calculate($tariff, (string) $consumption, null, $start, $end);

            return [
                'lines' => [[
                    'charge_type' => 'water',
                    'description' => $source === 'estimated'
                        ? "Estimated water charge {$start} to {$end}"
                        : "Water charge {$start} to {$end}",
                    'quantity' => $consumption,
                    'unit_rate_minor' => 0,
                    'amount_minor' => $result['amount_minor'],
                    'tariff_version_id' => $tariff->id,
                    'meter_reading_id' => $reading?->id,
                    'dimensions' => $dimensions,
                    'explanation' => $result['breakdown'],
                ]],
                'consumption' => (string) $consumption,
                'source' => $source,
                'reading_id' => $reading?->id,
                'tariff_code' => $tariff->code,
                'tariff_version_id' => $tariff->id,
            ];
        }

        $result = $this->tariffs->calculate($tariff, null, null, $start, $end);

        return [
            'lines' => [[
                'charge_type' => $serviceType,
                'description' => ucfirst($serviceType)." charge {$start} to {$end}",
                'quantity' => 1,
                'unit_rate_minor' => 0,
                'amount_minor' => $result['amount_minor'],
                'tariff_version_id' => $tariff->id,
                'dimensions' => $dimensions,
                'explanation' => $result['breakdown'],
            ]],
            'source' => 'fixed',
            'tariff_code' => $tariff->code,
            'tariff_version_id' => $tariff->id,
        ];
    }

    private function estimateConsumption(ServiceConnection $connection): ?string
    {
        $readings = MeterReading::where('service_connection_id', $connection->id)
            ->whereNotNull('consumption')
            ->where('source', 'actual')
            ->orderByDesc('read_at')
            ->limit(3)
            ->pluck('consumption');

        if ($readings->isEmpty()) {
            return null;
        }

        $totalMilli = 0;
        foreach ($readings as $value) {
            $totalMilli += Decimal::toScaled((string) $value, 3);
        }

        return Decimal::fromScaled(Decimal::roundDiv($totalMilli, $readings->count()), 3);
    }
}
