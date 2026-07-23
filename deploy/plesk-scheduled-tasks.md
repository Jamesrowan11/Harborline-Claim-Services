# Plesk Scheduled Task examples

Add under Tools & Settings -> Scheduled Tasks, running as the subscription's
system user. Replace the path with your vhost path.

## Mandatory

| Cron | Command | Purpose |
|---|---|---|
| `* * * * *` | `php /var/www/vhosts/example.com/httpdocs/artisan schedule:run >> /dev/null 2>&1` | Laravel scheduler (daily scans, retention, automation ticks) |

## If not using a systemd queue worker

| Cron | Command | Purpose |
|---|---|---|
| `* * * * *` | `php /var/www/vhosts/example.com/httpdocs/artisan queue:work --stop-when-empty --max-time=50 --tries=3` | Process queued jobs |

## Optional operational helpers

| Cron | Command | Purpose |
|---|---|---|
| `0 3 * * 0` | `php /var/www/vhosts/example.com/httpdocs/artisan hcs:enforce-retention --dry-run >> storage/logs/retention-dryrun.log` | Weekly retention preview for review |
