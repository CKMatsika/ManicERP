# ManicERP

A modular municipal ERP for Zimbabwean urban councils and rural district
councils, built on Laravel 13, PostgreSQL, React/TypeScript (Inertia) and a
Flutter citizen app. It is designed for later regional expansion, but only the
Zimbabwe configuration is implemented.

> **Status: working demonstration build, not a production implementation.**
> The verified slice covers council provisioning and isolation, authentication
> and MFA, roles and module gating, a tamper-evident audit trail, private
> documents, a versioned chart-of-accounts import, double-entry posting with
> immutable journals and reversals, property/party/account master data,
> effective-dated tariffs, metered and valuation billing, cashier receipts,
> server-verified sandbox payments, allocation, bank reconciliation, a citizen
> portal and a first source-cited AI assistant. Procurement, stores, assets,
> fleet, HR/payroll, land allocation, licensing and full IPSAS disclosures are
> **not implemented** and their modules are marked unavailable.
>
> Read [docs/IMPLEMENTATION_STATUS.md](docs/IMPLEMENTATION_STATUS.md) before
> making any claim about completeness.

## Repository layout

| Path | Contents |
| --- | --- |
| `backend/` | Laravel 13 application, central and per-council migrations, Inertia staff ERP and citizen portal, JSON API, PHPUnit tests |
| `mobile/` | Flutter citizen application source (Android/iOS). Not built or run here: no Flutter SDK is installed |
| `docs/` | Architecture, module inventory, accounting sources, threat model, API contract, setup, deployment, roadmap and honest status |
| `.github/workflows/ci.yml` | PostgreSQL-backed CI for migrations, tests and the frontend build |

The staff web surface is served by Inertia from `backend/resources/js`; that is
the documented web location because Inertia is server-driven. The API lives in
`backend/routes/api.php`. The mobile app is a separate package under `mobile/`.

## Verified environment

These versions were inspected on the build machine, not assumed:

- PHP **8.4.25** (ZTS) with `pdo_pgsql`, `intl`, `bcmath`, `zip`, `gd`, `mbstring`
- Composer **2.10.2**
- Laravel **13.40.0**
- PostgreSQL **16.10** on port **5433** (no PostGIS extension installed)
- Node **20.19.2** and pnpm **10.24.0**
- Git **2.50.0**
- Docker CLI present but the daemon is **not running**
- Flutter/Dart **not installed** — mobile builds are unverified
- Redis **not available** — database cache/queue/sessions are used
- No verified Zimbabwean payment gateway credentials; only the labelled sandbox adapter exists

## Quick start (Windows, PowerShell)

```powershell
# 1. Database (PostgreSQL 16 on port 5433 in this environment)
& 'C:\Program Files\PostgreSQL\16\bin\psql.exe' -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE manicerp_central"
& 'C:\Program Files\PostgreSQL\16\bin\psql.exe' -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE manicerp_demo"

# 2. Backend configuration
cd backend
copy .env.example .env
php artisan key:generate

# 3. Schema, roles, chart, demo council and demonstration data
php artisan migrate --path=database/migrations/central --force
php artisan manicerp:demo

# 4. Build the web assets and run the app
pnpm install
pnpm run build
php artisan serve
```

Open <http://127.0.0.1:8000>. Demonstration sign-ins are printed by
`manicerp:demo`; see [docs/SETUP-WINDOWS.md](docs/SETUP-WINDOWS.md).

For a second council, `php artisan council:provision` is available in the
architecture documentation but no CLI command is exposed yet; the demo command
and the provisioner service are the supported path.

## Tests

```powershell
cd backend
php artisan test
```

Last full run in this environment: **75 tests, 296 assertions, all passing**
(plus the Flutter model tests, which cannot run without a Dart SDK). The suite
covers balanced posting, period locks, reversal linkage, idempotency, chart
validation and leading zeros, separation of duties on billing approval, billing
snapshots and duplicate prevention, signature/replay protection for payment
webhooks, receipt uniqueness, allocation and overpayment, bank matching, portal
linking and revocation, physical council isolation, module dependencies, audit
tamper detection, document authorisation and AI retrieval gating.

## Demonstration credentials

`php artisan manicerp:demo` creates these **DEMONSTRATION** users with the
password printed by the command (default `Demo!Pass2026`):

| Role | Email |
| --- | --- |
| Council administrator | `admin@demo.manicerp.test` |
| Finance officer | `finance@demo.manicerp.test` |
| Finance approver | `approver@demo.manicerp.test` |
| Revenue clerk | `clerk@demo.manicerp.test` |
| Cashier | `cashier@demo.manicerp.test` |
| Auditor | `auditor@demo.manicerp.test` |
| Citizen | `citizen@example.com` |

Change or delete them before any real deployment. The demo council is visibly
labelled **DEMONSTRATION** in the UI, the seeded chart is labelled as a
demonstration chart, and production reporting remains blocked until an official
chart and posting rules are approved.

## What is deliberately not claimed

- No official Ministry of Finance or council chart of accounts was obtained.
  The active chart contains illustrative codes only.
- No IPSAS statement, Zimbabwe Treasury guidance, PAAB guidance or Financial
  Reporting Manual was verified. See
  [docs/ACCOUNTING_SOURCES.md](docs/ACCOUNTING_SOURCES.md) for the register and
  the explicit production blockers.
- No real payment gateway, bank API, mobile-money API or STS prepaid provider is
  integrated. Only signed, replay-protected sandbox flows are implemented.
- No PostGIS: coordinates are stored as decimal latitude/longitude with
  provenance, which is not a legal cadastre.
- No malware scanner is configured; uploaded documents are marked
  `not_scanned` and served only through authorised routes.
- No Android/iOS build, push notification, deep link, store package or offline
  payment confirmation exists.
- AI generation requires a provider key and is disabled by default; only
  retrieval over approved documents is verified.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Modules and activation](docs/MODULES.md)
- [Accounting sources and standards register](docs/ACCOUNTING_SOURCES.md)
- [Roadmap](docs/ROADMAP.md)
- [Implementation status](docs/IMPLEMENTATION_STATUS.md)
- [Threat model](docs/THREAT_MODEL.md)
- [API contract](docs/API.md)
- [Windows setup](docs/SETUP-WINDOWS.md)
- [Deployment and operations](docs/DEPLOYMENT.md)
