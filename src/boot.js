/** Real application boot — loaded by server.js so any failure (including
 *  import-time errors) is captured to storage/logs/boot-error.log. */
import cron from 'node-cron';
import db from './db.js';
import { createApp } from './app.js';
import { config } from './config.js';
import { processJobs } from './services/queue.js';
import { runDailyScans } from './services/scans.js';
import { enforceRetention } from './services/retention.js';
import { seedBaseline } from './seeds/baseline.js';
import { bus } from './events.js';

if (config.env === 'production' && !config.appKey) {
  throw new Error(
    'APP_KEY environment variable is not set. Add APP_KEY (any 32+ character random secret) '
    + 'to Custom environment variables in the Plesk Node.js panel, store a copy in a password '
    + 'manager, then Restart App.');
}

const [batch, applied] = await db.migrate.latest();
if (applied.length) console.log(`Migrations applied (batch ${batch}): ${applied.join(', ')}`);
await seedBaseline(db); // idempotent: stages, templates, default automations

const app = createApp();

app.listen(config.port, () => {
  console.log(`${config.brand.name} listening on port ${config.port} (${config.env})`);
});

// Queue worker: poll every 15 seconds.
setInterval(() => processJobs().catch((e) => console.error('queue worker', e)), 15_000);

// Scheduler (times are server-local).
cron.schedule('0 6 * * *', () => runDailyScans().catch((e) => console.error('daily scans', e)));
cron.schedule('30 2 * * *', () => enforceRetention().catch((e) => console.error('retention', e)));
cron.schedule('0 * * * *', () => bus.emitDomain('schedule.tick', {
  context: { idempotency_suffix: `tick-${new Date().toISOString().slice(0, 13)}` },
}).catch((e) => console.error('schedule tick', e)));
