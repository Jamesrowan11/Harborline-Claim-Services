# Harborline Claim Services — Platform (Node.js)

A production-ready **Node.js** application containing three surfaces:

1. **Public website** — a warm, traditional, accessibility-first site explaining
   possible surplus funds, with a secure inquiry form, letter verification, and
   secure document upload.
2. **Internal case-management portal** — leads, cases, a 42-stage configurable
   pipeline, tasks, calendar, documents, communications, an automation engine
   with safety controls, reporting, and a Print & Export Center (CSV/XLSX/PDF).
3. **Client portal** — simplified case status, secure uploads, messaging,
   electronic agreement signing, and communication preferences.

> **Provisional name.** "Harborline Claim Services" is a working name pending
> entity-name, trademark, domain, social-handle, and licensing-language
> clearance. Every brand string resolves through `src/config.js` and the
> administrator Settings screen — the product can be renamed without a rebuild.
> See [COMPLIANCE-PLACEHOLDERS.md](COMPLIANCE-PLACEHOLDERS.md).

## Stack

- **Node.js 20+**, Express 4, EJS server-rendered views, Tailwind CSS 4
- **Knex.js** — PostgreSQL 16 / MySQL 8 selected via env (`DB_CONNECTION`); SQLite for dev/test
- express-session (DB-backed), bcryptjs, **otplib TOTP MFA** (mandatory for staff)
- **exceljs** (formatted XLSX), **pdfkit** (PDF), nodemailer (email), node-cron (in-process scheduler)
- helmet (CSP/security headers), rate limiting, CSRF protection
- **Vitest + Supertest** for tests

Built for **Plesk Node.js hosting**: the Application Startup File is
**`server.js`**, which runs the web server, job-queue worker, and scheduler in
one process — no external cron or workers.

## Quick start (local development)

```bash
npm install
cp .env.example .env
# For local dev set: NODE_ENV=development, DB_CONNECTION=sqlite
node src/cli.js key:generate      # paste output into .env as APP_KEY
node src/cli.js migrate
node src/cli.js seed:demo         # roles, stages, templates, automations + FICTIONAL demo data
npm run build:css
npm start                         # http://localhost:3000
```

Demo logins (fictional data, never for production):

| Role | Email | Password |
|---|---|---|
| Super Administrator | `admin@example.test` | `demo-super-administrator-password-123` |
| Case Manager | `manager@example.test` | `demo-case-manager-password-123` |
| Researcher | `researcher@example.test` | `demo-researcher-password-123` |
| Client | `client@example.test` | `demo-client-password-123` |

Staff users are forced through TOTP MFA enrollment on first portal visit.

## Key concepts

- **Case numbers** — `HCS-{YEAR}-{STATE}-{COUNTY}-{SEQ:6}` (e.g.
  `HCS-2026-MD-AA-000001`); format editable in Administration → Settings.
- **Pipeline** — 42 seeded stages, each mapped to a client-safe status label;
  editable in Administration → Pipeline Stages.
- **Automation engine** — triggers → conditions → actions with draft/test/
  approval/active modes, idempotency, rate limits, loop prevention, per-case
  pause, and a global emergency stop. See [AUTOMATIONS.md](AUTOMATIONS.md).
- **Outreach gate** — every automated outbound message must pass template
  approval, consent, opt-out, verification-level, frequency, and hold checks
  (`src/services/outreachGate.js`).
- **Audit trail** — append-only `audit_events` for record changes, document
  downloads, exports, logins, consent changes, and more; sensitive fields
  masked.
- **Print & Export Center** — 16 report definitions in
  `src/services/reports.js`, exported as print-preview HTML, CSV, formatted
  XLSX, or PDF.

## CLI

```bash
node src/cli.js migrate | rollback | seed | seed:demo
node src/cli.js scans                  # run the daily scans manually
node src/cli.js retention [--dry-run]  # apply retention policies
node src/cli.js key:generate
node src/cli.js create-admin <email> <name> <password>
```

## Documentation

| File | Purpose |
|---|---|
| [README-PLESK.md](README-PLESK.md) | Full Plesk Node.js deployment guide |
| [DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md) | Go-live checklist |
| [SECURITY.md](SECURITY.md) | Security architecture and hardening |
| [BACKUP-RESTORE.md](BACKUP-RESTORE.md) | Backup and restore procedures |
| [AUTOMATIONS.md](AUTOMATIONS.md) | Automation engine reference |
| [COMPLIANCE-PLACEHOLDERS.md](COMPLIANCE-PLACEHOLDERS.md) | Items requiring attorney review |
| [docs/ADMIN-MANUAL.md](docs/ADMIN-MANUAL.md) | Administrator manual |
| [docs/EMPLOYEE-MANUAL.md](docs/EMPLOYEE-MANUAL.md) | Employee manual |
| [docs/CLIENT-PORTAL-GUIDE.md](docs/CLIENT-PORTAL-GUIDE.md) | Client portal help guide |

## Tests

```bash
npm test
```

49 tests cover authentication + MFA gating, permissions, case numbering,
lead intake + consent capture, duplicate detection, lead→case conversion,
stage changes, the automation engine (all safety modes), the outreach gate,
client-portal isolation, document permissions, exports (CSV/XLSX/PDF),
letter verification, audit masking, scheduled scans, and retention with
legal holds.
