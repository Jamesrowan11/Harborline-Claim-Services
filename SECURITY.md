# Security Architecture

## Authentication

- Password hashing via bcrypt (Laravel `hashed` cast), 12-round default.
- **Mandatory TOTP multifactor authentication for all staff users**
  (`MFA_REQUIRED_FOR_STAFF=true`): enrollment is forced on first portal visit,
  the challenge is re-required every session, and 8 single-use recovery codes
  are issued (stored encrypted).
- Login throttling (5 attempts / 5 min per email+IP) and MFA throttling.
- Anti-enumeration password reset (identical response either way), 12+
  character passwords with letters and numbers.
- Login alerts emailed to staff on each sign-in (`LOGIN_ALERTS_ENABLED`).
- Deactivated accounts (`deactivated_at`) cannot log in and are rejected by
  middleware even with a live session.
- Sessions: database driver, encrypted cookies, 30-minute inactivity lifetime,
  secure cookies in production, session regeneration on login, full
  invalidation on logout. Revoke a user's sessions by clearing rows in the
  `sessions` table for that user (Administration → Users → deactivate also
  blocks middleware immediately).

## Authorization

- Role-based permissions (spatie/laravel-permission) — nine seeded roles from
  Super Administrator to read-only Auditor and Client, with ~60 granular
  permissions enforced on every portal route via middleware.
- Per-record policies: `CaseFilePolicy` and `DocumentPolicy` gate every view/
  download. Clients are hard-scoped to cases their claimant record is attached
  to; there is no route that lists other claimants' data.
- Client portal isolation is covered by automated tests
  (`tests/Feature/ClientPortalIsolationTest.php`).

## Web-layer protections

- CSRF protection on all forms (Laravel VerifyCsrfToken).
- XSS: Blade escaping everywhere; the few `{!! !!}` usages render only
  app-generated route links.
- SQL injection: Eloquent/query-builder bindings exclusively.
- Rate limiting on login, MFA, password reset, all public forms, and client
  upload/message endpoints.
- Security headers middleware on every response: CSP
  (`default-src 'self'`, no external origins), `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`,
  and HSTS over HTTPS.
- CAPTCHA/bot protection on public forms: honeypot field by default
  (`CAPTCHA_DRIVER=honeypot`); Turnstile/reCAPTCHA keys can be configured.

## Documents and data

- All uploads go to a **non-public disk** (`storage/app/private-documents`)
  that is outside the web root and has `serve => false`; there are no public
  storage links to client documents. Downloads happen only through
  authenticated, policy-checked, audited routes.
- Strict file-type validation (PDF, JPEG, PNG, HEIC, DOC/DOCX) and size limits.
- Virus-scanning hook (`VIRUS_SCAN_ENABLED` + `VIRUS_SCAN_COMMAND`, ClamAV
  compatible); files flagged `infected` can never be downloaded by anyone.
- SHA-256 hash recorded for every upload.
- Field-level encryption (Laravel encrypted casts) for: claimant date of
  birth, SSN last-four, MFA secrets, MFA recovery codes, webhook secrets.
- Public forms never request SSNs, bank credentials, card data, or full
  government ID — by design and by documented policy.

## Audit trail

Append-only `audit_events` table (no update/delete route or model path
exists). Recorded: record creation/changes/deletion (with masked sensitive
fields), document viewing and downloads, export/spreadsheet creation, login,
failed login, MFA events, password resets, permission/user changes, template
approvals, automation changes, settings changes, consent changes, stop-contact
requests, and letter-verification attempts. Each row captures user, IP, and
user agent.

## Logs and error handling

- `APP_DEBUG=false` in production — stack traces are never shown to visitors.
- Application logs avoid sensitive payloads; audit payloads mask
  `password`, `ssn_last_four`, `date_of_birth`, `mfa_secret`, and tokens.
- Daily log rotation (`LOG_STACK=daily`).

## Backups

Plesk backups should be encrypted (enable "Use password protection" /
encrypted remote storage). See BACKUP-RESTORE.md.

## Reporting a vulnerability

Email the administrator address configured in branding settings with subject
"SECURITY". Do not open public issues for vulnerabilities.
