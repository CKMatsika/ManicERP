# Manual local browser acceptance

This is the supported manual fallback for the in-app browser URL-policy denial.
Use a normal local browser against the isolated `ACCEPTANCE_DEMO`; do not edit
the signed verification URL, disable verification/MFA, or copy test credentials
into screenshots or reports.

## Start the preserved local council

From Windows PowerShell:

```powershell
cd C:\Users\User\Desktop\ManicERP\backend
$env:Path = 'C:\php84;' + $env:Path
php artisan serve --host=127.0.0.1 --port=8001
```

The bootstrap uses `APP_ENV=local`, `manicerp_acceptance_central` and
`manicerp_acceptance_demo`; it must not point at any operational database. If
the acceptance DB is absent, use the guarded `backend/bin/New-ManicErpAcceptance.ps1`.
Do not use `migrate:fresh` or run the general DEMO seeder against this partial
acceptance tenant.

## Citizen registration and linking

1. Open `http://127.0.0.1:8001/register` and register the synthetic
   `citizen@acceptance.test` identity with a new local-only password.
2. In a separate local PowerShell window, inspect the newest registration mail
   entry in `backend/storage/logs/laravel.log`. Laravel's configured `log` mail
   transport records the message. Treat its original signed link as a
   credential. Open that unmodified link in the normal browser before expiry.
   Do not paste it into reports or screenshots.
3. Sign in as the citizen. Request account linking and enter the one-time code
   from the local mail log when prompted. Confirm that only the synthetic
   account becomes linked.
4. Sign out. Sign in as each privileged synthetic staff actor using the
   ACL-restricted credentials file under `backend/storage/app/acceptance`.
   Complete normal MFA enrollment/setup and confirmation. Keep TOTP seeds and
   recovery codes local; do not use one identity for steps requiring separation
   of duties.

## Core revenue journey

5. As citizen, inspect the account, statements and outstanding bills. Start a
   sandbox payment and verify a browser return alone does not mark it paid.
6. Complete settlement only through the server-side labelled sandbox flow.
   Confirm a verified payment, receipt and allocation appear once; reload and
   replay the return path to confirm no duplicate record is created.
7. As cashier, record a separate synthetic cash payment in an open session and
   close the session with the exact counted amount.
8. As finance staff, import/match the demonstration bank statement and review
   reconciliation variance and trial balance. Never add a balancing entry just
   to clear a variance.

## Licensing journey and adverse checks

9. As citizen, create a demonstration licence application, save a draft, upload
   synthetic private evidence, review snapshotted requirements/charges and
   submit. Confirm another citizen and another council cannot retrieve it.
10. Settle only the required sandbox stage charge. As reviewer, request more
    information; respond as citizen. Assign the inspector identity, record
    findings, and use separate decision and issue identities. Confirm payment
    alone never approves a licence and only one certificate is issued after a
    reload/retry.
11. Download the citizen certificate and verify its QR/public reference. Check
    expired, suspended and revoked statuses. Renew and confirm prior history is
    retained. Separately reject a refundable application and follow the
    credit-note/sandbox-refund workflow.
12. Repeat on desktop and a narrow viewport. Check invalid form feedback,
    permission-denied states, session expiry, loading/error states, inbox
    deduplication and accessible navigation.

## Evidence to record

Record date, browser/version, viewport, synthetic actor role, outcome and
non-secret record references in `docs/ACCEPTANCE_REPORT.md`. Record counts and
minor-unit totals for bills, customer balances/credits, receipts, allocations,
refunds, cash/bank journals and trial-balance debits/credits. Do not copy
passwords, cookies, signed links, OTPs, TOTP secrets, recovery codes, bank
account numbers, private evidence or personal data into the report.

If the app returns to an inaccessible security policy or required external
service, stop that specific check and record the observed blocker. Keep browser
results separate from PHPUnit/API evidence.
