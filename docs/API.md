# API contract (v1)

Base URL: `https://<council-host>/api/v1` (the app also accepts the API on the
main host; council resolution uses the token ability, not the URL).
All responses are JSON. Timestamps are ISO-8601 UTC; amounts are integer minor
units plus a currency code.

## Authentication

```
POST /api/v1/auth/login
{
  "email": "citizen@example.com",
  "password": "...",
  "council_code": "DEMO",
  "device_name": "Pixel 8"
}
```

Response `200`:

```json
{
  "token": "1|....",
  "token_type": "Bearer",
  "expires_at": "2026-12-05T10:00:00+00:00",
  "user": { "id": "01...", "name": "...", "email": "..." },
  "council": { "code": "DEMO", "name": "...", "currency": "USD", "timezone": "Africa/Harare" },
  "membership": { "type": "citizen", "role": "citizen", "permissions": ["..."] },
  "abilities": ["council:DEMO", "portal"]
}
```

Send `Authorization: Bearer <token>` on every subsequent request. Tokens are
scoped to one council and one membership type and expire after 30 days.

`POST /api/v1/auth/logout` revokes the current token.
`GET /api/v1/me` returns the current user, council and membership.

There is no public API self-registration endpoint in v1. The browser provides
`GET /register` and rate-limited `POST /register`. A registrant selects an
active council, supplies a strong password and verifies their email through a
signed, expiring link (`GET /register/verify` displays confirmation;
`POST /register/verify` activates the identity). This creates only a citizen
membership for the selected council. It never creates a portal account link;
citizens must separately request a link verified against the recorded contact
method. Duplicate addresses receive a generic response and existing active
identities are not changed. Local acceptance uses the clearly labelled Laravel
log mail transport; production delivery still requires a configured, verified
mail adapter.

Errors: `401` unauthenticated/expired token; `403` no valid membership for the
token's council; `404` for an account the user is not linked to; `422`
validation or business-rule failure; `429` throttled.

## Citizen endpoints (all require a citizen-scoped token)

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/accounts` | Linked accounts with outstanding balance |
| GET | `/accounts/{id}` | Account summary (404 if not linked) |
| GET | `/accounts/{id}/bills` | Paginated bills with calculation explanation |
| GET | `/accounts/{id}/payments` | Verified payments and receipts |
| GET | `/payments` | Verified payments across all linked accounts |
| POST | `/payments/intents` | Create a payment intent |
| GET | `/service-requests` | Requests submitted by or for this user |
| POST | `/service-requests` | Create a request |
| POST | `/account-links` | Request a link (code sent to verified contact) |
| POST | `/account-links/{id}/verify` | Confirm the one-time code |
| DELETE | `/account-links/{id}` | Revoke a link (reason required in body) |

### Licensing and renewals

These additive endpoints are available under `/api/v1/licensing` to citizen
tokens for councils where the `licensing` module is enabled. All application
access is restricted to verified account links. A council's published type
catalogue may contain expressly labelled demonstration configurations; only an
independently approved, effective council configuration is legal/council
guidance. Configuration snapshots are retained on submission.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/licensing/types` | Effective published type catalogue, current version, fields, documents, schedule and charges |
| GET | `/licensing/guidance?q=...` | Permission-gated assistant answer and citations from approved, effective sources; may return no answer |
| GET | `/licensing/applications` | Applications, staged charge balances/recognition states, inspections, certificate versions and linked account refund statuses |
| POST | `/licensing/drafts` | Save a server-side draft (account, licence type, optional application/licence ID and form) |
| POST | `/licensing/applications` | Submit form using a required `idempotency_key`; required evidence must already be uploaded |
| POST | `/licensing/applications/{application}/documents` | Multipart upload (`account_id`, `document_type`, `file`); 10 MB limit; returns version and SHA-256 |
| POST | `/licensing/applications/{application}/respond` | Respond to a council information request with account ID and form |
| POST | `/licensing/applications/{application}/payment` | Create a pending shared payment intent (`idempotency_key`, optional `charge_id`); does not confirm settlement |
| POST | `/licensing/applications/{application}/refunds` | Request a refund with `payment_id`, amount, note, idempotency key and optional `charge_id`, after configured lifecycle eligibility, charge credit note and allocation reversal |
| GET | `/licensing/notifications` | List only the signed-in user's in-app licensing notifications |
| POST | `/licensing/notifications/{notification}/read` | Mark only the signed-in user's notification as read |
| GET | `/licensing/certificates/{certificate}` | Certificate metadata for a linked holder |
| GET | `/licensing/certificates/{certificate}/download` | Authenticated council-branded PDF with QR verification link |

Submission body:

```json
{
  "account_id": "01...",
  "licence_type": "DEMO_TRADER",
  "application_id": "01...",
  "idempotency_key": "device-generated-unique-value",
  "form": { "trading_name": "Example", "activity": "Retail" }
}
```

Every charge is linked to its submitted configuration version, billing journal,
trigger, payment prerequisite, recognition method and refund rule. Payment
intent requests can name a staged charge; partial payment and allocation remain
owned by the shared revenue services. Only server-verified settlement and
allocation reduce a charge balance. Approval and issuance remain staff actions.
Deferred revenue is released only after its approved trigger and evidence, with
a balanced linked journal and unique event key. Refund settlement remains in
the shared sandbox refund workflow. Public QR verification uses the browser route
`/councils/{councilCode}/licences/verify/{opaqueId}` and returns minimal
authenticity/current-validity information without caching.

Notification external channels are separate adapter rows. Email, SMS and push
remain `unavailable` while their adapters are not configured; they are never
reported as delivered. In-app notices are delivered synchronously and the API
never exposes another user's inbox rows.

The Flutter source stores its offline draft locally with `sent:false`; it is
not an application until successfully synchronised. A payment redirect is
never treated as proof of settlement.

### Payment intent

```json
POST /api/v1/payments/intents
{
  "account_id": "01...",
  "bill_id": "01...",
  "idempotency_key": "device-generated-unique-value"
}
```

Response `201`:

```json
{
  "data": {
    "id": "01...",
    "reference": "PAY-01...",
    "amount_minor": 2950,
    "currency": "USD",
    "status": "pending",
    "checkout": {
      "gateway": "sandbox",
      "sandbox": true,
      "message": "Sandbox only...",
      "webhook_endpoint": "/api/v1/webhooks/payments/sandbox"
    }
  }
}
```

**The intent is not a payment.** A receipt is created only after the server
verifies settlement. Clients must poll bills/payments or receive a notification;
they must never treat a browser/app return as confirmation.

## Payment webhook (server-to-server only)

```
POST /api/v1/webhooks/payments/sandbox
X-Sandbox-Timestamp: 1760000000
X-Sandbox-Signature: <hex hmac-sha256 of "{timestamp}.{rawBody}">
Content-Type: application/json

{
  "reference": "PAY-01...",
  "provider_reference": "SBX-...",
  "status": "succeeded",          // succeeded | failed | cancelled
  "amount_minor": 2950,
  "nonce": "unique-per-event"
}
```

Responses: `200` processed (idempotent for a repeated reference/nonce replay is
rejected with `422`); `422` bad signature, expired timestamp, replayed nonce,
unknown status or amount mismatch; `404` unknown reference.

## Health

`GET /api/v1/health` → `{ "status": "ok", "time": "..." }` (no auth).

## Pagination

Paginated endpoints return Laravel paginator JSON (`data`, `links`,
`current_page`, `last_page`, `per_page`, `total`). Query parameters:
`page`, `per_page` (where implemented).

## Web (browser) routes

The staff ERP and citizen portal are Inertia pages, not JSON:

- `/login`, `/logout`, `/home`, `/councils`, `/security/mfa/*`
- staff: `/dashboard`, `/revenue/*`, `/accounting/*`, `/procurement`,
  `/procurement/actions`, `/admin/*`,
  `/ai/assistant`, `/documents/{id}`
- portal: `/portal`, `/portal/accounts/{id}`,
  `/portal/accounts/{id}/statement` (PDF), `/portal/pay*`, `/portal/link*`,
  `/portal/requests`

All web routes are CSRF-protected and permission/module-checked server-side.
Procurement actions use separate scoped permissions for supplier/bank approval,
budget changes, sourcing awards, invoice exceptions and payment approval. Its
supplier-bank and supplier-settlement records are synthetic acceptance
functionality; no public supplier API or real-bank payment API is enabled.

The staff-only procurement workbench is available at `/procurement`; POST
`/procurement/actions` accepts the allow-listed supplier, budget, requisition,
award, order-amendment/closure/reopening, receipt, invoice, credit and payment
operations documented by the form controls. These are session-authenticated
Inertia form actions, not JSON endpoints. PO amendments are versioned and
require independent approval; cancellations apply only to outstanding
unreceived/uninvoiced quantity; closure records fulfilled, partially-cancelled
or administrative status and can leave AP unpaid. Reopening has a separate
reasoned request and independent approval.

Private procurement documents use authenticated staff routes:

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/procurement/documents/{type}/{record}` | Multipart upload or replacement for `supplier_evidence`, `quotation`, `purchase_order`, `receiving_evidence` or `invoice`; maximum 10 MB, allow-listed file types; replacement requires a reason |
| GET | `/procurement/documents/{document}/preview` | Inline PDF/image preview with `no-store` response headers |
| GET | `/procurement/documents/{document}/download` | Authorized private download |
| POST | `/procurement/documents/{record}/retention` | Update retention date with a reason; files are not automatically purged |

Storage keys are generated server-side under the active council prefix. Upload
paths are never accepted from the caller. Reads re-check council, record-area
and document permissions; quotation files require the confidential-document
permission. Replacements retain immutable file/version provenance. Malware
scanning is unavailable and the UI/report marks uploads `not_scanned`.

## Versioning

The API prefix is `/api/v1`. Breaking changes require a new prefix; additive
fields are allowed. Mobile clients pin the prefix and must handle unknown fields.
