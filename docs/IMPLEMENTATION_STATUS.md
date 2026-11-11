# Implementation status

Updated 2026-11-05 (Africa/Harare). This is a working demonstration, not an approved production financial or licensing system.

## Resume point and baseline

The requested milestones now include **Financial Readiness and Zimbabwe Reporting Configuration** and **Municipal Licensing and Renewals**. The existing application was continued in place; no application regeneration or operational database reset was performed.

The supplied handoff reported 75 tests / 296 assertions. The first run of the existing working tree instead passed **95 tests / 409 assertions**: unfinished source-register, opening-balance and tenant-job work was already present. That work was preserved and extended. The original production frontend build also passed.

At the start of the procurement-controls/documents milestone, the requested
baseline was reproduced serially at **159 tests / 813 assertions**. The final
serial suite now passes **162 tests / 915 assertions**. The acceptance tenant
received only the pending additive procurement migration; its data was not
reset.

The default shell resolves PHP 8.2.12 in XAMPP, which cannot load this application's dependencies. Use **C:\php84\php.exe (8.4.25)**. Composer 2.10.2, PostgreSQL 16 on port 5433 and Node 20.19.2/pnpm are available. The repository's AGENTS.md required Laravel Boost installation; Boost 2.10.4 and its development dependencies were installed. No runtime framework upgrade was performed.

## Implemented and deterministically tested

| Area | Delivered behavior | Evidence |
| --- | --- | --- |
| Existing foundation | Per-council databases, membership/module gates, MFA, immutable journals, payment idempotency, chart validation, billing, receipts, bank reconciliation, portal/API, audit and private documents preserved | Existing full-suite journeys retained; manual-journal account selector prop repaired during type checking |
| Source register | Two SHA-256-checked supplied documents; 15 extracted requirements; separate examination, extraction, applicability, council-policy approval and implementation-test states; five standards linked to source sections without verifying them | AccountingSourcesTest, AccountingStandardsTest |
| Tenant processing | Serialized council ID, active-council/module checks, bounded retries, context restoration including custom log fields, council cache/storage paths, traversal rejection, separate council log files, per-council scheduler | TenantQueueTest includes real database queue deserialization, three failed attempts, a subsequent different-council job, nested context and file/cache/log isolation |
| Opening balances | Downloadable CSV templates; staging, preview, row errors, malformed money/date/dimension checks, duplicate GL rows and customer references, currency/cutover checks, independent approval, locked-period checks, posting once and reconciliation | OpeningBalanceTest |
| Opening customer data | Outstanding invoices, credit balances, deposits and unapplied funds staged without invented historic payments/receipts; receivable and credit controls reconciled; customer controls excluded from a separate GL batch to prevent double-counting | OpeningBalanceTest; immutable posted batch/row guards |
| Refunds | Request, reservations, approval threshold, independent approval/rejection, explicitly labelled sandbox settlement, retryable failures, partial refunds, stable idempotency and receipt preservation; payment refunds cannot consume unrelated opening credits or deposits | RefundTest and RefundConcurrencyTest; stale duplicates and two-process approval contention tested; row locks serialize mutation paths |
| Allocation reversal | Explicitly restores the invoice and posts a receivable/unapplied-funds transfer; separate from refund and receipt reversal; subsequent allocation excludes settled/reserved refunds | RefundTest traces receipt -> allocation -> reversal -> refund -> balanced GL |
| Report configuration | Source-backed draft headings for position, performance by nature, changes in net assets/equity and a separate unavailable-budget template; versioned account mappings, effective dates and dimension filters; independent approval; approved versions immutable | FinancialReadinessTest |
| Mapped statements | Posted-data amounts, debit/credit sign conventions, unmapped/ambiguous/currency checks, ordered comparative periods, GL reconciliation, opening/movement/closing equity plus unclosed result, function/programme expense notes | FinancialReadinessTest; missing functions disclosed |
| Revenue policies | Draft/independent approval, source and binding-arrangement/compliance-obligation rationale, effective-dated versions, overlap rejection and controlled prospective supersession; approved account/method fields immutable | FinancialReadinessTest |
| Billing recognition | Previous behavior was immediate receivable/revenue posting. Approved immediate policies retain it; deferred/advance/deposit classifications credit a configured liability when billed. Policy/version/accounts are snapshotted; historic postings preserved. No-policy fallback is confined to a DEMONSTRATION chart | BillingTest tests deferred billing with zero revenue credit |
| AI | Existing Laravel AI SDK retained. Explicitly approved retrieval excerpts include section/page/hash and distinguish source guidance from an expressly approved council policy. Expired documents excluded; document/question text untrusted; no financial write tools | FinancialReadinessTest, PolicyAssistantTest; live provider generation unverified |
| UI and contracts | /accounting/readiness and /revenue/refunds, gated actions, role-aware navigation, citizen account refund statuses and additive GET /api/v1/accounts/{account}/refunds with linked-account checks | Page/permission and citizen access tests; frontend production build |

Migration cutover never changes the IPSAS adoption date. Generic journal reversal is blocked for opening/refund/allocation journals so a subledger cannot silently diverge. Approved and posted imports have no edit/delete action.

## Municipal licensing and renewals

The backend, staff ERP, citizen browser portal and versioned API include a
tested licensing workflow. This milestone extends it with stage billing,
controlled deferred revenue release, AI-assisted review drafts and per-user
notifications. Flutter source accepts charge IDs and notification API contracts,
but Flutter/Dart are unavailable here, so no mobile build was verified.

| Area | Delivered behavior | Evidence / limit |
| --- | --- | --- |
| Licence type configuration | Council-tenant type records and immutable numbered configuration versions; authority/effective dates; independent approval; explicit demo seed restricted to `is_demo` councils; submission stores the applicable snapshot | `LicensingWorkflowTest`; no statutory fee or legal requirement is asserted |
| Application record | Distinct instrument and application/renewal attempt; idempotent submissions; active-attempt prevention; private document version history; reviewer queues with holder/reference/status filters | `LicensingWorkflowTest`; no production council configuration supplied |
| Staff processing | Review, information request, inspection assignment/results, reasoned approval/rejection, configurable high-fee permission threshold, independent issuer, suspend/revoke/reinstate with reason and audit | `LicensingWorkflowTest`; two-process PostgreSQL decision contention leaves one decision; approved appeal configuration remains disabled |
| Citizen access | Verified linked-account enforcement for drafts, submissions, progress, payment intent, information response, renewal and refund requests; non-linked representative sees no application; private document access is scoped | `LicensingWorkflowTest`; public verification is opaque-ID based |
| Staged charges | Application, renewal, inspection and issuance charges use shared bills, payment intents, settlement and allocations. Each charge stores the immutable configuration version, stage trigger, prerequisite, refund rule, policy version and accounting snapshot. Retries reuse a stage event key; partial payments stay outstanding; waiver requires authority and a linked credit note | `LicensingWorkflowTest::test_inspection_and_issuance_charges_use_snapshot_and_gate_their_stages`; no retrospective charge after revision |
| Revenue recognition and refunds | Immediate policies post revenue at billing. Approved deferred policies post a liability and release only against their snapshotted trigger/evidence through a balanced journal linked to the original bill and policy version. Rejection/expiry refund eligibility is configuration-bound; credit notes reverse unearned liability and released revenue as applicable | `BillingTest`, `LicensingWorkflowTest::test_deferred_fee_releases_once_and_rejected_refund_corrects_the_liability`; demonstration immediate/deferred policies only; no automated term job |
| Certificates and verification | Branded PDF with unique reference, holder/type, conditions, issue/expiry and QR; public page reports authenticity separately from current licence validity; verification responses are not cached; prior certificate versions remain linked | `LicensingWorkflowTest`; no signed/encrypted certificate or independent PKI verification |
| Notifications | In-app notices are deduplicated and scoped to the applicant or authorised staff recipient; browser inbox and API list/read cannot cross users. External email/SMS/push adapters report `unavailable` until configured. Tenant delivery jobs track attempts, failures and delivery with retry | `LicensingWorkflowTest::test_in_app_notifications_are_deduplicated_and_user_scoped` uses a deterministic adapter that fails once then succeeds; external delivery remains unavailable |
| AI | Installed Laravel AI SDK adapter can extract only configured typed fields from the source document version and draft information requests/staff correspondence from recorded findings and the version snapshot. Provenance includes document ID/version/hash. Accept/correct/reject controls only staff draft fields; submitted data, decisions, issuance and money remain untouched | `LicensingWorkflowTest::test_ai_extraction_and_correspondence_are_review_only_and_version_linked` uses a deterministic provider fixture; live provider generation is unverified without credentials |
| API/mobile | API exposes staged balances, charge-specific payment intents/refund requests, per-user licensing notification list/read and private certificate download. Offline drafts remain local and marked unsent until sync | Browser/API contracts documented in `docs/API.md`; Flutter/Dart unavailable, so dependency resolution, analyzer, device tests and Android build were not run |

The workflow test exercises evidence upload, application charge, payment,
review, inspection charge/payment, decision, issuance charge/payment,
certificate, verification, and renewal. A separate two-process test confirms
competing issuers return one certificate. Production council configurations
must identify the council authority, effective dates and independent approval;
`DEMO_TRADER` values are illustrative only. Appeals remain an extension point:
an approved rule must define authority, period, reviewer separation, stay effect,
evidence/decision record, certificate effect and privacy before enabling a route.

## Procurement, budgets and accounts payable

The procurement module now has an enabled, permission-gated staff workbench at
`/procurement` and guarded local demonstration commands. It reuses supplier
party records, encrypts supplier bank account numbers, requires separate
supplier/bank detail approvals, keeps approved budget lines under row locks,
records budget amendments/transfers, adjusts requisition commitments to
awarded POs, and converts invoiced portions from commitment to actual so the
same amount is not consumed twice.

The workflow supports requisition approval, quotation/evaluation evidence,
award approval, purchase orders, partial receiving, duplicate supplier invoice
detection, strict three-way matching, reasoned match exceptions, invoice/credit
journals, independently approved partial payment proposals, explicitly
sandbox-only settlement, supplier withdrawal matching to a bank statement,
and supplier/AP/budget reports. Private, council-scoped supplier evidence,
quotation, PO, receiving and invoice attachments now use generated storage
keys, safe filenames, size/type validation, immutable versions, read-time
permission checks, retention metadata and explicit `not_scanned` status. The
workbench exposes uploads, preview/download, version provenance, amendments,
partial cancellation, closure, reopening and order history.

Approved PO versions are immutable. Reasoned amendments preserve before/after
line values and require independent approval; increases recheck available
budget under row locks, and quantities cannot fall below prior receipts,
invoices or cancellations. Partial cancellation releases only remaining
unreceived commitment. Closure blocks unresolved receipts, invoices, matching
exceptions and pending order decisions; it releases eligible commitment but
does not reverse actual expenditure or AP and does not require supplier
payment. Reopening is separately requested/approved and later amendments must
revalidate the budget. Requisition submission reserves the estimate; award
reduces it to the PO value; posted invoices convert commitment to actual.

Run the resumable synthetic acceptance scenario with:

```powershell
$env:DB_DATABASE = 'manicerp_acceptance_central'
php artisan manicerp:acceptance:procurement --as-of=2026-11-05
Remove-Item Env:DB_DATABASE
```

The scenario reports a $2,500 budget; PO version 1 at $1,100 amended to version
2 at $1,650; 0.400 received/invoiced, 0.300 cancelled and 0.800 subsequently
received/invoiced; and partial closure as `partially_cancelled`. Total AP is
$1,320, sandbox-paid $200 and remaining payable $1,120. Commitment is $0,
actual expenditure is $1,320 and available budget is $1,180. AP subledger and
GL 2100 both equal $1,120 (difference $0); bank cashbook and statement both
equal $1,500 (variance $0); trial balance debits and credits both equal
$10,785.79. A second run preserved record counts and the same balances.

`ProcurementConcurrencyTest` launches independent PHP processes and confirms
actual PostgreSQL lock waits for competing budget reservations, competing
payment approvals, invoice settlements, duplicate callback retries, stale
order amendments, and cancellation versus invoice posting. The winning
reservation is $80 against $100 with $20 remaining; only one of two $70
approvals survives against a $100 invoice; two valid $50 settlements pay a
$100 invoice once each; duplicate callbacks leave one payment/journal; stale
amendments cannot overwrite the winning version; cancellation cannot race
through a posted invoice. The demonstrated budget reservation occurs on
requisition submission, not on the later approval decision.

This is a functional demonstration slice, not a production-procurement claim.
Enforceable quotation-count thresholds, a complete supplier-return/correction
workflow for reducing received or invoiced quantity, full reversal/settlement
history UX, real payment/bank integration, antivirus scanning and full
reporting remain open. Stock and asset receipts are expensed; no inventory or
asset register is updated. Demonstration thresholds and accounts are not
statutory rules or approved council settings. Keep the real bank/payment
integration blocker.

## Verification commands and result

From `backend/` in PowerShell:

```powershell
$env:Path = 'C:\php84;' + $env:Path
php artisan test
php vendor/bin/pint --dirty --format agent
pnpm exec tsc --noEmit
pnpm run build
php artisan route:cache
php artisan route:clear
git diff --check
```

The prior milestone's tree passed **159 tests / 813 assertions**; the current
serial suite passes **162 tests / 915 assertions**. Pint, TypeScript
(`pnpm exec tsc --noEmit`) and production Vite build pass for the current tree.
Route-cache compilation/clear and `git diff --check` are recorded in the
acceptance report. The procurement page adds a 16.98 kB chunk. The Refunds
chunk remains 4.68 kB and the largest shared chunk is 358.87 kB. See
[ACCEPTANCE_REPORT.md](ACCEPTANCE_REPORT.md) for exact results and blockers.

The main harness uses only `manicerp_test_central`, `manicerp_test_demo` and `manicerp_test_demo_b`, rejecting other names before destructive setup. The two-process refund and licensing contention tests create uniquely named temporary PostgreSQL databases, restrict operations to their prefixes, and remove their tenant DBs and central fixtures in finally cleanup. No `migrate:fresh` was run against DEMO or an operational council database.

Targeted evidence includes opening posting/reconciliation and duplicate rejection; partial sandbox refunds with original receipts retained; approval/settlement retry guards and period locks; mapped performance, position and equity; immediate and deferred licence accounting; a related refund correction; review-only AI; notification retry; source-guidance citations; expired MFA challenge, charge-waiver authority and reason, information request/response, payment-without-approval, and cross-council evidence/inbox isolation. RefundConcurrencyTest and LicensingConcurrencyTest launch two PHP processes, observe PostgreSQL row-lock contention and verify one decision/audit event and one certificate. Run the PHPUnit suite serially: concurrent invocations can race while both recreate the dedicated test databases. Broader production load testing remains outstanding.

## Local DEMO and acceptance environments

`DEMO` (`manicerp_demo`) remains the existing local demonstration council. A separately guarded local-only acceptance environment uses `manicerp_acceptance_central` and `manicerp_acceptance_demo`; the runner refuses non-local settings, non-loopback DB hosts, cached config and wrong database names, and applies migrations without dropping data. Six synthetic staff actors have separate roles, including an MFA-required inspector. New credentials are written only to an ACL-restricted, git-ignored local file; no passwords are printed in reports. Privileged acceptance actors have not yet completed MFA enrollment in the browser.

```powershell
php artisan council:migrate --council=DEMO
php artisan accounting:seed-sources --council=DEMO
php artisan audit:verify --council=DEMO
```

Two documents, 15 requirements and five source-linked standards were registered. The audit chain passed with **81 events**. No council policy was approved, official code designated, tax activated, opening batch posted or refund settled in the separate local `DEMO` council by this continuation. The isolated `ACCEPTANCE_DEMO` tenant was preserved and topped up in place; it has 4 synthetic parties, 10 bills (including the opening receivable), posted opening batches, 2 sandbox payments, 2 receipts and 2 allocations. Its revenue and procurement controls reconcile in the acceptance report. No citizen account link or licensing application was created there, and no authenticated browser acceptance is claimed.

Staff can use the new pages with appropriately assigned permissions. Updated default role definitions separate finance preparation from approval for new provisioning. Existing council roles are not automatically broadened: assign the new scoped permissions through controlled council access configuration before using them. Council administrators retain their existing wildcard access, but cannot approve their own prepared items.

The scheduler registers `council:run-scheduled audit-verify` daily at 02:00 Africa/Harare with overlap prevention. An external scheduler must invoke `php artisan schedule:run`; no OS task was installed. Queue workers use the central PostgreSQL jobs table; retry_after defaults to 180 seconds, longer than the 120-second job timeout. Existing billing and PDF actions remain synchronous; the tested base is available for future production jobs. Central failed-job diagnostics are administrator-only operational records. Council job payload logs use `storage/logs/councils/{code}.log`.

## External verification and council approval

The supplied ZFRM and internally titled **Finance Act, 2023, No. 13** were examined and hashed. ZFRM section 1.2.2 names urban and rural district councils; section 1.3.5 lists early adoption of IPSAS 43-48. This does not verify the current complete authority or approve a council policy. All 12 existing standards retain their unverified status.

Treasury/ZIMRA sites were checked for later material; the source register documentation records discoveries and the remaining amendment-review boundary. Historical tax rates were not activated. No council-approved accounting policies or official posting schedule were supplied. See ACCOUNTING_SOURCES.md for hashes, exact pages and source links.

## Remaining limitations and production blockers

- Official Treasury/council chart and accounting-heads schedule missing. The active chart remains DEMONSTRATION. Report labels and internal identifiers are not official Ministry account codes.
- Current complete ZFRM/legislative amendments, local applicability and council policies still require qualified review and approval. No automatic unreserved IPSAS compliance statement is produced.
- Report templates cover supported posted data and selected source headings. Complete disclosures, notes, consolidation, cash-flow classifications, valuations, FX, impairment and hyperinflation remain outside this slice.
- Operational commitment budgets and budget-versus-actual reports now have a demonstration slice. Government budget accounting, official vote mapping and comparable-basis reconciliation for IPSAS Appendix 5 remain unavailable; its template remains explicitly labelled unavailable.
- Deferred licensing balances are held in liabilities and can be released by an authorised officer only after the approved event/evidence check. A term-elapsed rule has a bounded schedule from the bill date, but a scheduled automatic release job is not implemented. Advance receipts without a bill still use existing unapplied-funds accounting. Deposit release/refund requires a separately approved policy and workflow; deposits are not treated as freely refundable unapplied credit.
- A full post-cutover correction wizard that changes customer balances and GL together is not implemented. Posted migration journals cannot be generically reversed or edited; downstream corrections require a designed, approved reconciliation workflow. This is a software follow-up, not an external-document blocker.
- Only the sandbox refund adapter is available and is enforced. It moves no real money. A real provider needs verified integration, idempotent settlement, callback reconciliation and credentials before enablement.
- Live AI generation remains unverified without a provider key. Fixture-based extraction/draft tests do not establish provider accuracy. AI cannot approve/post/refund/change official codes or remove blockers.
- Email, SMS and push adapters remain unavailable pending council configuration and external credentials. In-app delivery and retried adapter state transitions are tested; no external notice was sent.
- Flutter/Dart SDK and signing identities remain unavailable; no mobile build or store artifact was verified. The existing API contract is preserved, with an additive refund-status endpoint.
- Redis is unnecessary for these tests; production worker deployment, OS scheduling, restore drills, antivirus, external mail and real payment/bank integrations still need operational work.

## Remaining licensing follow-up

The municipal MVP remains **partial**. The registration page was rendered and
submitted with synthetic data; the in-app browser URL security policy denied
navigation to the original signed verification link. No authenticated browser
journey, MFA enrollment or account-link journey is claimed. Use the normal
browser script at [MANUAL_ACCEPTANCE_SCRIPT.md](MANUAL_ACCEPTANCE_SCRIPT.md).
Flutter is unavailable and live-provider AI is disabled/unverified. Procurement
is now an enabled demonstration module with a staff workbench and runnable
amendment/closure, private-document and concurrency evidence. Quote thresholds,
supplier-return/correction controls and rendered-browser acceptance remain
gaps. Keep appeals disabled until authority is supplied. Stores,
assets, fleet, HR/payroll and land allocation remain unavailable modules.
