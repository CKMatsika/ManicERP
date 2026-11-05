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
- staff: `/dashboard`, `/revenue/*`, `/accounting/*`, `/admin/*`,
  `/ai/assistant`, `/documents/{id}`
- portal: `/portal`, `/portal/accounts/{id}`,
  `/portal/accounts/{id}/statement` (PDF), `/portal/pay*`, `/portal/link*`,
  `/portal/requests`

All web routes are CSRF-protected and permission/module-checked server-side.

## Versioning

The API prefix is `/api/v1`. Breaking changes require a new prefix; additive
fields are allowed. Mobile clients pin the prefix and must handle unknown fields.
