# Accounting sources, standards register and production blockers

## 1. No compliance claim

ManicERP implements an accrual double-entry engine and a versioned standards
register. **This is not evidence of IPSAS compliance.** Software cannot by itself
guarantee compliance: the applicable pronouncements, Zimbabwean adoption
guidance, the council's own policies, opening balances, valuations, disclosures
and approvals must be obtained and approved by qualified people.

No official Ministry of Finance, Treasury or council chart of accounts was
available in this build environment. The active chart is a **DEMONSTRATION**
chart with illustrative codes and a visible production blocker.

## 2. Verification procedure

For every standard and policy the council must record:

1. the standard and edition, and its issue date;
2. the **international** effective date, kept distinct from Zimbabwe adoption
   guidance and from the council's approved policy;
3. jurisdiction applicability (international, Zimbabwe, council);
4. the transition method and any transitional exemptions;
5. policy choices available and the one approved;
6. the source document, issuing authority, version, effective period and
   checksum;
7. verification status, verifier, date and council approver;
8. affected workflows and reports.

The register is stored per council in `accounting_standards` and is visible at
**Accounting → Standards register**. Entries are seeded as
`verification_status = unverified`, `applicability = assessment_required`. No
entry is approved by the seeder.

## 3. Register as seeded

| Standard | International effective (planning assumption) | Status |
| --- | --- | --- |
| IPSAS 45 Property, Plant and Equipment | 2025-01-01 | Unverified; applicability and transition assessment required |
| IPSAS 46 Measurement | 2025-01-01 | Unverified; measurement policy and evidence required |
| IPSAS 47 Revenue | 2026-01-01 | Unverified; binding arrangements/performance obligations not modelled |
| IPSAS 48 Transfer Expenses | 2026-01-01 | Unverified; transfer-expense obligations not modelled |
| IPSAS 43 Leases | not asserted | Unverified; leases not implemented |
| IPSAS 41 Financial Instruments | not asserted | Unverified; ECL/impairment policy and evidence missing |
| IPSAS 4 Foreign Exchange | not asserted | Unverified; FX revaluation/settlement not implemented |
| IPSAS 10 Hyperinflation | not asserted | Unverified; no approved indices |
| IPSAS 24 Budget Information | not asserted | Unverified; budgets/commitments not implemented |
| IPSAS 33 First-time Adoption | not asserted | Unverified; transition workpapers not implemented |
| IPSAS 35 Consolidated Statements | not asserted | Unverified; consolidation not implemented |
| IPSAS 22 General Government Sector | not asserted | Unverified; applicability to a council must be assessed |

The 2025/2026 dates above came from the implementation brief as **planning
assumptions**. They were not independently confirmed against an authoritative
IPSASB pronouncement in this environment. The discovery links supplied in the
brief are:

- IPSASB current pronouncements: <https://www.ipsasb.org/standards-pronouncements>
- IPSAS 47: <https://www.ipsasb.org/publications/ipsas-47-revenue>
- IPSAS 48: <https://www.ipsasb.org/publications/ipsas-48-transfer-expenses>
- Zimbabwe Treasury publications: <https://zimtreasury.co.zw/policies-publications/>
- PAAB public-sector standards: <https://paab.org.zw/public-sector-accounting-standards/>

These are discovery starting points only. Their content must be re-verified at
implementation time, recorded with a source hash and approved. Do not treat a
search result, a draft, a central-government schedule or another country's
adoption date as the council's authority.

## 4. Missing authoritative documents (explicit blockers)

The following are **not** in the repository and block production financial
reporting until supplied and approved:

1. The current applicable Treasury/council chart of accounts or accounting-heads
   schedule, with issuing authority, scope, version and effective period.
2. Zimbabwe Treasury guidance on IPSAS adoption for local authorities, including
   any Financial Reporting Manual.
3. PAAB guidance or directives applicable to the council.
4. Council-approved accounting policies (recognition, measurement, depreciation,
   impairment, revenue, leases, grants, provisions).
5. Approved opening balances, valuations and transition workpapers.
6. An approved posting-rule matrix for every source transaction.
7. Any approved hyperinflation index and calculation policy, if applicable.

## 5. Chart of accounts status

- Import is implemented for CSV/XLSX with staging, validation, preview and
  approval. Codes are strings and preserve leading zeros. Duplicate codes,
  missing parents, cycles, invalid classes/types and invalid dates are rejected.
- Approval creates a new active chart version and supersedes the previous one;
  posted account identities are never rewritten.
- The demo chart (`database/seeders/data/demo_chart.csv`) is illustrative and is
  stored with `source_type = demo` and `production_blocker = true`.
- An official import can be recorded with `source_type = official`, authority,
  source document and effective dates, clearing the blocker only after a
  qualified user approves it. The software does not verify the issuing authority
  for you.
- GFSM/economic classification is modelled as a mapped dimension/code, not as an
  assumption that GFSM equals IPSAS. South African mSCOA and GRAP are **not**
  included; they belong to a later country package.

## 6. Posting-rule matrix status

`posting_rules` maps an event code to an approved debit/credit account pair,
required dimensions and approval state. The demo seeds four rules
(`billing.rates`, `billing.water`, `billing.refuse`, `billing.rental`); receipts
build explicit receivable/unapplied-funds lines. These are demonstration
policies. A council must approve its own matrix, including funds, votes,
programmes and economic items, before production use.

## 7. Revenue and measurement caveats

- Billing currently recognises a receivable and revenue when a bill is issued.
  The IPSAS 47 analysis of binding arrangements, performance obligations,
  advance receipts and deposits is not implemented; the register records this.
- Rates are calculated from an approved valuation using a demonstration
  "cents per $100" configuration. This is a configurable tariff basis, not an
  assertion of a statutory rate.
- No authoritative tariff, penalty, interest or rebate schedule is bundled.
- Water estimates use the average of the last three actual readings and are
  labelled `estimated` in the bill snapshot.
- No hyperinflation indexation, FX revaluation or financial-instrument
  impairment is implemented.

## 8. What the software does provide

- balanced integer-minor-unit double entry, immutable posted journals and linked
  reversals;
- open-period enforcement, unique source postings and idempotency keys;
- effective-dated chart versions and tariffs;
- trial balance, ledger, position and performance reports derived from posted
  lines, with an equation check;
- drill-down from reports to journal lines and from bills to their calculation
  snapshot and journal;
- an unverified standards register that makes missing approvals visible instead
  of hiding them.

## 9. Change control

Changing a standard, policy or posting rule requires a new version, effective
date, source evidence and approval; historic posted amounts and account
identities are preserved. The audit trail records approvals and changes. A code
rollback must never be used to reverse posted financial transactions; use the
linked reversal workflow instead.
