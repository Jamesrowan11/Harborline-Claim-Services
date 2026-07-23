# Plesk Deployment Guide — Node.js (Plesk Obsidian, Ubuntu, Nginx)

This application is a **Node.js app** built specifically for Plesk's Node.js
hosting (Phusion Passenger behind nginx). No Docker, no PHP.

---

## 1. Requirements

- Plesk Obsidian with the **Node.js extension** installed
  (Tools & Settings → Updates → Add/Remove Components → Node.js support).
- Node.js **20 or newer** selected for the domain.
- PostgreSQL 16 **or** MySQL 8 available in Plesk.

## 2. Create the domain and enable Node.js

1. **Websites & Domains → Add Domain** (e.g. `example.com`).
2. Open the domain → **Node.js** and set:

| Setting | Value |
|---|---|
| Node.js version | 20+ (latest available) |
| Document Root | `/httpdocs/public` |
| Application Mode | `production` |
| **Application Root** | `/httpdocs` |
| **Application Startup File** | **`server.js`** |

`server.js` is the application startup file — it starts the web server, the
in-app scheduler (daily scans, retention, hourly automation tick), and the
job-queue worker in one process. **No separate cron jobs or workers are
required** — this is all built in.

The Document Root points at `public/` so only static assets are ever served
directly; everything else flows through the app.

## 3. Get the code onto the server

Use Plesk's **Git** extension (recommended): add this repository, deploy to
`/httpdocs`, deployment mode "Manual", and run the deploy steps (§6) after
each pull. SFTP upload works too.

## 4. Database setup

**Plesk → Databases → Add Database**:

- PostgreSQL 16: database `harborline`, user `harborline`, strong password.
- Or MySQL 8: the same, then set `DB_CONNECTION=mysql`, `DB_PORT=3306`.

No schema import is needed — migrations create everything.

## 5. Environment variables

Two options (both work; the Plesk UI is often easier):

**Option A — Plesk UI:** Domain → Node.js → **Environment Variables**. Add
every variable from `.env.example` you need (at minimum: `NODE_ENV`,
`APP_URL`, `APP_KEY`, `DB_*`, `MAIL_*`).

**Option B — `.env` file** in the application root (the app loads it via
dotenv; it is gitignored):

```bash
cd /var/www/vhosts/example.com/httpdocs
cp .env.example .env
node src/cli.js key:generate   # paste the output into .env as APP_KEY
```

Key values:

- `NODE_ENV=production`, `APP_URL=https://example.com`
- `APP_KEY` — required; encrypts MFA secrets and sensitive fields. Store a
  copy in a password manager: losing it loses encrypted data.
- `DB_*` from step 4
- `MAIL_*` — Plesk's local mail service, your email provider's SMTP, or a
  transactional service (Amazon SES recommended for deliverability)
- Leave `SMS_ENABLED=false` until counsel approves SMS outreach

## 6. First deployment

SSH into the server (or use Plesk's "Run script" in the Node.js panel):

```bash
cd /var/www/vhosts/example.com/httpdocs
npm ci --omit=dev                # install production dependencies
npm run build:css                # compile the stylesheet (needs devDeps: use `npm ci` without --omit=dev the first time, or build locally and commit nothing — see note)
node src/cli.js migrate          # create all tables
node src/cli.js seed             # roles, pipeline stages, templates, default automations
node src/cli.js create-admin you@example.com "Your Name" "a-strong-temporary-password"
```

> **CSS note:** `npm run build:css` requires dev dependencies. Either run
> plain `npm ci` (installs both) or build `public/assets/app.css` on your
> machine and upload it. The deploy script (§7) handles this automatically.

Then in Plesk → Node.js click **Restart App**. Sign in, complete MFA
enrollment, and change your password via the reset flow.

Never seed demo data (`seed:demo`) in production.

## 7. Routine deployments

```bash
cd /var/www/vhosts/example.com/httpdocs
git pull                         # or Plesk Git "Pull now"
bash deploy/deploy.sh            # npm ci, css build, migrate, permission checks
```

Then **Restart App** in the Plesk Node.js panel (or `touch tmp/restart.txt`,
which Passenger watches). The deploy script performs: dependency install,
CSS build, database migrations, storage-folder checks, and a health check.

## 8. Scheduler and queue worker

Nothing to configure. `server.js` runs them in-process:

- **Queue worker** — polls the jobs table every 15 seconds (outbound email/SMS deliveries, 3 retries with backoff).
- **Daily scans** — 06:00 server time (deadlines, overdue tasks, stale sources, inactivity, expired verifications).
- **Retention enforcement** — 02:30 daily (never touches legal holds).
- **Automation tick** — hourly `schedule.tick` event for scheduled automations.

If you prefer external control, you can also run these manually:
`node src/cli.js scans` and `node src/cli.js retention [--dry-run]`.

## 9. SSL

**Websites & Domains → SSL/TLS Certificates → Install a free Let's Encrypt
certificate** covering the domain and `www`. Enable "Redirect from HTTP to
HTTPS". The app sends HSTS and secure-cookie headers automatically when
`NODE_ENV=production`.

## 10. Writable folders

The subscription's system user must be able to write:

- `storage/` (private documents, logs, SQLite in dev)
- `.env` (if using Option B)

`deploy/deploy.sh` verifies these. Client documents live in
`storage/app/private-documents/` — outside the document root, never
web-served, downloadable only through authenticated, policy-checked routes.

## 11. Nginx directives (optional)

Plesk's Node.js hosting proxies everything automatically. If you want static
asset caching, paste `deploy/nginx-directives.conf` into **Apache & nginx
Settings → Additional nginx directives**.

## 12. Backups

**Plesk → Backup & Restore Manager**: daily schedule, remote storage
(S3/FTP), password-protected archives, including databases and files.
Details: [BACKUP-RESTORE.md](BACKUP-RESTORE.md). `APP_KEY` (from `.env` or
the Plesk environment-variable panel) is part of your backup — store it in a
secrets manager too.

## 13. Restore

Short version (full procedure in BACKUP-RESTORE.md): restore files + DB from
the same backup set, confirm `.env`/environment variables (especially
`APP_KEY`), run `node src/cli.js migrate`, Restart App, check `/up`.

## 14. Upgrade and rollback

**Upgrade:** take an on-demand backup → `git pull` →
`bash deploy/deploy.sh` → Restart App → verify `/up` and a test login.

**Rollback:** `git checkout <previous-tag>` →
`bash deploy/deploy.sh --skip-migrations` → Restart App. Never run newer
migrations backwards; restore the DB from the pre-upgrade backup if a
migration must be undone. Tag every release: `git tag prod-YYYYMMDD`.

## 15. Production troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| App won't start / 503 | Check Plesk → Node.js → the app log (Passenger shows startup errors); usually a missing env var (`APP_KEY`) or DB connection failure |
| "APP_KEY is not set" | Generate with `node src/cli.js key:generate`, set it, Restart App |
| Styles missing | `public/assets/app.css` not built — run `npm run build:css` |
| DB connection refused | `DB_*` values wrong, or the DB user lacks access from localhost |
| Emails not sending | Check `MAIL_*`; with `MAIL_MAILER=log`, mail goes to `storage/logs/mail.log` (useful for testing) |
| Queued jobs stuck | Check the `jobs` table for `failed` rows and `last_error`; the worker runs inside the app process, so a stopped app = stopped worker |
| Scheduled tasks not firing | Same — they run inside `server.js`; make sure the app has been running continuously (Passenger may idle-stop apps: set "Application Mode: production" and consider Passenger's min instances) |
| Uploads fail | File type not in the allowed list (PDF/JPG/PNG/HEIC/DOC/DOCX) or over `MAX_UPLOAD_MB` |
| Health check | `curl -fsS https://example.com/up` returns `{"ok":true}` |
| Emergency: stop all automations | Portal → Automation Center → EMERGENCY STOP, or set env `AUTOMATION_GLOBAL_STOP=true` + Restart App |

Logs: Plesk → Node.js panel shows the application log;
`storage/logs/mail.log` and `sms.log` capture log-driver deliveries. In
production, error details are never shown to visitors — only logged.
