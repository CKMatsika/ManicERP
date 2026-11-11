<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingSourceDocument;
use App\Modules\Accounting\Models\ChartVersion;
use App\Modules\Accounting\Models\GlAccount;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Models\ReportLine;
use App\Modules\Accounting\Models\ReportLineMapping;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PermissionService;
use App\Modules\Platform\Services\TenantManager;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ReportMappingService
{
    /** Source-backed headings are internal identifiers, never official posting codes. */
    public function templates(User $user): void
    {
        $this->authorize($user, 'accounting.report_mapping.manage');
        $source = AccountingSourceDocument::where('internal_title', 'Zimbabwe Financial Reporting Manual')->firstOrFail();
        $templates = [
            'position' => ['Statement of financial position', 'Appendix 1 / PDF pp.133-134; §4.4', [
                ['ppe', 'Property, plant and equipment', 'Non-current assets', 'debit'],
                ['intangible', 'Intangible assets', 'Non-current assets', 'debit'],
                ['cash', 'Cash and cash equivalents', 'Current assets', 'debit'],
                ['receivables', 'Receivables', 'Current assets', 'debit'],
                ['inventory', 'Inventories', 'Current assets', 'debit'],
                ['payables', 'Payables and accruals', 'Current liabilities', 'credit'],
                ['liabilities', 'Other liabilities', 'Current liabilities', 'credit'],
                ['surplus', 'Accumulated surplus / deficit', 'Net assets / equity', 'credit'],
            ]],
            'performance' => ['Statement of financial performance (expenses by nature)', 'Appendix 2 / PDF pp.134-135; §4.3.2(b) PDF p.26', [
                ['taxes', 'Taxes', 'Revenue without binding arrangements', 'credit'],
                ['transfers', 'Transfers', 'Revenue without binding arrangements', 'credit'],
                ['point', 'Revenue from compliance obligations satisfied at a point in time', 'Revenue', 'credit'],
                ['over_time', 'Revenue from compliance obligations satisfied over time', 'Revenue', 'credit'],
                ['other', 'Other revenue', 'Revenue', 'credit'],
                ['employee', 'Employee costs', 'Expenses by nature', 'debit'],
                ['goods', 'Use of goods and services', 'Expenses by nature', 'debit'],
                ['depreciation', 'Depreciation and amortisation', 'Expenses by nature', 'debit'],
                ['finance', 'Finance costs', 'Expenses by nature', 'debit'],
            ]],
            'equity' => ['Changes in net assets / equity', 'Appendix 3 / PDF pp.135-136; §4.5 PDF p.27', [
                ['surplus', 'Accumulated surplus / deficit', 'Net assets / equity', 'credit'],
                ['reserves', 'Other reserves', 'Net assets / equity', 'credit'],
                ['capital', 'Contributed capital', 'Net assets / equity', 'credit'],
            ]],
            'budget' => ['Separate comparison of budget and actual amounts', 'Appendix 5 / PDF pp.139-142; §§4.6-4.7 PDF p.27', []],
        ];
        DB::connection('tenant')->transaction(function () use ($templates, $source, $user) {
            foreach ($templates as $code => [$name, $ref, $lines]) {
                $definition = ReportDefinition::firstOrCreate(['code' => $code, 'version' => 1], [
                    'name' => $name, 'statement_type' => $code, 'status' => 'draft', 'source_document_id' => $source->id,
                    'source_ref' => $ref, 'currency_code' => app(TenantManager::class)->requireCouncil()->currency_code,
                    'units' => 'units', 'created_by' => $user->id,
                    'notes' => 'Configurable source-backed headings. Additional schedules and disclosures require council assessment.',
                ]);
                foreach ($lines as $order => [$lineCode, $label, $section, $sign]) {
                    $definition->lines()->firstOrCreate(['line_code' => $lineCode], ['label' => $label, 'section' => $section, 'sign' => $sign, 'sort_order' => $order]);
                }
            }
        });
    }

    public function nextVersion(ReportDefinition $definition, User $user): ReportDefinition
    {
        $this->authorize($user, 'accounting.report_mapping.manage');

        return DB::connection('tenant')->transaction(function () use ($definition, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('report-mapping'))");
            $copy = $definition->replicate(['approved_by', 'approved_at']);
            $copy->version = (int) ReportDefinition::where('code', $definition->code)->max('version') + 1;
            $copy->status = 'draft';
            $copy->created_by = $user->id;
            $copy->save();
            foreach ($definition->lines as $line) {
                $newLine = $line->replicate();
                $newLine->report_definition_id = $copy->id;
                $newLine->save();
            }

            return $copy;
        });
    }

    public function map(ReportLine $line, array $data, User $user): ReportLineMapping
    {
        $this->authorize($user, 'accounting.report_mapping.manage');

        return DB::connection('tenant')->transaction(function () use ($line, $data, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('report-mapping'))");
            $line = $line->fresh(['definition']);
            if ($line->definition->status !== 'draft') {
                throw new \DomainException('Create a new report version to change approved mappings.');
            }
            $account = GlAccount::whereHas('chartVersion', fn ($q) => $q->where('status', 'active'))->where('code', $data['gl_account_code'])->first();
            if (! $account || ! $account->isPostable() || ! $account->isActiveOn($data['effective_from'])) {
                throw new \DomainException('Map an active posting account.');
            }
            $classes = match ($line->definition->statement_type) {
                'position' => ['asset', 'liability', 'net_asset'], 'performance' => ['revenue', 'expense'], 'equity' => ['net_asset'], default => [],
            };
            if (! in_array($account->account_class, $classes, true)) {
                throw new \DomainException('The account class is outside this statement scope.');
            }
            if (! in_array($line->sign, ['debit', 'credit'], true) || (($data['effective_to'] ?? null) && $data['effective_to'] < $data['effective_from'])) {
                throw new \DomainException('Invalid mapping sign or effective period.');
            }
            app(PostingService::class)->validateDimensions($data['dimension_filters'] ?? [], $data['effective_from']);

            return $line->mappings()->create([...$data, 'status' => 'draft', 'created_by' => $user->id]);
        });
    }

    public function approve(ReportDefinition $definition, User $user): ReportDefinition
    {
        $this->authorize($user, 'accounting.report_mapping.approve');

        return DB::connection('tenant')->transaction(function () use ($definition, $user) {
            DB::connection('tenant')->select("SELECT pg_advisory_xact_lock(hashtext('report-mapping'))");
            $definition = $definition->fresh(['lines.mappings']);
            if ($definition->status !== 'draft' || $definition->created_by === $user->id || $definition->lines->flatMap->mappings->contains('created_by', $user->id)) {
                throw new \DomainException('A draft report requires approval by a different officer.');
            }
            if (! $definition->effective_from || ! $definition->currency_code || ! in_array($definition->units, ['units', 'thousands'], true)) {
                throw new \DomainException('Approve a reporting currency, display units and effective date first.');
            }
            $mappings = $definition->lines->flatMap->mappings;
            foreach ($mappings as $a) {
                foreach ($mappings as $b) {
                    if ($a->id === $b->id || $a->gl_account_code !== $b->gl_account_code) {
                        continue;
                    }
                    $dateOverlap = $a->effective_from <= ($b->effective_to ?? CarbonImmutable::parse('9999-12-31')) && $b->effective_from <= ($a->effective_to ?? CarbonImmutable::parse('9999-12-31'));
                    $filtersOverlap = collect($a->dimension_filters ?? [])->every(fn ($v, $k) => ! isset(($b->dimension_filters ?? [])[$k]) || $b->dimension_filters[$k] === $v);
                    if ($dateOverlap && $filtersOverlap) {
                        throw new \DomainException('Ambiguous overlapping account mappings.');
                    }
                }
            }
            foreach ($definition->lines as $line) {
                if (! in_array($line->sign, ['debit', 'credit'], true)) {
                    throw new \DomainException('Report sign must be debit or credit.');
                }
                $line->mappings()->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            }
            $definition->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            app(AuditLogger::class)->record(action: 'accounting.report_mapping.approved', subject: $definition, after: ['version' => $definition->version], module: 'accounting');

            return $definition;
        });
    }

    public function statement(ReportDefinition $definition, string $from, string $to, ?string $compareFrom = null, ?string $compareTo = null): array
    {
        if ($from > $to || (($compareFrom === null) !== ($compareTo === null)) || ($compareFrom && ($compareFrom > $compareTo || $compareTo >= $from))) {
            throw new \DomainException('Use ordered, non-overlapping current and comparative periods.');
        }
        if ($compareFrom && CarbonImmutable::parse($from)->diffInDays($to) !== CarbonImmutable::parse($compareFrom)->diffInDays($compareTo)) {
            throw new \DomainException('Comparative periods must have equal duration.');
        }
        $definition->load('lines.mappings');
        $current = $this->amounts($definition, $from, $to);
        $comparative = $compareFrom ? $this->amounts($definition, $compareFrom, $compareTo) : null;
        $blockers = ['Official posting chart/schedule, current source applicability, complete disclosures and council approvals must be assessed. No unreserved IPSAS compliance statement is issued.'];
        if ($definition->status !== 'approved') {
            $blockers[] = 'Report mapping version is not council-approved.';
        }
        if (! $definition->effective_from || $definition->effective_from->toDateString() > $to || ($definition->effective_to && $definition->effective_to->toDateString() < $to)) {
            $blockers[] = 'Report definition is outside its approved effective period.';
        }
        if (isset($current['functional_expense_notes']['UNMAPPED FUNCTION'])) {
            $blockers[] = 'Functional expense disclosure is incomplete: expense postings lack function/programme dimensions.';
        }
        if (! $compareFrom) {
            $blockers[] = 'Comparative period not supplied.';
        }
        if ($definition->statement_type === 'budget') {
            $blockers[] = 'Budget register and comparable-basis reconciliation are not implemented; Appendix 5 amounts are unavailable.';
        }
        if (ChartVersion::where('status', 'active')->where('production_blocker', true)->exists()) {
            $blockers[] = 'DEMONSTRATION posting chart; official Ministry schedule missing.';
        }

        return ['definition' => $definition, 'current' => $current, 'comparative' => $comparative, 'from' => $from, 'to' => $to,
            'compare_from' => $compareFrom, 'compare_to' => $compareTo, 'blockers' => $blockers, 'compliance_statement' => null];
    }

    private function amounts(ReportDefinition $definition, string $from, string $to): array
    {
        $classes = match ($definition->statement_type) {
            'position' => ['asset', 'liability', 'net_asset'], 'performance' => ['revenue', 'expense'], 'equity' => ['net_asset'], default => [],
        };
        $lines = JournalLine::with('account', 'entry')->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $to))
            ->whereHas('account', fn ($q) => $q->whereIn('account_class', $classes))->get();
        $amounts = $definition->lines->mapWithKeys(fn ($l) => [$l->line_code => ['label' => $l->label, 'sign' => $l->sign, 'opening_minor' => 0, 'movement_minor' => 0, 'closing_minor' => 0]])->all();
        $unmapped = [];
        $ambiguous = [];
        $currencyErrors = [];
        $raw = 0;
        $mapped = 0;
        $functions = [];
        foreach ($lines as $posted) {
            $date = $posted->entry->entry_date->toDateString();
            if ($definition->statement_type === 'performance' && $date < $from) {
                continue;
            }
            $net = $posted->debit_minor - $posted->credit_minor;
            if ($posted->entry->currency_code !== $definition->currency_code) {
                $currencyErrors[] = $posted->entry->entry_number;

                continue;
            }
            $raw += $net;
            $matches = [];
            foreach ($definition->lines as $line) {
                foreach ($line->mappings as $mapping) {
                    if ($mapping->status !== 'approved' || $mapping->gl_account_code !== $posted->account->code || $mapping->effective_from->toDateString() > $date || ($mapping->effective_to && $mapping->effective_to->toDateString() < $date)) {
                        continue;
                    }
                    if (collect($mapping->dimension_filters ?? [])->every(fn ($v, $k) => ($posted->dimensions[$k] ?? null) === $v)) {
                        $matches[] = $line;
                    }
                }
            }
            if (count($matches) !== 1) {
                if ($matches === []) {
                    $unmapped[] = $posted->account->code;
                } else {
                    $ambiguous[] = $posted->account->code;
                }

                continue;
            }
            $mapped += $net;
            $line = $matches[0];
            $amount = $line->sign === 'credit' ? -$net : $net;
            $amounts[$line->line_code][$date < $from ? 'opening_minor' : 'movement_minor'] += $amount;
            $amounts[$line->line_code]['closing_minor'] += $amount;
            if ($posted->account->account_class === 'expense') {
                $function = $posted->dimensions['function'] ?? $posted->dimensions['programme'] ?? 'UNMAPPED FUNCTION';
                $functions[$function] = ($functions[$function] ?? 0) + $net;
            }
        }
        $result = (int) JournalLine::whereHas('entry', fn ($q) => $q->where('status', 'posted')->where('currency_code', $definition->currency_code)->whereDate('entry_date', '<=', $to))
            ->whereHas('account', fn ($q) => $q->whereIn('account_class', ['revenue', 'expense']))->selectRaw('COALESCE(SUM(credit_minor - debit_minor),0) AS result')->value('result');
        if ($definition->statement_type === 'equity') {
            $openingResult = (int) JournalLine::whereHas('entry', fn ($q) => $q->where('status', 'posted')->where('currency_code', $definition->currency_code)->whereDate('entry_date', '<', $from))
                ->whereHas('account', fn ($q) => $q->whereIn('account_class', ['revenue', 'expense']))->selectRaw('COALESCE(SUM(credit_minor - debit_minor),0) AS result')->value('result');
            $amounts['unclosed_result'] = ['label' => 'Unclosed surplus / deficit from posted performance accounts', 'sign' => 'credit',
                'opening_minor' => $openingResult, 'movement_minor' => $result - $openingResult, 'closing_minor' => $result];
        }

        return ['lines' => $amounts, 'unmapped_accounts' => array_values(array_unique($unmapped)), 'ambiguous_accounts' => array_values(array_unique($ambiguous)),
            'currency_errors' => array_values(array_unique($currencyErrors)), 'trial_balance_net_minor' => $raw, 'mapped_net_minor' => $mapped,
            'reconciles' => $definition->statement_type !== 'budget' && $raw === $mapped && $unmapped === [] && $ambiguous === [] && $currencyErrors === [],
            'unclosed_result_minor' => $definition->statement_type === 'position' ? $result : null,
            'functional_expense_notes' => $functions, 'budget_amounts_available' => false];
    }

    private function authorize(User $user, string $permission): void
    {
        if (! app(PermissionService::class)->allows($user, $permission)) {
            throw new AuthorizationException;
        }
    }
}
