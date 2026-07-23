# Backup & Restore

## What must be backed up

| Item | Location | Why |
|---|---|---|
| Database | Plesk DB (PostgreSQL/MySQL) | All records, audit trail, consents |
| Private documents | `storage/app/private-documents/` | Client uploads — irreplaceable |
| Public storage | `storage/app/public/` | Logos and public assets |
| Environment file | `.env` | Secrets and configuration (never in git) |
| Logs (optional) | `storage/logs/` | Investigations |

The application code itself lives in git; it does not need file backup beyond
the repository, but including it makes restores simpler.

## Recommended Plesk configuration

**Plesk → Backup & Restore Manager → Schedule**:

- Full backup weekly, incremental daily, at a low-traffic hour.
- Store in remote storage (S3, FTP(S)) — never only on the same server.
- Enable password protection / encryption for backup archives.
- Retention: at least 30 daily + 12 weekly sets (align with your
  administrator-configured retention policies; legal-hold data must remain
  restorable for the life of the hold).

Additionally, an application-level DB dump before every deploy:

```bash
# PostgreSQL
pg_dump -Fc harborline > ~/backups/harborline-$(date +%Y%m%d-%H%M).dump
# MySQL
mysqldump --single-transaction harborline | gzip > ~/backups/harborline-$(date +%Y%m%d-%H%M).sql.gz
```

## Restore procedure

1. Stop the Node.js app in Plesk (Websites & Domains → Node.js → Stop App).
2. **Files**: restore via Plesk Backup Manager (or extract the archive into
   the vhost), confirming `storage/app/private-documents` is intact.
3. **Database**: restore the matching backup:
   ```bash
   # PostgreSQL
   pg_restore -c -d harborline harborline-YYYYMMDD.dump
   # MySQL
   zcat harborline-YYYYMMDD.sql.gz | mysql harborline
   ```
4. Verify `.env` matches the restored environment (APP_KEY especially — the
   original APP_KEY is REQUIRED to read encrypted fields; losing it loses encrypted data).
5. `node src/cli.js migrate`
6. Restart the app in the Plesk Node.js panel (worker and scheduler restart with it).
7. Health check: `curl -fsS https://example.com/up`.
8. Spot-check: log in, open a case, download a document, run a report.
9. Start the app again in the Plesk Node.js panel.

## Disaster recovery notes

- **APP_KEY is part of your backup.** Store `.env` (or at minimum APP_KEY) in
  a secure secrets manager as well as in backups.
- Test a full restore to a staging subscription quarterly.
- After any restore, review the audit log gap and note the restore event in
  an internal record.
