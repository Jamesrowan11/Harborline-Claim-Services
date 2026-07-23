/**
 * Application startup file — set this as the "Application Startup File" in
 * Plesk's Node.js settings. On boot it migrates the database and seeds
 * baseline data automatically (both idempotent), then starts the web server,
 * the in-process scheduler, and the job-queue worker. Everything is
 * manageable from the Plesk panel: NPM install -> Restart App -> visit
 * /setup in the browser to create the first administrator.
 */
import cron from 'node-cron';
import db from './src/db.js';
import { createApp } from './src/app.js';
import { config } from './src/config.js';
import { processJobs } from './src/services/queue.js';
import { runDailyScans } from './src/services/scans.js';
import { enforceRetention } from './src/services/retention.js';
import { seedBaseline } from './src/seeds/baseline.js';
import { bus } from './src/events.js';

try {
  const [batch, applied] = await db.migrate.latest();
  if (applied.length) console.log(`Migrations applied (batch ${batch}): ${applied.join(', ')}`);
  await seedBaseline(db); // idempotent: stages, templates, default automations
} catch (error) {
  console.error('Startup migration/seed failed:', error);
  process.exit(1);
}

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
