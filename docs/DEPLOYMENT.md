# Deployment and operations

This is a production-oriented checklist, not a claim that hosting has been
provisioned or verified. No live deployment was performed for this build.

## 1. Hosting requirements

- Linux with PHP 8.4 FPM and the extensions listed in `SETUP-WINDOWS.md`.
- PostgreSQL 16+ (PostGIS optional but not used). One central database plus one
  database per council, or independent deployments if required.
- HTTPS reverse proxy with the real `Host` header forwarded.
- Private writable storage for `storage/app/private` (or a private S3-compatible
  bucket with server-side encryption).
- A queue worker and scheduler process for each environment.
- Backup storage separate from the database host.
- Verify private storage, workers, scheduler, backups and restore **before**
  choosing hosting.

## 2. Environment

Copy `backend/.env.example` and set at least:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.example.org
APP_KEY=base64:...            # generate once, store in a secret manager
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Africa/Harare

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=manicerp_central
DB_USERNAME=...
DB_PASSWORD=...
DB_SSLMODE=require

TENANT_DB_HOST=...
TENANT_DB_PORT=5432
TENANT_DB_USERNAME=...
TENANT_DB_PASSWORD=...
TENANT_DB_SSLMODE=require

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
DOCUMENTS_DISK=documents

TENANCY_ALLOW_SESSION_SELECTION=false
PAYMENTS_DEFAULT_GATEWAY=sandbox
AI_ENABLED=false
```

Secrets (database, sandbox webhook secret, AI keys) must come from the secret
manager, never from git. `backend/.env` is ignored.

## 3. Build and release

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan package:discover
php artisan config:cache
php artisan route:cache
php artisan view:cache
pnpm install --frozen-lockfile
pnpm run build
php artisan migrate --path=database/migrations/central --force
php artisan council:migrate
```

Order matters: back up first; run central migrations, then every council. A new
council is provisioned with `council:provision` (which creates its database and
runs tenant migrations). Verify `/up` and the audit chain afterwards:

```bash
php artisan audit:verify
```

## 4. Processes

- Web: PHP-FPM behind the proxy.
- Queue: `php artisan queue:work --queue=default --tries=3 --timeout=120`.
  Database queue/cache are used because Redis is not available; Redis is
  supported by configuration when present. No production tenant job exists yet,
  so the worker is required before adding one.
- Scheduler: `* * * * * php artisan schedule:run` (no scheduled tenant tasks are
  registered yet; period locks, billing reminders, statement generation and
  notification dispatch are planned).

## 5. Storage

Documents are written to the `documents` private disk under
`storage/app/private/documents`. Web server configuration must not expose
`storage/` or `public/storage` for this disk. Downloads go through
`/documents/{id}` with a permission check and audit event.

For S3-compatible storage, add a disk, set `DOCUMENTS_DISK`, keep the bucket
private, enable encryption and versioning, and restrict credentials to that
bucket. Uploaded content is not virus-scanned in this build; add scanning before
accepting third-party uploads in production.

## 6. Backups and recovery

Proposed targets (must be approved, implemented and tested; not met by this
build):

- **RPO 24 hours**, **RTO 4 hours** for a single council.
- Nightly `pg_dump -Fc` of the central database and each council database.
- Encrypted backup storage in a separate region/account.
- Document storage versioning/replication with the same retention.
- Quarterly restore rehearsal with a written record.

Restore procedure:

1. Provision an empty PostgreSQL instance.
2. Restore the central database first, then each council database.
3. Restore private documents to the matching paths.
4. Set the environment to the restored instance; do not point at the old one.
5. Run `php artisan migrate --path=database/migrations/central --force` and
   `php artisan council:migrate` only to bring schema forward, never to rewrite
   posted financial history.
6. Run `php artisan audit:verify` and reconcile subledger control totals to the
   trial balance before reopening service.

## 7. Rollback

- Application rollback: deploy the previous release artifact and run
  `php artisan optimize:clear` plus the previous cached config. Do **not** run
  destructive `migrate:rollback` on a database containing posted financial data;
  a code rollback must not silently undo transactions.
- Schema mistakes: ship a forward migration that repairs, and preserve evidence.
- Financial corrections: use the linked reversal workflow with a reason and
  approval, never direct edits or deletes.
- A failed council provisioning leaves `status = provisioning_failed` and a
  partially created database for inspection; do not delete it before capturing
  the platform audit event and logs.

## 8. Monitoring and incident handling

- Health: `GET /up` (framework) and `GET /api/v1/health`.
- Failed jobs: inspect `failed_jobs` and retry after fixing; do not blindly
  re-run billing jobs (billing posting is idempotent, but investigate first).
- Logs: structured application logs with secrets redacted; no payment card data
  is ever collected.
- Audit: alert if `audit:verify` fails; export/retain audit evidence off-host
  (not implemented).
- Queue and scheduler health should be monitored; no dashboards are included.

## 9. Go-live gates

Do not describe a successful demo as a production implementation until:

- an official chart and posting-rule matrix are imported, verified and approved;
- Treasury/PAAB sources and council accounting policies are approved and the
  standards register shows verified, approved entries;
- payment/bank integrations have documented API access, production credentials,
  reconciliation and refund controls;
- migration/opening balances are reconciled to control accounts and the trial
  balance;
- backup restore has been tested;
- refunds, write-offs and year-end close exist;
- departmental sign-off and training are complete.
