# Roadmap

Status legend: **Done** = implemented, tested in this environment; **Partial** =
working slice with named gaps; **Scaffolded** = structure or contract only;
**Not started** = no claim.

## Phase 0 — Environment, architecture, backlog

| Item | Status | Evidence |
| --- | --- | --- |
| Environment inspection and verified versions | Done | `docs/IMPLEMENTATION_STATUS.md` §1 |
| Architecture and tenancy decisions | Done | `docs/ARCHITECTURE.md` |
| Module inventory, dependencies and availability | Done | `docs/MODULES.md`, `config/modules.php` |
| Domain model for parties, property, accounts, meter, accounting | Done | tenant migrations, models |
| Accounting source/standards register | Done (sources unverified) | `accounting_standards`, `docs/ACCOUNTING_SOURCES.md` |
| Threat model | Done | `docs/THREAT_MODEL.md` |
| Ranked backlog | Done | bottom of `docs/IMPLEMENTATION_STATUS.md` |

## Phase 1 — Runnable foundation

| Item | Status | Evidence |
| --- | --- | --- |
| Laravel 13 + PostgreSQL + Inertia/React + Tailwind | Done | app boots, build produced, HTTP smoke test |
| Council provisioning and database-per-council | Done | `CouncilProvisioner`, demo command, isolation tests |
| Council isolation (records, API tokens, permissions) | Done | `CouncilIsolationTest` |
| Authentication, sessions, API tokens, logout/revocation | Done | `AuthenticationTest`, Sanctum tokens |
| Privileged-staff MFA (TOTP + recovery codes) | Done | `TotpService`, `MfaController`, auth tests |
| Roles, permission catalogue and server-side checks | Done | `PermissionService`, `AuthenticationTest` |
| Module registry, dependencies, core lock | Done | `ModuleRegistryTest` |
| Audit foundation, hash chain and verification | Done | `AuditChainTest`, `audit:verify` |
| Private documents and authorised downloads | Done | `DocumentTest` |
| Demo council, roles, users and labelled data | Done | `manicerp:demo` |
| CI workflow | Done (not executed here) | `.github/workflows/ci.yml` |
| Flutter scaffold and API contract | Partial | `mobile/`, `docs/API.md`; no SDK, no build |
| AI SDK/access/audit foundation | Partial | SDK installed; retrieval, gating, budget, usage records tested; provider call unverified |

## Phase 2 — Accounting

| Item | Status | Evidence |
| --- | --- | --- |
| Chart/head import, validation, approval, leading zeros | Done | `ChartImportTest` |
| Dimensions | Done (core types; posting validation) | dimension seeds/tests |
| Currencies, money, decimal rounding | Done | `MoneyTest`; FX table exists |
| Posting engine, balanced/idempotent posting | Done | `AccountingPostingTest` |
| Periods and locks | Done (lock enforced; close-year awaits) | posting tests |
| Journals, reversal linkage, immutability | Done | posting tests |
| Posting-rule matrix and approval state | Done (four demo rules) | `posting_rules` |
| Trial balance, ledger, position, performance | Done | reporting service + pages |
| Opening balances/transition workpapers | Partial | GL/customer staging, validation, approval, posting and reconciliation; see `OpeningBalanceTest` |
| FX revaluation, settlement differences | Not started | — |
| Budgets, commitments, budget-vs-actual | Not started | — |
| Full IPSAS statements, notes, comparatives, consolidation | Not started | clearly not claimed |

## Phase 3 — Revenue cycle

| Item | Status | Evidence |
| --- | --- | --- |
| Parties, roles, contacts, properties, valuations, ownership | Done | models/services; isolation and billing tests |
| Customer accounts, liabilities, connections, meters, readings | Done | `BillingTest`, `MeterReadingService` |
| Effective-dated tariffs, bands, proration, explanation | Done | `BillingTest` |
| Billing preview/approval/posting, snapshots, duplicate prevention | Done | `BillingTest` |
| Cashier sessions, receipts, allocation, overpayment | Done | `PaymentTest`, cashier tests via demo |
| Sandbox gateway, signed/replay-protected webhooks, intent states | Done | `PaymentTest` |
| Bank statement import, matching suggestions, reconciliation | Done | `BankReconciliationTest` |
| Refund approval workflow | Sandbox implemented/tested | requests, thresholds, independent approval, partial refunds, retries, allocation reversal, GL and UI; no real gateway |
| Payment plans, arrears notices, dispute holds | Not started | — |
| Real Zimbabwean gateways/banks/mobile money/STS | Not started (no API access) | sandbox only |

## Phase 4 — Citizen surfaces

| Item | Status | Evidence |
| --- | --- | --- |
| Portal sign-in, verified linking with OTP, revocation | Done | `PortalAccessTest` |
| Public citizen registration with signed email verification | Partial | `CitizenRegistrationTest`; browser verification pending URL policy clearance |
| Bills, balances, payments, receipt list, PDF statement | Done | portal pages; statement PDF |
| Pay flow with verified sandbox settlement | Done | portal pay pages, `PaymentTest` |
| Complaints/service requests | Done (create/list; no staff resolution UI) | portal requests, `service_requests` |
| Flutter app source (auth, accounts, bills, pay intent, link, requests, licensing, local unsent drafts) | Partial | `mobile/`; Flutter SDK unavailable in this environment, so unbuilt |
| Push notifications, deep links, offline reading capture | Not started | — |
| Licensing and renewals (config, application attempts, private evidence, inspection, decisions, shared fees/refunds, certificates, QR verification) | Partial: browser/API slice implemented and backend tested; notification delivery and production council setup remain | `LicensingWorkflowTest`, `/licensing`, `/portal/licensing`, `/api/v1/licensing`; Flutter source added but unbuilt |

## Phase 5 — Procurement, AP and budgets

| Item | Status | Evidence |
| --- | --- | --- |
| Supplier party reuse, encrypted bank changes and independent approval | Partial | `ProcurementBudgetTest`; private attachments remain |
| Versioned budget lines, amendments/transfers and locked reservations | Partial | `SupplierBudgetService`; no direct multi-process race test yet |
| Requisition, quotation, evaluation, award and purchase order | Partial | `SourcingService`; demo-only thresholds and private quotes remain |
| Partial receipt and three-way invoice matching | Partial | `ProcurementBudgetTest`; no post-receipt amendment/closure |
| AP invoice/credit journals, payment proposals and sandbox settlement | Partial | `AccountsPayableService`; real bank adapter unavailable |
| Supplier ledger, ageing, budget and AP/GL reconciliation | Partial | `/procurement`; synthetic acceptance scenario has zero difference |

Stores/assets are not updated by received goods; goods and services are
expensed using the demonstration expense mapping. No procurement-law compliance
claim is made.

## Phase 6 — Assets, leases, engineering, land/GIS, fleet, HR/payroll

Not started. Modules are unavailable. No PostGIS is installed; GIS is limited to
decimal coordinates with provenance.

## Phase 7 — Full reporting, integrations, pilot migration, readiness

Financial-readiness slice implemented/tested: source requirements, tenant jobs,
opening GL/customer staging and reconciliation, sandbox refunds, versioned
ZFRM mappings and revenue policies. Complete disclosures, downstream-correction
wizard, parallel run, backup restore testing and departmental sign-off remain.

## Cross-cutting AI sequence

1. SDK, permissions, budget, audit foundation — **Partial**.
2. Source-cited policy assistant — **Partial** (retrieval and citations work;
   provider generation untested without a key).
3. Customer assistant over authorised tools — **Not started**.
4. Document extraction with human verification — **Not started**.
5. Anomaly/duplicate suggestions, bank matching, complaint classification —
   **Not started** (deterministic bank suggestions exist, not AI).
6. Draft management commentary from report outputs — **Not started**.

## Recommended next tasks (ranked)

1. Complete desktop/narrow-browser licensing acceptance, including normal MFA,
   verified email and account linking, the full certificate/renewal and rejection
   flows, plus independent financial reconciliation. The signed verification
   navigation is currently blocked by the local browser URL security policy.
2. Complete financial follow-ups: official source/chart review and council
   approvals, downstream migration corrections, obligation-fulfilment releases,
   deposit-release policy, full notes, budgets and year-end close.
3. Expand Procurement with private attachments, enforceable demonstration
   sourcing thresholds, post-receipt amendments/closure and multi-process
   budget/payment contention tests.
4. Verify real refund/payment provider integration before moving real funds.
5. Add party/property legacy staging, payment plans, arrears and dispute holds.
6. Add stores/assets with receipts, valuation and depreciation; then land and
   housing with GIS provenance.
7. Harden operations: antivirus, private object storage, backup/restore drills,
   monitoring, mail, Flutter builds/signing, push and deep links.
