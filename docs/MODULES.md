# Module inventory, manifests and activation

## Manifest contract

Every module is declared in `backend/config/modules.php`:

```php
'accounting' => [
    'name' => 'Accounting and IPSAS reporting',
    'version' => '0.3.0',
    'description' => '...',
    'dependencies' => ['platform.core'],
    'permissions' => ['accounting.chart.view', ...],
    'core' => false,
    'available' => true,
],
```

`core` modules can never be disabled. `available` distinguishes a functioning
module from a declared-but-unimplemented one; unavailable modules are visible in
the module page, cannot be enabled, and their routes are not treated as
available. Permissions listed here are the only permissions
`PermissionService` will grant; an enabled module is a prerequisite for its
permissions.

Activation state lives in central `council_modules` and is enforced by
`ModuleRegistry` and the `module:` route middleware. Jobs and integrations must
also call `ModuleRegistry::ensureEnabled()`; no production tenant job exists yet,
so that rule is documented rather than demonstrated.

## Dependency graph

```
platform.core  (identity, audit, documents, council directory)
  ├── accounting
  │     ├── revenue
  │     │     └── customer
  │     │           └── licensing (unavailable)
  │     ├── procurement (unavailable)
  │     └── assets (unavailable)
  ├── hr (unavailable; depends on accounting)
  └── ai
```

Rules enforced by `ModuleRegistry::setEnabled`:

1. A core module cannot be disabled.
2. A module with at least one enabled dependant cannot be disabled.
3. An unavailable module cannot be enabled.
4. A module cannot be enabled while any dependency is disabled.

## Implemented and verified

| Module | Version | Status | Verified by |
| --- | --- | --- | --- |
| `platform.core` | 1.0.0 | Implemented and tested | Council provisioning, permissions, module tests, audit chain tests, document tests |
| `accounting` | 0.3.0 | Implemented and tested (core accounting, not full IPSAS statements) | Posting, reversal, period, trial balance, chart import and standards tests |
| `revenue` | 0.3.0 | Implemented and tested (rates/water/refuse; rental configured but not exercised) | Billing, payment, cashier, bank reconciliation tests |
| `customer` | 0.2.0 | Implemented and tested (portal linking, bills, payments, statements, requests) | Portal access tests |
| `ai` | 0.1.0 | Partially implemented; retrieval and gating tested, provider generation unverified | Policy assistant tests (disabled/budget/no-source paths) |

## Declared but not implemented

These modules have manifests with `available = false`. They appear in the
module page as **Not implemented**, cannot be enabled, and no route, table or
service pretends to deliver them.

| Module | Planned scope | Dependencies |
| --- | --- | --- |
| `licensing` | Configurable licence types, applications, inspections, issuance, renewal, public verification | customer, revenue |
| `procurement` | Supplier onboarding, requisitions, tenders, purchase orders, receipts, matching, payments | accounting |
| `assets` | Asset/components, depreciation, impairment, revaluation, disposal, maintenance, work orders | accounting |
| `hr` | Establishment, positions, leave, payroll, statutory tables, payslips, GL reconciliation | accounting |

## Adding a module

1. Add the manifest and its permissions to `config/modules.php`.
2. Create `app/Modules/<Name>` with models, services and policies. Controllers
   must not query another module's tables directly.
3. Add tenant migrations under `database/migrations/tenant/` (or central
   migrations if the data is control-plane).
4. Add routes inside a `module:<id>` middleware group and guard each action with
   a permission.
5. Add the module to `DefaultRoles` where appropriate and seed role permissions
   for existing councils (a migration or command, not a silent runtime change).
6. Add tests for permissions, module activation, isolation and the business
   invariants. Update this document and `IMPLEMENTATION_STATUS.md`.
