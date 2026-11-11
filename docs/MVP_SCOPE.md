# Municipal MVP scope

This document defines the bounded web-and-mobile municipal MVP. It is not a
claim that ManicERP is a complete ERP or production-approved financial system.
Synthetic acceptance data, demonstration schedules and sandbox integrations
must remain clearly labelled. Official charts, tariffs, accounting policies,
procurement rules, payment integrations and IPSAS disclosures stay blocked
until the relevant authority and evidence are approved.

## Acceptance contract

The web MVP is accepted only after all three rendered-browser journeys pass on
desktop and narrow mobile widths, and their independent financial control
totals reconcile:

1. Opening balances → property/service → reading → billing → citizen enquiry →
   sandbox payment → receipt/allocation → bank reconciliation → reports.
2. Licence application → evidence → staged charges → payment → review and
   information response → inspection → independent decision → certificate →
   verification → renewal.
3. Approved budget → requisition → sourcing/award → purchase order → partial
   receipt → matched invoice → payment approval → recorded sandbox settlement
   → bank reconciliation → supplier/AP/GL reconciliation.

An HTTP/API test is automated evidence. It does not count as rendered-browser
acceptance. Mobile build/device evidence and external integration evidence are
reported separately.

## Completion matrix

| Capability | Implemented | Automated evidence | Browser verified | Mobile verified | External / council approved |
| --- | --- | --- | --- | --- | --- |
| Existing AR, billing, receipts, refunds and bank reconciliation | Broad slice | 146 tests / 751 assertions pass; see report | No full journey yet | No | Sandbox only; council schedules unapproved |
| Opening balances and mapped reports | Implemented slice | Present | No | No | Official chart and complete reporting unavailable |
| Licensing and renewals | Implemented demonstration slice | Present, including concurrency | Registration screen only; signed-link navigation blocked, lifecycle pending | Source only | Fees/authority unapproved; external notices unavailable |
| Citizen self-registration | Implemented in web; account linking remains separate | Signed verification, expiry, throttle, duplicate identity and no-link tests | Form rendered and submitted; email remains pending | Pending | Local log mail only; external delivery unavailable |
| Procurement, budgets and AP | Partial runnable service/workbench slice | Supplier/budget/sourcing/AP tests; synthetic AP/GL and bank differences zero | No browser run | Not applicable | Demonstration thresholds/accounts and sandbox only; no compliance claim |
| Supplier-invoice AI | Not implemented | Fixture tests to add | Pending | Not applicable | Live provider disabled/unverified |
| Flutter customer parity | Partial source | Not run: Flutter/Dart/ADB absent | Pending | No build verified | No store/signing claim |

## Revenue MVP boundary

The existing revenue module already includes parties, properties, accounts,
effective-dated tariffs, metered readings, billing review/posting, payments,
allocations, refunds, statements, opening customer balances and bank
reconciliation. The next acceptance work should verify these paths together,
identify missing debtor ageing/collection reports and correct only proven gaps.
Currency values remain integer minor units; customer subledgers and GL controls
must reconcile. Real payment movement stays unavailable.

## Procurement, commitments and AP boundary

Implement suppliers on party records, controlled bank-detail changes, approved
budgets, commitments, requisitions, sourcing evidence, awards, purchase orders,
partial receiving, invoices, three-way matching, credit notes, payment
proposals and supplier/AP reports. The first runnable staff/service slice is
available at `/procurement`; remaining gaps are tracked in
`MVP_CHECKLIST.md`. Do not represent commitments as posted expenditure, nor
purchase approvals as settlement. Until stores/assets modules exist, received
goods are expensed; stock and asset registers are not updated.

Approval thresholds and sourcing rules are demonstration configuration only.
Do not assert Zimbabwe procurement compliance without current primary authority
and council approval. Bank details, quotations and invoices remain private.

## AI boundary

Use installed Laravel AI services and permission/budget/source controls for
invoice field drafts, PO/receipt match suggestions, completeness checks,
source-cited assistance and draft correspondence/commentary. Keep source hashes,
provider/model metadata and field provenance. Require human review before any
award, supplier, invoice, payment or journal change. When provider credentials
are unavailable, run deterministic fixtures and report live generation as
unverified.

## Persistent execution checklist

See [MVP_CHECKLIST.md](MVP_CHECKLIST.md) for the ordered implementation ledger,
verification evidence and exact next task. Update that file at each milestone.
