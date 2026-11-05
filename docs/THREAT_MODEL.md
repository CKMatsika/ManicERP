# Threat model

## 1. Assets

- Council operational data in separate PostgreSQL databases (billing, debtors,
  payments, journals, properties, personal contact data, documents).
- Central identity, memberships, roles, sessions and API tokens.
- Private documents and their provenance hashes.
- The audit hash chain.
- Provider and payment credentials (none are committed; AI keys are backend-only).
- Integrity of posted financial records and receipts.

## 2. Actors

- Council staff with role-specific duties (revenue, cashier, finance, approver,
  auditor, administrator).
- Citizens with linked accounts.
- Platform/control-plane administrators (not fully implemented as a separate UI).
- Unauthenticated internet users, including attackers.
- Malicious or compromised uploads/documents and retrieved AI text.
- External providers (payment gateway, AI provider, bank, mail, storage).

## 3. Trust boundaries

1. Internet ↔ web/API (TLS terminates at the reverse proxy).
2. Session/token ↔ central identity and membership.
3. Central control plane ↔ tenant database (physical boundary).
4. Browser/app ↔ server business rules (client input is untrusted).
5. Application ↔ provider APIs (server-to-server only).
6. Uploaded/retrieved content ↔ AI prompt (content is data, never instructions).

## 4. Threats and mitigations

| Threat | Mitigation | Residual risk |
| --- | --- | --- |
| Cross-council data access via guessed IDs | One database per council; membership revalidated per request; portal `requireAccount` returns 404; API token carries a council ability; tests cover records, sessions and tokens | A platform-level cross-council tool is not implemented; future BI must be designed with explicit authority |
| Caller-supplied council identifier | Never read from request body/query; resolved from verified host, session membership or token ability | Host header spoofing is prevented only if the proxy passes the real host and domains are verified |
| Privilege escalation via navigation | Every route has server-side auth, member-type, module and `can:` checks; navigation is cosmetic | Missing `can:` on a new route would be a defect; route tests are partial |
| Self-approval / fraud | Billing approval requires a different officer from the preparer; roles separate preparation, approval and cashiering; audit records actors | Journal posting has no second-approver workflow; single `*` administrators can do everything |
| Stolen session or token | Session regeneration on login, secure cookies in production, token expiry and revocation, MFA for privileged roles, login throttling, lockout fields | No device/session management UI; no IP anomaly detection |
| MFA bypass | Middleware blocks privileged staff routes until TOTP/recovery verification, 12-hour freshness, hashed recovery codes consumed once | TOTP secret is encrypted with `APP_KEY`; losing the key loses enrolment |
| Password attacks | Hash (bcrypt cost 12), rate limiter (5/min per email+IP), failed-attempt counters, generic error messages | No account lockout notification or breached-password check |
| CSRF | Laravel CSRF on web routes, SameSite=Lax session cookie | Inertia form posts rely on the framework token; JSON API uses bearer tokens |
| SQL injection | Eloquent/query builder bindings; no raw user SQL; AI has no SQL tool | Raw reporting SQL is builder-based; review any future raw SQL |
| IDOR on files | Documents on a private disk, authorised controller, permission `platform.documents.view`, hashes and classification | No per-record document policy yet: any staff member with the permission can read any council document |
| Malicious upload | MIME + extension allow-list, size limit, private storage, `nosniff`, forced download | **No malware scanner**; uploads are marked `not_scanned` |
| Webhook forgery / replay | HMAC-SHA256 over timestamp + raw body, 5-minute tolerance, nonce replay cache, server-to-server settlement only, idempotent intent/reference handling | Sandbox secret in env; production secret management/rotation not implemented |
| Duplicate payment/receipt | Idempotent settlement, unique provider reference, unique receipt per payment, locked intent | Provider-side duplicate settlement is handled by reference; reconciliation still required |
| Billing double-post | Unique `(source_type, source_id)`, idempotency key, partial unique active-bill index, per-item lock | Manual database edits are out of scope |
| Reporting manipulation | Reports derive from posted lines; posted entries immutable; linked reversals; audit chain | A database superuser can alter data; off-site audit export is not implemented |
| Audit tampering | Hash chain with previous hash, canonical JSON, locked sequence, append-only model guards, `audit:verify` | The chain has no external anchor/notarisation; a full-database rewrite is possible with DBA access |
| AI prompt injection | Retrieved documents and user text treated as untrusted; system prompt restricts to numbered sources; no tools; output labelled assistance; permission and tenant filtering before retrieval | No adversarial evaluation corpus; keyword retrieval can miss or over-select content |
| AI data leakage to provider | Retrieval limited to the current council's approved documents; personal data policy not formally approved; budget and usage recorded | No redaction pipeline before sending retrieved text |
| Sensitive personal data exposure | Personnel/medical modules do not exist; contacts are per party; least-privilege permissions | No field-level encryption; no data-retention automation |
| Denial of service | Login throttle, API rate limiter, queue for heavy work (not used yet) | No per-council quota or WAF; billing preview runs synchronously |
| Supply chain | Composer/pnpm lock files; versions inspected | No dependency scanning in CI yet |
| Backups | Documented procedure | **Not configured or restore-tested here** |

## 5. Security decisions that must be made before production

1. Disable session-based council selection and require verified council hosts.
2. Configure HTTPS, secure/HTTP-only cookies, trusted proxies and HSTS.
3. Move documents to private S3-compatible storage with server-side encryption
   and add malware scanning.
4. Approve a personal-data and AI-provider data policy, including redaction.
5. Add a second approver workflow for manual journals and write-offs.
6. Add per-record document authorisation.
7. Add external anchoring or signed export for audit.
8. Implement, test and document encrypted backups with restore evidence.
