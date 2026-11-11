# Local setup on Windows

These steps match the build machine used for this implementation (Windows 10,
PHP 8.4 in `C:\php84`, PostgreSQL 16 on port 5433, pnpm). Adjust paths if your
installation differs.

## 1. Prerequisites

- PHP **8.3+** (8.4 verified) with `pdo_pgsql`, `pgsql`, `intl`, `bcmath`,
  `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `zip`,
  `gd`.
- Composer 2.
- PostgreSQL 16 (PostGIS optional; not used here).
- Node 20+ and pnpm 10+.
- Git.
- Optional: Flutter SDK for the mobile app.

The XAMPP PHP 8.2 shipped on the build machine is **too old** for Laravel 13;
use PHP 8.3/8.4 for Composer and Artisan. This repository was built with:

```powershell
C:\php84\php.exe 'C:\ProgramData\ComposerSetup\bin\composer.phar' --version
```

## 2. Create databases

```powershell
$psql = 'C:\Program Files\PostgreSQL\16\bin\psql.exe'
& $psql -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE manicerp_central"
# The demo command creates manicerp_demo itself.
```

The local `pg_hba.conf` uses trust authentication for localhost. Never do that on
a shared or production machine.

## 3. Configure the backend

```powershell
cd backend
copy .env.example .env
C:\php84\php.exe artisan key:generate
```

Check the database block in `.env`: `DB_HOST=127.0.0.1`, `DB_PORT=5433`,
`DB_DATABASE=manicerp_central`, `DB_USERNAME=postgres`, empty password. The
`TENANT_DB_*` variables are only a connection template; the database name for a
council always comes from the council directory.

## 3a. Isolated acceptance environment

Do not use the operational `manicerp_central` or existing `manicerp_demo`
database for acceptance. With a local-only `.env` (`APP_ENV=local` and a
loopback `DB_HOST`), run:

```powershell
cd C:\Users\User\Desktop\ManicERP\backend
.\bin\New-ManicErpAcceptance.ps1
```

The runner checks the exact database names and local settings, creates
`manicerp_acceptance_central` only if absent, applies pending central migrations,
and provisions/reuses `ACCEPTANCE_DEMO` on `manicerp_acceptance_demo`. It never
runs `migrate:fresh`, drops a database or overwrites existing user credentials.
New synthetic staff credentials are saved only when new actors are created, in
`backend/storage/app/acceptance/` with an ACL for the current Windows user and
SYSTEM. The JSON files are git-ignored. Privileged staff must enroll MFA through
the ordinary setup and challenge screens.

The local mail transport is `log`; verification links and OTP codes in
`backend/storage/logs/laravel.log` are credentials. Do not paste them into source
control, screenshots or reports. Keep this file private and remove it using
normal local credential-retention procedures when acceptance is complete.

To serve only the isolated acceptance central database, in the same PowerShell
session set these variables before starting Laravel:

```powershell
$env:DB_DATABASE = 'manicerp_acceptance_central'
$env:APP_URL = 'http://127.0.0.1:8001'
$env:MAIL_MAILER = 'log'
C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8001
```

The browser setup is synthetic and sandbox-only. If a seed fails, inspect and
repair the existing acceptance data; do not reset or delete the database to
make a rerun pass.

## 4. Install and migrate

```powershell
C:\php84\php.exe 'C:\ProgramData\ComposerSetup\bin\composer.phar' install
C:\php84\php.exe artisan migrate --path=database/migrations/central --force
C:\php84\php.exe artisan manicerp:demo
```

`manicerp:demo` is idempotent. It provisions the DEMONSTRATION council, its
database, roles, users, chart, parties, properties, meters, tariffs, readings,
bills, receipts and a sandbox payment, and prints the demonstration passwords.

Other useful commands:

```powershell
C:\php84\php.exe artisan council:provision HARARE --name="Harare City Council" --domain=harare.example.org
C:\php84\php.exe artisan council:migrate
C:\php84\php.exe artisan accounting:seed-standards --council=DEMO
C:\php84\php.exe artisan audit:verify
```

The licensing migration is additive and is included in `council:migrate`. The
module manifest makes it available, but a council must enable the `licensing`
module and assign the relevant permissions before staff or citizen routes are
usable. `Seed demonstration type` is restricted to a council explicitly marked
`is_demo`; the seeded configuration says **DEMONSTRATION ONLY** and does not
assert statutory fees, deadlines or legal requirements. Production licence
types need a recorded council authority, effective dates and independent
approval before they appear in the citizen catalogue.

## 5. Frontend

```powershell
cd backend
pnpm install
pnpm run build
```

For live reload, run `pnpm dev` in one terminal and
`C:\php84\php.exe artisan serve` in another.

## 6. Run

```powershell
C:\php84\php.exe artisan serve
```

Open <http://127.0.0.1:8000>. Demo sign-ins (password `Demo!Pass2026` unless
overridden):

- `admin@demo.manicerp.test` — council administrator (MFA enrolment required on first use)
- `finance@demo.manicerp.test` — finance officer
- `approver@demo.manicerp.test` — finance approver
- `clerk@demo.manicerp.test` — revenue clerk
- `cashier@demo.manicerp.test` — cashier
- `auditor@demo.manicerp.test` — auditor
- `citizen@example.com` — citizen portal

The privileged demo roles require TOTP. On first sign-in, add the displayed
secret to an authenticator and confirm a code. If you only need the citizen
portal, sign in as `citizen@example.com`.

## 7. Tests

```powershell
cd backend
C:\php84\php.exe artisan test
```

The test bootstrap recreates `manicerp_test_central`, `manicerp_test_demo` and
`manicerp_test_demo_b` once per process and rolls each test back. It needs the
PostgreSQL superuser to create databases (the configured user does).

## 8. Mobile

```powershell
cd mobile
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

The licensing screen uses the same versioned `/api/v1/licensing` endpoints as
other mobile services. Offline drafts are stored on-device and remain clearly
marked unsent until the server accepts submission. `file_picker` handles
evidence selection and certificate saving; `flutter pub get` resolves the
added dependency before building.

If Flutter is not installed, `flutter` commands fail; that is expected and means
mobile builds are unverified, not that the source is absent.

## 9. Common issues on Windows

- **Laravel says `bootstrap/cache` is not writable** even though files can be
  created: a restrictive inherited ACL can make PHP's writability check fail.
  Fix the folder permissions for the project directory, then run
  `php artisan package:discover`.
- **`Class "..." not found` after adding a Composer package**: Laravel's package
  manifest is cached. Run `composer dump-autoload` and
  `php artisan package:discover`, or delete `bootstrap/cache/packages.php`.
- **pnpm link errors** (`Cannot find package` for a transitive dependency): this
  build uses `.npmrc` with `node-linker=hoisted`; remove `node_modules` and
  `pnpm-lock.yaml`, then `pnpm install`.
- **Vite build fails in a restricted sandbox** while spawning a child process:
  run the build in a normal terminal. In this environment the frontend build was
  run outside the confined token.
- **PostgreSQL connection refused**: the local cluster listens on 5433, not
  5432. Check `postmaster.pid` and `postgresql.conf`.
- **`php artisan test` cannot open phpunit**: run it from the `backend/`
  directory.
- Database migrations run with `--database=tenant` use
  `TENANT_DB_DATABASE`/`manicerp_demo`; normally use `council:migrate` instead so
  the correct council database is selected.
