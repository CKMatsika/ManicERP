# ManicERP municipal MVP and licensing acceptance report

**Result: Partial; end-to-end web MVP not accepted.** The baseline was
reproduced, the acceptance seed now resumes without duplicates, and executable
revenue and procurement scenarios reconcile to their control accounts. The
browser tool denied the signed verification URL; no authenticated browser
journey or narrow layout is claimed. Procurement and revenue evidence below is
from guarded local commands and database-derived controls, not rendered-browser
acceptance. Flutter build/device and live-AI generation remain unverified.

## Tested tree and environment

- Date: 2026-11-05 (Africa/Harare).
- Repository HEAD: `608b940a3a931832e2050f35d71440985656086d`.
- Tested source: HEAD plus existing uncommitted working-tree changes and this
  pass's registration, acceptance-bootstrap, billing-index, test and doc changes.
  No commit was made.
- Host: Windows; PHP 8.4.25; Composer 2.10.2; PostgreSQL 16 on port 5433;
  Node 20.19.2 and pnpm 10.24.0.
- Reproduced this milestone's requested baseline: **159 tests / 813
  assertions passed**. The final serial suite after procurement controls and
  documents passes **162 tests / 915 assertions**. PHPUnit
  configured and guarded its destructive setup to
  `manicerp_test_central`, `manicerp_test_demo` and `manicerp_test_demo_b`.
  It did not reset or migrate the local `manicerp_central` or `manicerp_demo`
  databases. PHPUnit fixture users use synthetic `test.local` identities. A
  separate `manicerp_acceptance_central` and `manicerp_acceptance_demo` were
  created for local acceptance. The bootstrap applies pending migrations only;
  it never drops an existing acceptance database.
- Six separate synthetic staff actors were created (administrator, cashier,
  reviewer, inspector, licensing approver and finance approver). The inspector
  role is MFA-required and limited to viewing applications and inspections.
  Generated credentials reside in an ACL-restricted, git-ignored local JSON
  file; no secret is included in this report. Staff MFA enrollment remains
  pending because the browser journey did not reach sign-in.
- The initial seed had stopped after 4 parties and 3 bills. The tenant was
  preserved and completed in place; it now contains 4 synthetic parties, 10
  bills (including the opening receivable), 2 verified sandbox payments, 2
  receipts, 2 allocations and 11 posted journals before the separate AP
  scenario. No citizen account link or licensing application was created.
- The acceptance step ledger and all three guarded bootstrap/revenue/procurement
  commands were rerun. Master data, bills, receipts, journals, opening batches,
  PO, invoice and settlement counts remained unchanged on completed steps.
- Run PHPUnit serially: overlapping database-resetting suites previously
  deadlocked. The final run was serial.

## Automated scenarios and outcomes

All scenarios in this section are automated PHPUnit evidence, not observations
from a live authenticated browser session.

| Scenario | Evidence and outcome |
| --- | --- |
| Synthetic authentication and role controls | `AuthenticationTest` covers valid/invalid sign-in, membership requirement, MFA setup, first-time TOTP enrollment through setup and confirmation, challenge, and expired privileged MFA session. The standard MFA flow is tested; MFA enrollment was not performed in the live browser. |
| Account linking and citizen isolation | `PortalAccessTest` covers verification before access, incorrect-code rejection, revocation and guessed-account privacy. `LicensingWorkflowTest::test_unlinked_representative_cannot_read_or_pay_an_application` covers an unlinked citizen. |
| Draft, evidence, submission and snapshots | `LicensingWorkflowTest::test_citizen_to_certificate_to_public_verification_and_renewal_preserves_history` covers evidence versioning, idempotent submission, submitted configuration/charge retention after revision, certificate history and renewal. |
| Information request and response | The lifecycle feature test now requests information as a reviewer, verifies `information_requested`, then responds as the linked citizen and verifies return to `submitted`. |
| Staged payment prerequisites | `test_inspection_and_issuance_charges_use_snapshot_and_gate_their_stages` checks application, inspection and issuance charges and blocks later stages until settlement. The end-to-end test asserts verified payment leaves the application `submitted` until staff review and decision. Shared payment tests cover signed sandbox settlement, receipt/allocation, replay and duplicate settlement. |
| Inspection, decision and single issuance | The lifecycle journey uses separate fixture users for review, inspection, decision and issue. `LicensingConcurrencyTest` runs competing decision and issue processes against PostgreSQL and leaves one decision and one certificate. |
| Certificate and public validity | The feature test checks certificate download and public verification, suspension, council-local expiry and revocation, including retained authenticity versus current status. |
| Rejection and refund correction | `test_rejected_refundable_application_uses_shared_credit_note_and_sandbox_refund_workflows` preserves the original receipt and verifies the linked credit note and sandbox refund journal are balanced. |
| Deferred revenue | `test_deferred_fee_releases_once_and_rejected_refund_corrects_the_liability` checks a USD 25.00 deferred fee: the release debits liability account 2050 and credits revenue account 4020 once; a rejected application receives a linked balanced correction and sandbox refund. These are demonstration account mappings/policies, not council-approved accounting. |
| Waiver controls | `test_charge_waivers_require_authority_and_a_reason` verifies a reviewer without waiver permission is forbidden, an authorised waiver without a reason is rejected, and a reasoned waiver records its linked balanced credit note and audit event. |
| Council/user file and inbox isolation | `test_another_council_cannot_read_licensing_evidence_or_notifications` checks a permitted document reader in the other tenant receives 404 for the evidence, and the other tenant's web/API licensing inbox and application list are empty. Existing tenant queue tests also check council-scoped storage paths. |
| Notification retries | `test_in_app_notifications_are_deduplicated_and_user_scoped` uses a deterministic adapter that fails once, then succeeds; inbox scope and private preview content are checked. External email/SMS/push remain unavailable. |
| AI review boundary | `test_ai_extraction_and_correspondence_are_review_only_and_version_linked` uses a deterministic fixture provider and confirms provenance/version history and that accepted values remain staff drafts rather than overwriting submitted fields. |

These tests establish deterministic ledger and control behavior for the listed
fixtures. Synthetic ledger transactions were added only to the dedicated
`ACCEPTANCE_DEMO` tenant; the separate local `DEMO` council was not changed by
the scenarios.

## Rendered browser observation

The in-app browser opened the isolated acceptance app at
`http://127.0.0.1:8001/register`. The rendered registration form showed the
synthetic acceptance council and clearly stated that registration does not
grant customer-account access. The form was submitted with synthetic identity
data. A read-only central-database check confirmed that the user row remained
`pending_verification` and unverified, with no membership or portal-account
link.

The local mail transport wrote the signed verification link to the Laravel log.
The in-app browser URL policy rejected navigation to that link because it would
not grant authority for the requested URL. It prohibited achieving the same
navigation through another surface or indirect method. No workaround was
attempted. The user authorized the synthetic registration, but
the browser policy denial remains an execution blocker. Therefore email
verification, sign-in, MFA enrollment, verified account linking and the
licensing journey were not completed in the browser.

The browser session reported a rendered desktop form, but viewport dimensions
were not available for this run. No narrow layout was checked. Registration
server-side validation and expiry/access behavior are automated; useful browser
validation, expired-session, authenticated loading/error states, evidence upload,
certificate download, public QR status and rejection/refund remain manually
unobserved. The UI form submission is the only authenticated-flow-adjacent
browser action observed.

## Acceptance revenue scenario and independent reconciliation

Command: `php artisan manicerp:acceptance:revenue --as-of=2026-11-05` from
`backend/` with `DB_DATABASE=manicerp_acceptance_central`. It calls existing
opening-balance, billing, payment, cashier, bank and reporting services. All
values below are USD minor units (100 minor = USD 1.00). The command completed
twice; its second run created no new financial records.

| Control | Independently recomputed subledger | Posted GL / statement | Difference |
| --- | ---: | ---: | ---: |
| Opening GL batch | 10,000 debit / 10,000 credit | Balanced journal | 0 |
| Opening customer invoice | 50,000 | Rates receivable control | 0 |
| Opening unapplied customer credit | 5,000 | 2050 liability credit | 0 |
| Rates, water, refuse bills | 614,000 + 15,579 + 3,000 = 632,579 | Revenue accounts 4010–4030 = 632,579 | 0 |
| Bill debt after payments | 448,579 | Receivables 1310–1330 net debit = 448,579 | 0 |
| Net debt after opening credit | 443,579 | Receivables less 2050 opening credit = 443,579 | 0 |
| Verified payments / receipts / allocations | 234,000 / 234,000 / 234,000 | Receipt journals: 1110 debit 64,000; 1120 debit 170,000 | 0 unapplied payment balance |
| Cash on hand | Opening 10,000 + cashier 64,000 = 74,000 | 1110 debit balance 74,000 | 0 |
| Bank receipts at revenue stage | Verified online settlement 170,000 | 1120 debit balance before supplier settlement 170,000 | 0 |
| Bank statement at revenue stage | Closing 170,000 | Cashbook 170,000 | 0 |
| Trial balance | Debits 926,579 | Credits 926,579 | Balanced |

No deferred-recognition bills were created in this scenario. The 5,000 credit
on account 2050 is an opening unapplied customer credit, not deferred revenue.
No amount was plugged or forced to reconcile. Official fees/codes, complete
IPSAS statements and live payment movement remain unverified.

## Acceptance procurement scenario and AP reconciliation

Command: `php artisan manicerp:acceptance:procurement --as-of=2026-11-05`
from `backend/` under the same isolated database guard, after the revenue
scenario. The scenario used a
synthetic supplier, encrypted bank details, an approved 250,000 budget, a
120,000 requisition estimate, 110,000 evaluated quote/PO, a partial receipt of
400/1,000 milli-units, and a matched invoice for 44,000. A separate approver
approved a 20,000 payment proposal; the sandbox adapter recorded settlement.
A second statement line matched the 20,000 withdrawal to the supplier payment.
The command was rerun after completion without duplicating records.

| Control | Actual result |
| --- | ---: |
| Budget approved | 250,000 |
| Awarded PO | 110,000 |
| Commitment remaining | 66,000 |
| AP actual expenditure | 44,000 |
| Budget available | 140,000 |
| Invoice / sandbox payment / remaining AP | 44,000 / 20,000 / 24,000 |
| Supplier AP subledger | 24,000 credit balance |
| GL AP control 2100 | 24,000 credit balance |
| AP-to-GL difference | 0 |
| Bank before / settlement / after | 170,000 / (20,000) / 150,000 |
| Bank reconciliation variance | 0 |
| Trial balance after AP postings | Debits 990,579 = credits 990,579 |

After the supplier settlement, account 1120's combined cashbook balance is
150,000 and its latest acceptance statement closes at 150,000 (difference 0).
The revenue-stage 170,000 reconciliation is a valid earlier checkpoint before
the later 20,000 supplier withdrawal. The revenue command now reports the
latest statement-to-cashbook difference on reruns, instead of presenting the
stored earlier checkpoint as the current bank position.

### Procurement controls and documents milestone

The isolated `ACCEPTANCE_DEMO` tenant received the pending additive procurement
controls/documents migration only (`council:migrate --council=ACCEPTANCE_DEMO`).
No acceptance data was reset. The guarded procurement command completed the
following resumable scenario and was rerun; the second run preserved the same
PO, amendment, receipt, invoice, settlement, journal and statement identifiers
and balances.

| Control | Independently checked result | Difference |
| --- | ---: | ---: |
| Approved budget | 250,000 | — |
| PO v1 → v2 | 110,000 → 165,000 | — |
| Ordered / received / cancelled quantity | 1.500 / 1.200 / 0.300 | 0 quantity left unresolved |
| Invoice expenditure / AP actual | 44,000 + 88,000 = 132,000 | 0 |
| Commitment reserved / converted / remaining | 132,000 / 132,000 / 0 | 0 |
| Budget available | 118,000 | 0 (250,000 − 132,000 − 0) |
| Supplier payable | 132,000 invoices − 20,000 sandbox paid = 112,000 | — |
| Supplier AP subledger / GL 2100 | 112,000 / 112,000 | 0 |
| Bank before / withdrawal / after | 170,000 / (20,000) / 150,000 | 0 statement variance |
| Trial balance | 1,078,579 debit / 1,078,579 credit | Balanced |

Order closure status is `partially_cancelled`. It did not require the remaining
112,000 supplier payable to be paid and did not reverse AP or actual expenditure.
The v1 quantity remains in its immutable snapshot; v2 records its before/after
amounts and approval. Accepted, cancelled, invoiced and history rows are
preserved.

`ProcurementConcurrencyTest` uses independent PHP processes, synchronization
barriers, PostgreSQL `application_name`, and `pg_stat_activity` lock-wait
observations. Competing 8,000 reservations against a 10,000 line left one
8,000 commitment and 2,000 available; competing 7,000 payment approvals
against a 10,000 invoice left one approved proposal and no journal until
settlement; two valid 5,000 settlements produced one 10,000 paid balance and
two payment journals; duplicate callbacks created one payment/journal; stale
amendments preserved the winning version; cancellation could not pass a
concurrent invoice post after receipt movement. A later independent closure
released the eligible balance. Reservation occurs at requisition submission;
the later requisition approval does not create a second reservation.

`ProcurementDocumentTest` verifies file size/type handling, caller path
ignorance, sanitized original name, council-prefixed generated key, private
download/preview headers, immutable prior version after reasoned replacement,
retention metadata, cross-council 404, confidential quotation access and
permission re-check after role changes. Malware scanning is unavailable and
files are clearly `not_scanned`. No extracted text or live AI document
processing was run.

The AP match test exposed a floating-point boundary defect: received 1.000 less
already invoiced 0.400 could yield slightly less than 0.600 in binary float,
creating a false invoice exception. Matching now compares integer milli-units
and independently rounds the expected minor-unit value. Regression coverage
now posts the follow-up 0.600 invoice. An unposted acceptance invoice created
before that fix was safely rechecked against the corrected quantities and
matched; no journal was backdated or altered.

These are automated command/database results, not rendered-browser observations.
Supplier quote thresholds remain demonstration configuration. A linked supplier
return/correction workflow is still needed before reducing order quantities
below already received or invoiced amounts. Real payment, malware scanning,
complete reporting, official council procurement policy and live integrations
remain blocked.

This validates a deterministic staff-service scenario, not policy approval or
the browser journey. The $440 invoice was matched to the accepted 0.400
quantity; 0.600 remains outstanding. Supplier-bank account numbers remain
encrypted and are redacted from output. Procurement thresholds and account
codes are demonstration configuration only.

## Flutter and Android

Flutter, Dart and `adb` were not found on `PATH`. A directory exists at
`%LOCALAPPDATA%\Android\Sdk`, and Java is present, but no usable Flutter/Dart
toolchain or Android debug build was available. No mobile source inspection is
reported as a build/test result.

After installing Flutter and Android command-line tools on Windows, run:

```powershell
cd C:\Users\User\Desktop\ManicERP\mobile
flutter doctor -v
flutter pub get
dart format --output=none --set-exit-if-changed .
flutter analyze
flutter test
flutter build apk --debug --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Then run on an emulator/device and verify API connection, citizen login and
session expiry, application/evidence access, private document download,
payment status from server settlement, certificate access and offline-draft
sync. No iOS build is claimed; that requires macOS and Xcode.

## AI provider

Provider configuration was checked without printing credentials: `AI_ENABLED`
is false, and the OpenAI and Anthropic key variables are unset. No live model
request was made. Fixture-backed extraction, provenance, correspondence draft,
human review, access and no-authoritative-overwrite checks passed in PHPUnit.
Live cited guidance, extraction and drafting remain unverified.

## Defects and fixes

- Added regression coverage for normal first-time MFA enrollment, expired
  privileged MFA sessions, information request/response, payment not approving
  an application, cross-council licensing document/inbox isolation and waiver
  authority/reason. All passed in the final suite.
- Added signed, expiring citizen email verification, rate-limited registration,
  generic duplicate-address behavior, no-account-link assertions and adverse
  expired-link/throttle cases. Five registration feature tests pass.
- Acceptance bootstrap first hit a duplicate-bill error. Inspection found that
  the licensing migration had replaced the revenue active-bill index, and that
  keyed one-off licence charges need to coexist for the same account and date.
  Added additive migrations that preserve per-service revenue uniqueness while
  excluding charges guarded by the unique `licence_application_key`. The two
  affected licensing tests pass, followed by the full suite.
- The initial demo seed partially wrote synthetic data before failing. The
  bootstrap now safely reuses the existing acceptance tenant and does not reset
  it; top-up/reconciliation of the partial seed remains open.
- The local suite's concurrent schema resets can deadlock each other. Serial
  execution is the verified procedure; coordinating independent test processes
  remains test-harness follow-up.
- The procurement work exposed a false invoice match exception at a fractional
  quantity boundary. The calculation now uses integer milli-units, is covered
  by a second partial invoice test and has re-evaluated the unposted acceptance
  invoice. The guarded purchasing scenario now reconciles after amendment,
  partial cancellation and closure.
- The in-app browser URL security policy denied the signed verification link
  and explicitly disallowed alternate browser surfaces or indirect workarounds.
  This is the reason the manual authenticated journey remains open.

## Verification commands

Run from `backend/` in PowerShell (do not start multiple PHPUnit runs at once):

```powershell
C:\php84\php.exe artisan test --compact
C:\php84\php.exe vendor/bin/pint --dirty --format agent
pnpm exec tsc --noEmit
pnpm run build
C:\php84\php.exe artisan route:cache
C:\php84\php.exe artisan route:clear
git diff --check
```

The requested baseline was **159 tests / 813 assertions**. The final serial run
after this milestone passed **162 tests / 915 assertions** (114.2 seconds).
Pint, TypeScript, production Vite build, route-cache compilation/clear and
`git diff --check` passed. The procurement chunk is 16.98 kB; the Refunds chunk
is 4.68 kB and the largest shared chunk is 358.87 kB. Flutter, live AI, and
authenticated browser checks were not executed; the browser URL restriction is
described above and documented in the manual script.

## Ranked next backlog

1. Run the manual steps in [MANUAL_ACCEPTANCE_SCRIPT.md](MANUAL_ACCEPTANCE_SCRIPT.md)
   in the user's normal local browser: open the original mail-log verification
   link, complete citizen linking and privileged MFA, then finish licensing and
   revenue journeys on desktop and narrow width. No security-policy workaround
   was attempted.
2. Implement approved council procurement thresholds, enforceable minimum quote
   counts and comparative evaluation controls.
3. Design a linked supplier-return/correction workflow before permitting
   reductions below received or invoiced quantities; add reversal and
   settlement-history UI. Keep closed-order AP semantics as tested.
4. Add deterministic fixture-backed invoice extraction, suggested matching and
   completeness assistance with provenance and human review. Keep live provider
   generation blocked until configuration and credentials are authorised.
5. Install Flutter, Dart and Android tooling if available; otherwise retain the
   mobile blocker and use the reproducible Windows commands above.
6. Preserve external blockers: official council chart/fees/procurement policy,
   real payment/bank and notification adapters, full statutory reporting,
   appeals authority and production operations.

The official fee schedule, council approval, real payment/delivery providers,
complete reporting and appeal authority remain blocked pending the required
external evidence and decisions.
