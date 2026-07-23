# Harborline Claim Services — Platform

A production-ready Laravel 12 application containing three surfaces:

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
> clearance. Every brand string resolves through `config/branding.php` and the
> administrator Settings screen — the product can be renamed without a rebuild.
> See [COMPLIANCE-PLACEHOLDERS.md](COMPLIANCE-PLACEHOLDERS.md).

## Stack

- Laravel 12 (PHP 8.3+), Blade + Livewire 3 + Alpine.js + Tailwind CSS 4
- PostgreSQL 16 **or** MySQL 8 (selected via `DB_CONNECTION`); SQLite for local/test
- Laravel queues (database driver by default; Redis + Horizon optional)
- Laravel Scheduler (daily scans, retention, automation ticks)
- spatie/laravel-permission (roles), maatwebsite/excel (XLSX), barryvdh/laravel-dompdf (PDF), pragmarx/google2fa (TOTP MFA)
- Pest for tests

## Quick start (local development)

```bash
composer install
cp .env.example .env
# For local dev, set: APP_ENV=local, APP_DEBUG=true, DB_CONNECTION=sqlite
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed        # seeds roles, stages, templates, automations + demo data
npm ci && npm run build           # or npm run dev
php artisan serve
```

Demo logins (fictional data, local/testing only):

| Role | Email | Password |
|---|---|---|
| Super Administrator | `admin@example.test` | `demo-admin-password-123` |
| Case Manager | `manager@example.test` | `demo-manager-password-123` |
| Researcher | `researcher@example.test` | `demo-researcher-password-123` |
| Client | `client@example.test` | `demo-client-password-123` |

Staff users are forced through TOTP MFA enrollment on first portal visit.

## Key concepts

- **Case numbers** — `HCS-{YEAR}-{STATE}-{COUNTY}-{SEQ:6}` (e.g.
  `HCS-2026-MD-AA-000001`), format editable in Administration → Settings.
- **Pipeline** — 42 seeded stages, each mapping to one of the client-safe
  status labels. Stages are editable in Administration → Pipeline Stages.
- **Automation engine** — triggers → conditions → actions with draft/test/
  approval/active modes, idempotency, rate limits, loop prevention, per-case
  pause, and a global emergency stop. See [AUTOMATIONS.md](AUTOMATIONS.md).
- **Outreach gate** — every automated outbound message must pass template
  approval, consent, opt-out, verification-level, frequency, and hold checks
  (`app/Services/OutreachGate.php`).
- **Audit trail** — append-only `audit_events` written for record changes,
  document views/downloads, exports, logins, consent changes, and more.
- **Print & Export Center** — 16+ report definitions in
  `app/Services/ReportRegistry.php`, exported as print-preview HTML, CSV,
  formatted XLSX, or PDF.

## Documentation

| File | Purpose |
|---|---|
| [README-PLESK.md](README-PLESK.md) | Full Plesk deployment guide |
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
./vendor/bin/pest
```

Covers authentication + MFA gating, permissions, case numbering, stage
changes, lead intake + consent capture, duplicate detection, the automation
engine (all safety modes), the outreach gate, client-portal isolation,
document permissions, exports, audit logging, scheduled scans, and retention
(including legal holds).
