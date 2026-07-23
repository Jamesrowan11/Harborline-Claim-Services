# Deployment Checklist

## Before first deploy
- [ ] Name clearance complete (see COMPLIANCE-PLACEHOLDERS.md) or launch under provisional branding knowingly
- [ ] Attorney review of Privacy Policy, Terms, Disclaimer, consent texts
- [ ] Domain configured in Plesk with Node.js 20+, Application Root `/httpdocs`, Document Root `/httpdocs/public`, Startup File `server.js`
- [ ] Database created (PostgreSQL 16 or MySQL 8)
- [ ] Environment configured (Plesk Node.js panel or `.env`): NODE_ENV=production, APP_URL, DB_*, MAIL_*
- [ ] `node src/cli.js key:generate` run; APP_KEY stored in a secrets manager
- [ ] Let's Encrypt SSL installed; HTTP→HTTPS redirect on
- [ ] `bash deploy/deploy.sh --first-run` completed without errors; app restarted in Plesk
- [ ] Confirmed the app stays running (Passenger production mode) — scheduler and worker live inside it
- [ ] Plesk backups scheduled to remote storage, encrypted
- [ ] First Super Administrator created (`node src/cli.js create-admin`); MFA enrolled; demo credentials absent
- [ ] Demo seed (`seed:demo`) never run in production

## Verify after each deploy
- [ ] `curl -fsS https://example.com/up` → `{"ok":true}`
- [ ] Home page, inquiry form, verify-letter page load
- [ ] Portal login + MFA works
- [ ] A test XLSX export downloads
- [ ] `jobs` table has no `failed` rows
- [ ] Plesk Node.js application log free of new errors

## Before enabling outreach
- [ ] Communication templates reviewed and approved in-app
- [ ] Outreach minimum verification level + frequency limits set
- [ ] SMS remains disabled unless counsel has approved (SMS_ENABLED)
- [ ] Sensitive automations individually reviewed and approved
