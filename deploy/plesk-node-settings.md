# Plesk Node.js panel — settings summary

| Setting | Value |
|---|---|
| Node.js version | 20+ |
| Application Mode | production |
| Application Root | /httpdocs |
| Document Root | /httpdocs/public |
| Application Startup File | server.js |

Environment variables: everything from .env.example that applies
(NODE_ENV, APP_URL, APP_KEY, DB_*, MAIL_*, ...). Alternatively keep a .env
file in the application root.

After each deploy: click "Restart App" (or `touch tmp/restart.txt`).

No cron jobs or external workers are needed — server.js runs the web
server, queue worker, and scheduler in one process.
