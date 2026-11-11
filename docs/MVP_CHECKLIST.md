# Municipal MVP implementation checklist

Persistent work ledger. Mark implementation and acceptance evidence separately;
do not infer browser, mobile, provider or council approval from automated tests.

## A. Acceptance environment and registration

- [x] Isolated central DB `manicerp_acceptance_central` and tenant
      `manicerp_acceptance_demo` use explicit local/database guards.
- [x] Synthetic staff roles and ACL-restricted local credential file; no
      credentials are printed or committed.
- [x] Acceptance setup steps are resumable and idempotent; partial records were
      retained, and a second setup/revenue run created no duplicate bills,
      receipts, journals or applications.
- [x] Synthetic customer, account/property/service records, approved tariffs,
      demonstration bank and demonstration licensing configuration verified.
- [x] Citizen registration, signed email verification and local mail-log
      transport exist; account linking remains separate.
- [ ] Enrol privileged users through standard MFA setup/challenge screens.
- [ ] Complete desktop and narrow browser licensing/revenue journeys. In-app
      browser URL security policy denied the original signed verification link;
      follow [MANUAL_ACCEPTANCE_SCRIPT.md](MANUAL_ACCEPTANCE_SCRIPT.md).

## B. Revenue MVP acceptance

- [x] Resumable command uses opening balance, billing, verified sandbox
      settlement, receipt/allocation, bank reconciliation and reporting services.
- [x] Independent synthetic AR/customer-credit, cash/bank, revenue, opening
      balance, liability, balanced-journal, trial-balance and bank-variance
      totals are published in `ACCEPTANCE_REPORT.md`.
- [ ] Verify citizen statement, refund, account access and the full journey in
      rendered browser at desktop and narrow widths.
- [x] Keep real payment and bank adapters unavailable.

## C. Procurement, commitments and AP

- [x] Supplier onboarding, encryption of bank account numbers, separate
      supplier/bank-change approvals and audit records.
- [x] Council-scoped private supplier, quotation, PO, receiving and invoice
      documents; validation, safe keys/names, version provenance, replacement
      reasons, confidential quote access, retention audit and visible
      `not_scanned` state. No file deletion or malware-scanning service is
      claimed.
- [x] Approved budgets, reasoned amendments/transfers, row-locked reservations,
      independent over-budget approval and audited commitment-to-actual
      conversion/award adjustment.
- [x] Requisition submission/approval, supplier quotations, evaluation records,
      award approval and PO issuance. Demonstration thresholds only; minimum
      quote-count rules remain open.
- [x] Partial receiving, outstanding quantity checks and controlled
      post-receipt amendments, independent version approval, partial
      cancellation, closure and controlled reopening. Supplier return or
      correction linkage is required before reducing below moved quantities.
- [x] Duplicate-checked supplier invoices, strict receipt/value match, reasoned
      match exceptions, non-PO service API, credit notes and balanced AP journals.
- [x] Payment proposal, independent approval, partial sandbox settlement,
      idempotent retries and supplier payment bank statement matching. Real
      banking remains unavailable.
- [x] Supplier ledger, creditor ageing, outstanding orders, matching exceptions,
      proposal status, budget availability and AP/GL reconciliation report.
- [x] Executable budget → requisition → quote/award → PO → partial receipt →
      partial receipt → amendment → partial cancellation → closure scenario
      with partial settlement and unpaid residual AP.
- [x] Independent PostgreSQL-process contention for budget reservations,
      payment approvals/settlements/retries, order amendment and cancellation
      versus invoice posting; assertions cover persisted balances and journals.
- [x] Enforce council procurement quotation-count thresholds in
      `SourcingService::prepareAward`, with an independent override and audit
      record.
- [x] Add supplier-return/correction workflow (`prepareSupplierReturn`,
      `approveSupplierReturn`, `rejectSupplierReturn` plus aliases) with a
      dedicated migration, received/invoiced quantity reduction, credit journal
      for invoiced portions, budget actual reversal and audit. Reversal/
      settlement-history UI and antivirus remain open.

## D. AI and customer channels

- [ ] Add deterministic invoice extraction, suggested matching and completeness
      assistance with provenance and human review only.
- [x] Preserve existing policy citations, review-only licensing AI and no
      authoritative-record writes. Live provider remains disabled/unverified.
- [x] Add scheduled term-elapsed deferred revenue release in
      `LicenceMaintenanceJob` via `LicenceChargeService::releaseEligibleDeferred`,
      using the snapshotted policy trigger and evidence. Deposit-release
      policy/workflow remains a separately approved blocker.
- [x] Add controlled downstream correction workflow (`CorrectionService` /
      `DownstreamCorrectionService`) with prepare/approve/reject, balanced
      journal posting, period and separation-of-duties guards, idempotency and
      audit. Customer-subledger linkage remains a documented follow-up.
- [x] Add foundational HR/payroll module: employee master, pay elements,
      payroll runs and lines, submission/approval with balanced journal posting.
      Leave, statutory deductions and payslip PDF remain follow-ups.
- [x] Add foundational fleet module: vehicle register, fuel logs and maintenance
      cost posting with journal. Disposal, depreciation and telematics remain
      follow-ups.
- [x] Add foundational land/GIS module: parcel records with decimal
      coordinates and provenance. No PostGIS geometry; remains a documented
      substitute.
- [x] Add foundational disclosure helpers: changes in net assets and heuristic
      cash-flow summary derived from posted GL data. Full IPSAS disclosures,
      notes, consolidation, valuations, FX, impairment and hyperinflation remain
      follow-ups.
- [ ] Complete citizen registration/linking, revenue and licensing journeys in
      a normal browser.
- [ ] Install Flutter/Dart/Android tooling and run mobile checks if available;
      do not claim iOS without macOS/Xcode.

## E. Repeatable demonstration and closeout

- [x] Add guarded resumable Windows acceptance commands for revenue and AP plus
      a manual browser script.
- [x] Publish independent AR/AP/GL/bank control totals with zero differences in
      the synthetic service scenarios.
- [ ] Complete browser acceptance and update actual observations.
- [ ] Review external blockers: official chart/fees/procurement policy, complete
      reporting, council approval, external delivery, live payment/bank adapters,
      appeal authority and production operations.

## This-session progress

- [x] Reproduced requested baseline: 159 tests / 813 assertions; final serial
      suite after this milestone: 162 tests / 915 assertions.
- [x] Added acceptance step runner/idempotency tests and Procurement supplier,
      budget, sourcing, AP, permission/isolation and reconciliation tests.
- [x] Completed revenue scenario: $6,825.79 billed including $500.00 opening
      invoice; $2,340.00 received/allocated; $4,485.79 bill debt and $50.00
      opening credit ($4,435.79 net debt); bank variance $0 at revenue stage;
      trial balance balanced.
- [x] Completed purchasing scenario: $2,500.00 approved budget, $1,100.00 PO,
      PO amended to $1,650.00, 0.300 cancelled from 1.500, 1.200 received and
      invoiced, $200.00 sandbox settlement, $1,120.00 unpaid AP; AP-to-GL and
      bank differences $0; final bank balance $1,500.00; actual $1,320.00,
      commitment $0 and budget available $1,180.00. Trial balance balanced at
      $10,785.79 per side. Acceptance scenario rerun without duplicate records.
- [ ] Browser registration verification/MFA/linking and UI journeys; Flutter
      tooling/device tests; live provider generation.

## Exact next task

Run [MANUAL_ACCEPTANCE_SCRIPT.md](MANUAL_ACCEPTANCE_SCRIPT.md) in the normal
local browser to complete signed email verification, standard MFA and citizen
linking, then execute revenue/licensing journeys at desktop and narrow widths.
Next code slice: enforce source-approved procurement quote thresholds and
complete supplier-return/correction controls. Browser/MFA acceptance, mobile
tooling, antivirus and live-provider verification remain separate blockers.
