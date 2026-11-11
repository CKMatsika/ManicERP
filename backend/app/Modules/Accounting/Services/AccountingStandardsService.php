<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingStandard;

/**
 * Standards and policy register. Entries are seeded as UNVERIFIED planning
 * assumptions. They must not be read as evidence of IPSAS compliance: each
 * entry requires a verified source document, an applicability decision and an
 * approved policy choice before affected production reporting is enabled.
 */
class AccountingStandardsService
{
    public function seedDefaults(): int
    {
        $count = 0;

        foreach ($this->definitions() as $definition) {
            AccountingStandard::updateOrCreate(
                ['code' => $definition['code'], 'edition' => $definition['edition']],
                $definition + [
                    'verification_status' => 'unverified',
                    'applicability' => 'assessment_required',
                    'jurisdiction' => 'international',
                ],
            );
            $count++;
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'code' => 'IPSAS 45',
                'title' => 'Property, Plant and Equipment',
                'edition' => '2023 issue (planning assumption)',
                'international_effective_from' => '2025-01-01',
                'transition_method' => 'retrospective_or_modified_retrospective',
                'affected_workflows' => ['assets', 'depreciation', 'disclosures'],
                'notes' => 'Prompt-provided effective date treated as a planning assumption. Source document not verified in this environment.',
            ],
            [
                'code' => 'IPSAS 46',
                'title' => 'Measurement',
                'edition' => '2023 issue (planning assumption)',
                'international_effective_from' => '2025-01-01',
                'transition_method' => null,
                'affected_workflows' => ['valuation', 'fair_value', 'disclosures'],
                'notes' => 'Measurement policies and current value evidence are not implemented. Valuation approval is council-controlled.',
            ],
            [
                'code' => 'IPSAS 47',
                'title' => 'Revenue',
                'edition' => '2023 issue (planning assumption)',
                'international_effective_from' => '2026-01-01',
                'transition_method' => 'retrospective',
                'affected_workflows' => ['billing', 'revenue_recognition', 'receivables', 'contracts'],
                'notes' => 'Revenue recognition currently books billing to receivable and revenue. Binding-arrangement and performance-obligation analysis is not implemented.',
            ],
            [
                'code' => 'IPSAS 48',
                'title' => 'Transfer Expenses',
                'edition' => '2023 issue (planning assumption)',
                'international_effective_from' => '2026-01-01',
                'transition_method' => 'retrospective',
                'affected_workflows' => ['grants', 'transfers', 'commitments'],
                'notes' => 'Transfer-expense obligations are not modelled separately yet; grants and commitments are planned modules.',
            ],
            [
                'code' => 'IPSAS 43',
                'title' => 'Leases',
                'edition' => '2022 issue (planning assumption)',
                'international_effective_from' => null,
                'transition_method' => 'modified_retrospective',
                'affected_workflows' => ['leases', 'right_of_use_assets', 'payables'],
                'notes' => 'Leases are not implemented. No lease accounting policy has been approved.',
            ],
            [
                'code' => 'IPSAS 44',
                'title' => 'Non-current Assets Held for Sale and Discontinued Operations',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['assets', 'disposals', 'disclosures'],
                'notes' => 'Listed in ZFRM 1.3.5 as early adopted. No asset disposal workflow is implemented and the standard remains unverified.',
            ],
            [
                'code' => 'IPSAS 41',
                'title' => 'Financial Instruments',
                'edition' => '2018 issue (planning assumption)',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['receivables', 'impairment', 'borrowings', 'disclosures'],
                'notes' => 'Expected-credit-loss assumptions and approval evidence are not implemented. Impairment policy is a production blocker.',
            ],
            [
                'code' => 'IPSAS 4',
                'title' => 'The Effects of Changes in Foreign Exchange Rates',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['fx', 'revaluation', 'settlement_differences'],
                'notes' => 'Effective-dated rates are modelled; FX revaluation and settlement journals are not implemented.',
            ],
            [
                'code' => 'IPSAS 10',
                'title' => 'Financial Reporting in Hyperinflationary Economies',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['hyperinflation', 'indexation'],
                'notes' => 'Only controlled calculation through approved indices may be added. No index source has been verified.',
            ],
            [
                'code' => 'IPSAS 24',
                'title' => 'Presentation of Budget Information in Financial Statements',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['budgets', 'budget_vs_actual', 'reconciliation'],
                'notes' => 'Budget, commitment and budget-versus-actual reporting is not implemented.',
            ],
            [
                'code' => 'IPSAS 33',
                'title' => 'First-time Adoption of Accrual Basis IPSAS',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => 'transitional_exemptions',
                'affected_workflows' => ['opening_balances', 'transition_workpapers', 'disclosures'],
                'notes' => 'Opening balance and transition workpapers are not implemented.',
            ],
            [
                'code' => 'IPSAS 35',
                'title' => 'Consolidated Financial Statements',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['consolidation', 'eliminations'],
                'notes' => 'Consolidation and eliminations are not implemented.',
            ],
            [
                'code' => 'IPSAS 22',
                'title' => 'Disclosure of Financial Information about the General Government Sector',
                'edition' => 'planning assumption',
                'international_effective_from' => null,
                'transition_method' => null,
                'affected_workflows' => ['disclosures'],
                'notes' => 'Applicability to an individual council must be assessed, not assumed.',
            ],
        ];
    }
}
