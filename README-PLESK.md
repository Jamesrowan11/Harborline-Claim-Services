# Plesk Deployment Guide (Plesk Obsidian, Ubuntu, Nginx)

This application is built to run reliably on an AWS-hosted Ubuntu server with
Plesk Obsidian, Nginx as the reverse proxy (Apache optionally behind it),
Let's Encrypt SSL, Plesk databases, Plesk scheduled tasks, and Plesk backups.
Docker is **not** required.

---

## 1. Required PHP (8.3+) extensions

Enable in **Plesk → Tools & Settings → PHP Settings** (or via
`plesk bin php_handler`), for the PHP 8.3 (or newer) FPM handler:

`bcmath ctype curl dom fileinfo filter gd iconv intl json libxml mbstring
openssl pcre pdo pdo_pgsql (or pdo_mysql) session simplexml tokenizer xml
xmlwriter zip` — plus `redis` if you enable Redis, and `posix pcntl` for
Horizon.

## 2. Create the domain and document root

1. **Plesk → Websites & Domains → Add Domain** (e.g. `example.com`).
2. Set **Hosting Settings → Document root** to `httpdocs/public`
   (you will deploy the repository into `httpdocs`, so `/public` is the only
   web-exposed directory — this is essential; the app root must never be
   web-served).
3. **PHP Settings**: PHP 8.3 FPM served by Nginx; `memory_limit=512M`,
   `upload_max_filesize=25M`, `post_max_size=26M`, `max_execution_time=120`.

## 3. Get the code onto the server

Use Plesk's Git extension (recommended) or SFTP:

- **Plesk → Websites & Domains → Git** → add this repository, deploy to
  `/httpdocs`, deployment mode "Manual" (you will run the deploy script after
  each pull).

## 4. Database setup

**Plesk → Databases → Add Database**:

- PostgreSQL 16: database `harborline`, user `harborline`, strong password.
- Or MySQL 8: the same, then set `DB_CONNECTION=mysql`, `DB_PORT=3306`.

No schema import is needed — migrations create everything.

## 5. Environment variables

```bash
cd /var/www/vhosts/example.com/httpdocs
cp .env.example .env
php artisan key:generate
```

Then edit `.env` (never commit it; it is gitignored):

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://example.com`
- `DB_*` from step 4
- `MAIL_*` — Plesk's local mail service or an external SMTP provider
- `BRAND_*` values (or set them later in Administration → Settings)
- Leave `SMS_ENABLED=false` until counsel approves SMS outreach

If Redis is installed, prefer `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`,
and install Horizon (`composer require laravel/horizon`) for queue monitoring.

## 6. First deployment

```bash
cd /var/www/vhosts/example.com/httpdocs
bash deploy/deploy.sh --first-run
```

The script runs composer/npm builds, migrations, storage link, caches,
permission checks, and a health check (see §14). On the first run it also
seeds roles, pipeline stages, templates, and default (inactive) automations —
without demo data when `APP_ENV=production`.

Create your first administrator:

```bash
php artisan tinker --execute="
\$u = App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => 'CHANGE-ME-NOW-123', 'user_type' => 'staff']);
\$u->assignRole('Super Administrator');
"
```

Sign in, complete MFA enrollment, then immediately change the password via
the reset flow.

## 7. Writable folders

The web server user (`psacln`/subscription system user) must be able to write:

- `storage/` (all subdirectories, including `storage/app/private-documents`)
- `bootstrap/cache/`

`deploy/deploy.sh` verifies and fixes these:
`chmod -R u+rwX,g+rwX storage bootstrap/cache`.

## 8. Queue worker

Queues default to the `database` driver. Two supported options:

**Option A — Plesk Scheduled Task keep-alive (simplest).**
Add a task (see §9) running every minute:

```
php /var/www/vhosts/example.com/httpdocs/artisan queue:work --stop-when-empty --max-time=50 --tries=3
```

**Option B — long-running worker via systemd (recommended for volume).**
Copy `deploy/harborline-queue.service` to `/etc/systemd/system/`, adjust the
path and user, then:

```bash
systemctl daemon-reload && systemctl enable --now harborline-queue
```

With Redis + Horizon, run `php artisan horizon` in the service instead and use
`php artisan horizon:terminate` on deploy.

## 9. Plesk scheduled tasks

**Plesk → Tools & Settings → Scheduled Tasks** (run as the subscription's
system user). One task is mandatory:

| Schedule | Command |
|---|---|
| `* * * * *` | `php /var/www/vhosts/example.com/httpdocs/artisan schedule:run >> /dev/null 2>&1` |

The in-app scheduler then handles: daily scans (06:00), retention enforcement
(02:30), queue pruning, and hourly automation ticks. If you chose queue
Option A, add that task too.

## 10. SSL

**Plesk → Websites & Domains → SSL/TLS Certificates → Install a free Let's
Encrypt certificate**, covering the domain and `www`. Enable:

- "Redirect from HTTP to HTTPS" (Hosting Settings)
- Auto-renewal (default with the Let's Encrypt extension)

The app sends HSTS and secure-cookie headers automatically when serving HTTPS.

## 11. Nginx directives (if needed)

Plesk's default Laravel handling usually suffices because the document root is
`/public`. If you need explicit rules, paste `deploy/nginx-directives.conf`
into **Apache & nginx Settings → Additional nginx directives**.

## 12. Apache directives (if Apache runs behind Nginx)

`public/.htaccess` ships with the application and handles rewrites. If you
manage directives centrally, use `deploy/apache-directives.conf` in
**Apache & nginx Settings → Additional directives for HTTP/HTTPS**.

## 13. Storage link

`php artisan storage:link` (run automatically by the deploy script) links
`public/storage` → `storage/app/public`. Client documents deliberately do
**not** live there — they are stored on the non-public `private` disk
(`storage/app/private-documents`) and are only served through authenticated,
policy-checked download routes.

## 14. Routine deployments, cache and optimization

Every code update:

```bash
cd /var/www/vhosts/example.com/httpdocs
git pull            # or Plesk Git "Pull now"
bash deploy/deploy.sh
```

The script performs: `composer install --no-dev --optimize-autoloader`,
`npm ci`, `npm run build`, `php artisan migrate --force`, storage link,
`config:cache`, `route:cache`, `view:cache`, `event:cache`,
`queue:restart`, permission checks, and a health check against `/up`.

Manual cache commands when needed:

```bash
php artisan optimize:clear   # clear config/route/view/event caches
php artisan optimize         # rebuild them
```

## 15. Backups

Use **Plesk → Backup & Restore Manager** on a daily schedule, storing to
remote storage (S3/FTP), including databases and files. Details and the
what-must-be-backed-up list: [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

## 16. Restore

Full procedure in [BACKUP-RESTORE.md](BACKUP-RESTORE.md). Short version:
restore files + database from the same backup set, re-check `.env`, run
`php artisan optimize:clear && php artisan migrate --force`, restart the queue
worker, verify `/up`.

## 17. Upgrade and rollback

**Upgrade**

1. `php artisan down --retry=60 --secret="maintenance-bypass-TOKEN"`
2. Take an on-demand Plesk backup (files + DB).
3. `git pull && bash deploy/deploy.sh`
4. `php artisan up`

**Rollback**

1. `php artisan down`
2. `git checkout <previous-tag-or-commit>`
3. `bash deploy/deploy.sh --skip-migrations` (never run newer migrations
   backwards; restore the DB from the pre-upgrade backup if a migration must
   be undone)
4. `php artisan up`

Tag every production release (`git tag prod-YYYYMMDD`) so rollback targets
are unambiguous.

## 18. Production troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| 500 with blank page | `APP_KEY` missing → `php artisan key:generate`; check `storage/logs/laravel.log` |
| 403/404 on every page | Document root not pointing at `/public` |
| Styles/JS missing | `npm run build` not run, or `public/build` not deployed |
| "Permission denied" in logs | Re-run §7 permission fixes; confirm PHP-FPM user matches file owner |
| Emails not sending | Check `MAIL_*`; try `php artisan tinker` → `Mail::raw('test', fn ($m) => $m->to('you@example.com')->subject('test'));` |
| Queued jobs never run | `schedule:run` cron missing (§9) or worker dead (§8); check `php artisan queue:failed` |
| Scheduled automations not firing | Same as above — the scheduler cron is mandatory |
| Uploads fail at ~2 MB | Raise `upload_max_filesize`/`post_max_size` in Plesk PHP settings |
| Session logouts after deploy | `SESSION_DRIVER=database` requires the sessions table (migrations); don't cache config with a stale `.env` |
| Health check | `curl -fsS https://example.com/up` returns 200 when the app boots |
| Emergency: stop all automations | Administration → Automation Center → EMERGENCY STOP, or set `AUTOMATION_GLOBAL_STOP=true` in `.env` + `php artisan config:cache` |

Logs: `storage/logs/laravel.log` (daily rotation), Plesk → Logs for
Nginx/PHP-FPM errors. In production `APP_DEBUG` must stay `false` — errors are
masked for visitors and detailed only in logs.
