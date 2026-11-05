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
| Opening balances/transition workpapers | Not started | — |
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
| Refund approval workflow | Partial | table exists; no service/UI |
| Payment plans, arrears notices, dispute holds | Not started | — |
| Real Zimbabwean gateways/banks/mobile money/STS | Not started (no API access) | sandbox only |

## Phase 4 — Citizen surfaces

| Item | Status | Evidence |
| --- | --- | --- |
| Portal registration/sign-in, linking with OTP, revocation | Done | `PortalAccessTest` |
| Bills, balances, payments, receipt list, PDF statement | Done | portal pages; statement PDF |
| Pay flow with verified sandbox settlement | Done | portal pay pages, `PaymentTest` |
| Complaints/service requests | Done (create/list; no staff resolution UI) | portal requests, `service_requests` |
| Flutter app source (auth, accounts, bills, pay intent, link, requests, offline draft) | Partial | `mobile/`; unbuilt |
| Push notifications, deep links, offline reading capture | Not started | — |
| Licence applications/renewals, appointments, quotations | Not started | licensing module unavailable |

## Phase 5 — Procurement, AP, stores, budgets

Not started. The `procurement` module is unavailable; no tables, routes or
screens pretend otherwise.

## Phase 6 — Assets, leases, engineering, land/GIS, fleet, HR/payroll

Not started. Modules are unavailable. No PostGIS is installed; GIS is limited to
decimal coordinates with provenance.

## Phase 7 — Full reporting, integrations, pilot migration, readiness

Not started. Migration tooling, parallel run, backup restore testing and
departmental sign-off are prerequisites that do not yet exist.

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

1. Add queued tenant jobs with validated council context and tests (e.g. billing
   chunk continuation, statement PDFs, notification dispatch).
2. Add refund service, partial allocation reversal UI and payment-plan tables.
3. Implement opening balances and a controlled year-end close with evidence.
4. Build migration staging for parties/properties/accounts/opening balances with
   control totals and reconciliation to the trial balance.
5. Obtain and verify an official chart and Treasury/PAAB sources; complete the
   standards register approvals and posting-rule matrix.
6. Add licence module tables/workflows and portal licence pages.
7. Add procurement and stores with budget checks and three-way matching.
8. Add assets/depreciation and land/housing allocation with GIS provenance.
9. Harden operations: malware scanning, S3 private storage, backup/restore test,
   queue monitoring, health checks.
10. Build and test the Flutter app on real Android/iOS toolchains; add push and
    deep links.
