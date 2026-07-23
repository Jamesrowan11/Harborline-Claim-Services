# Deployment Checklist

## Before first deploy
- [ ] Name clearance complete (see COMPLIANCE-PLACEHOLDERS.md) or launch under provisional branding knowingly
- [ ] Attorney review of Privacy Policy, Terms, Disclaimer, consent texts
- [ ] Domain configured in Plesk, document root = `httpdocs/public`
- [ ] PHP 8.3 FPM with required extensions (README-PLESK.md §1)
- [ ] Database created (PostgreSQL 16 or MySQL 8)
- [ ] `.env` configured: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*
- [ ] `php artisan key:generate` run; APP_KEY stored in a secrets manager
- [ ] Let's Encrypt SSL installed; HTTP→HTTPS redirect on
- [ ] `bash deploy/deploy.sh --first-run` completed without errors
- [ ] Scheduler cron added (`schedule:run` every minute)
- [ ] Queue worker running (systemd service or per-minute task)
- [ ] Plesk backups scheduled to remote storage, encrypted
- [ ] First Super Administrator created; MFA enrolled; demo credentials absent
- [ ] `SEED_DEMO_DATA` unset in production

## Verify after each deploy
- [ ] `curl -fsS https://example.com/up` → 200
- [ ] Home page, inquiry form, verify-letter page load
- [ ] Portal login + MFA works
- [ ] A test XLSX export downloads
- [ ] `php artisan queue:failed` is empty
- [ ] `storage/logs/laravel.log` free of new errors

## Before enabling outreach
- [ ] Communication templates reviewed and approved in-app
- [ ] Outreach minimum verification level + frequency limits set
- [ ] SMS remains disabled unless counsel has approved (SMS_ENABLED)
- [ ] Sensitive automations individually reviewed and approved
