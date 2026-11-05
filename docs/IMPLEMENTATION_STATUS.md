# Implementation status

Last updated: 2026-11-05 (Africa/Harare).
This document is the honest resume point. It separates **implemented**, **tested
here**, **scaffolded** and **externally validated**. Nothing later than Phase 4
is claimed.

## 1. Environment actually inspected

| Component | Verified value | Note |
| --- | --- | --- |
| OS | Windows 10.0.19045 | PowerShell 7 |
| PHP | 8.4.25 ZTS (`C:\php84`) | extensions: pdo_pgsql, intl, bcmath, zip, gd, mbstring, openssl |
| Composer | 2.10.2 | run with PHP 8.4 |
| Laravel | 13.40.0 | new project in `backend/` |
| PostgreSQL | 16.10, port **5433** | trust auth for localhost; **no PostGIS** |
| Node / pnpm | 20.19.2 / 10.24.0 | hoisted node linker (Windows junctions were broken under pnpm isolated linking) |
| Redis | absent | database cache/queue/session |
| Docker | CLI 29.8.2, daemon **not running** | not used |
| Flutter / Dart | **not installed** | mobile source unbuilt |
| Mail | log/array in dev/test | no SMTP configured |
| AI provider key | **none** | AI disabled by default |

## 2. Delivered

### Foundation (verified)

- Laravel 13 monorepo app in `backend/`, Flutter source in `mobile/`, docs in
  `docs/`.
- Central database (councils, domains, modules, users, roles, memberships,
  sessions, cache, jobs, tokens, platform audit, AI usage) and a database per
  council with automated provisioning and tenant migrations.
- Host/session/membership council resolution, token-ability resolution for the
  API, server-side membership revalidation, safe identifiers and context restore
  via `TenantManager::runFor`.
- Login, logout, session regeneration, rate limiting, TOTP MFA with hashed
  recovery codes, Sanctum tokens with council abilities and expiry.
- Role permission catalogue generated from module manifests, `Gate::before`
  integration, per-route `can:` checks, member-type separation.
- Module registry with core lock, dependency enforcement, availability flags,
  server-side route gating and audit.
- Hash-chained tenant audit with append-only model guards, `audit:verify` and
  platform audit for provisioning.
- Private documents with allow-lists, hashes, classification, authorised
  download and nosniff/no-store headers.
- Demo council command (`manicerp:demo`) with labelled users, chart, parties,
  properties, meters, tariffs, readings, billing, receipts and a sandbox payment.
- Council provisioning, migration and standards-seeding commands.
- Security headers, JSON error handling for API, Inertia error boundary (basic).

### Accounting (verified slice)

- Integer-minor-unit money and fixed-point decimal helpers; no float arithmetic.
- Chart versions, staging batches/rows, validation (duplicates, cycles, orphans,
  class/type/dates), string codes with leading zeros, approval and supersession,
  demo-source production blocker.
- Standards register (12 entries) with unverified status, effective dates,
  transition method, affected workflows and approval fields.
- Dimensions and values; validated on postings.
- Financial years/periods and open-period enforcement.
- Double-entry posting with per-line validation, balanced totals, active chart
  and account dates, dimensions, idempotency keys, source uniqueness, locked
  numbering and audit.
- Immutable posted entries, linked reversals, reversal idempotency, delete
  prevention.
- Posting-rule table with approval state and four demo rules.
- Trial balance, ledger, position and performance derived from posted lines;
  equation check.
- UI: chart, journals (post/reverse), trial balance, position, performance,
  ledger, standards register.

### Revenue (verified slice)

- Parties, dated roles, contact points, properties with decimal GPS, dated and
  approved valuations, dated share-limited ownership, customer accounts with
  explicit liabilities, service connections, meters and readings with
  continuity, rollover and abnormal flags.
- Effective-dated approved tariffs with progressive bands, fixed charges,
  minimums, valuation basis and proration; calculation explanations.
- Billing preview/approve/post, control totals, calculation snapshots, chunked
  restart-safe posting, partial unique index against duplicate bills,
  separation-of-duties approval, estimated-reading fallback marked estimated.
- Cashier sessions, verified capture, one receipt per payment, oldest-first
  allocation, balanced journals and unapplied-funds credit for overpayment.
- Sandbox payment intents/attempts/settlements, HMAC webhook verification,
  timestamp tolerance, nonce replay protection, idempotent settlement, pending/
  failed/cancelled/verified states, no receipt from a browser return.
- Bank statement CSV staging, duplicate/hash detection, matching suggestions,
  confirmation, bank-import verification source, reconciliation summary.

### Citizen surfaces

- Inertia portal: overview, account detail, bills with explanations, verified
  payments/receipts, payment intent and sandbox confirmation, account linking and
  revocation, service requests, PDF statement.
- Account linking requires a code sent to a verified contact; five attempts,
  15-minute expiry, hashed codes, 404 for unlinked IDs, audited revocation.
- Flutter source: secure token storage, login, account/bill/payment screens,
  intent creation, linking, service requests with an unsent offline draft.
  **No Android/iOS build was produced.**

### AI

- `laravel/ai` v1.3.0 installed; no key committed; disabled by default.
- Permission-gated, budget-aware policy assistant using approved documents only;
  keyword retrieval before any provider call; source excerpts with dates and
  authority; disclaimers; provider failure isolation; usage/token/cost records.
- Generation path is implemented against the SDK's `AnonymousAgent::prompt()`
  API but **not exercised** because no provider credentials exist here.

## 3. Test evidence

Command (from `backend/`):

```powershell
php artisan test
```

The suite runs against real PostgreSQL databases
(`manicerp_test_central`, `manicerp_test_demo`, `manicerp_test_demo_b`), creates
them once per process, runs migrations, and wraps each test in central/tenant
transactions. It covers:

- accounting: balanced/unbalanced, closed period, immutability, reversal and
  double reversal, idempotency, unknown account, trial balance;
- chart: leading zeros, duplicates, cycles, orphans, supersession, demo/official
  blocker;
- revenue: tariff effective dating, preview→approve→post, snapshot, journal,
  self-approval rejection, double-post prevention, idempotent preview;
- payments: signed webhook settlement, replay rejection, bad signature,
  idempotent settlement, overpayment unapplied credit, failure/no receipt,
  browser-return/no receipt, allocation;
- cash/bank: session capture, statement import idempotency, conflicting
  statements, matching, reconciliation variance, unresolved lines;
- access: portal OTP linking, guessed IDs, revocation, wrong code; isolation of
  operational data, staff session cross-council reads, token without membership;
- security/platform: login, bad credentials, MFA challenge, cashier forbidden,
  module dependency/core lock/unavailable module, disabled-route 403, audit
  tamper detection, document download authorisation, executable upload;
- AI: approved-only retrieval, no sources, disabled generation, budget
  exhaustion, permission refusal.

Final result is recorded at the bottom of this file after the last full run.

## 4. What is scaffolded or not started

- Flutter app: source only. No SDK, build, emulator run, screenshot, push,
  deep link, store package or offline reading capture.
- Queued tenant jobs, scheduler tasks and event/outbox integration: no production
  job classes. Cache/queue tables exist.
- Refunds: table only. No service, approval UI or GL reversal.
- Payment plans, arrears notices, dispute holds, write-off approvals.
- Licence module: manifest only.
- Procurement, stores, assets, fleet, HR/payroll, land/housing/GIS,
  planning/performance, community/regulatory, governance records.
- Full IPSAS statements, notes, comparatives, consolidation/eliminations,
  transition workpapers, opening balances, FX and hyperinflation.
- Migration staging for legacy data, control totals and reconciliation.
- Cross-council authority/reporting.
- Malware scanning, S3 private storage, backup automation and restore test.
- AI customer assistant, document extraction, anomaly detection, commentary.

## 5. External dependencies and blockers

| Blocker | Impact |
| --- | --- |
| No official Treasury/council chart or accounting-heads schedule | Production reporting blocked; demo chart labelled |
| No verified IPSASB/Treasury/PAAB source documents or council policies | Standards register remains unverified; disclosures blocked |
| No payment/bank/mobile-money API credentials | Sandbox adapter only |
| No STS provider/hardware contract | Prepaid tokens not implemented |
| No Flutter SDK / signing identities | Mobile builds and store publication cannot be verified |
| No SMTP provider configured | Verification codes only visible in logs/array mailer |
| No PostGIS | Coordinates are decimals, not a cadastre |
| No malware scanner | Documents marked not_scanned |
| No Redis, no Docker daemon | Database drivers only; container deployment unverified |

## 6. Known limitations / risks

- Session-based council selection is enabled by default for local development.
  In production, councils should be resolved by verified hosts; set
  `TENANCY_ALLOW_SESSION_SELECTION=false` and configure `council_domains`.
- The chart, tariffs and posting rules shipped in the demo are illustrative.
- Billing recognises revenue at issue; IPSAS 47 analysis is absent.
- The financial position includes the current-period result and is a management
  report, not a complete statement.
- Refunds, write-offs and year-end close are absent; do not use for a live
  financial year without them.
- No rate limiting on API write endpoints beyond the `api` limiter; no per-council
  quotas.
- Documents are served from local private storage; no S3, no antivirus.
- The AI assistant has no evaluation corpus beyond the unit tests and no
  embedding retrieval; it is keyword-based.
- CI has not been executed on GitHub in this environment.

## 7. Next task

Add queued tenant jobs (billing continuation, statement/receipt PDF generation,
notification dispatch) with a serialized validated council identifier,
`ModuleRegistry` checks and context clearing, plus tests proving no context
leakage. Then implement refunds and opening balances.

## 8. Reproduce from a clean database

```powershell
# central schema
php artisan migrate --path=database/migrations/central --force

# demo council, database, roles, users, chart and demonstration data
php artisan manicerp:demo

# rebuild web assets
pnpm install
pnpm run build

# serve
php artisan serve

# verify audit chain and run the suite
php artisan audit:verify
php artisan test
```

## 9. Final verified test result

Command: `php artisan test` from `backend/`, PostgreSQL 16, 2026-11-05.

Result: **71 tests passed, 248 assertions, 0 failures** (62 seconds).

Pint formatting check: run `./vendor/bin/pint` (applied) and
`./vendor/bin/pint --test` for CI.

Additional runtime smoke test (development server on port 8123):

- `GET /login` → 200 with built Vite assets and a CSRF token;
- staff sign-in (`clerk@demo.manicerp.test`) → 302 to `/home`;
- `GET /revenue/accounts` → 200 containing the demo account numbers;
- `GET /dashboard` → 200;
- citizen sign-in → `GET /portal` 200 with the linked account;
- `GET /portal/accounts/{id}/statement` → 200, `application/pdf`, 25 KB, `%PDF-`;
- `POST /api/v1/auth/login` → token with `council:DEMO,portal`;
- `GET /api/v1/accounts` with the token → 200 with `ACC-0000001`;
- `php artisan audit:verify --council=DEMO` → 77 events verified.

The Flutter model tests in `mobile/test/models_test.dart` have **not** been run:
no Flutter/Dart SDK is installed in this environment.
