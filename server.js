/**
 * Application startup file — set this as the "Application Startup File" in
 * Plesk's Node.js settings. Starts the web server, the in-process scheduler
 * (daily scans, retention, hourly automation tick), and the job-queue worker.
 */
import cron from 'node-cron';
import { createApp } from './src/app.js';
import { config } from './src/config.js';
import { processJobs } from './src/services/queue.js';
import { runDailyScans } from './src/services/scans.js';
import { enforceRetention } from './src/services/retention.js';
import { bus } from './src/events.js';

const app = createApp();

app.listen(config.port, () => {
  console.log(`${config.brand.name} listening on port ${config.port} (${config.env})`);
});

// Queue worker: poll every 15 seconds.
setInterval(() => processJobs().catch((e) => console.error('queue worker', e)), 15_000);

// Scheduler (replaces external cron; times are server-local).
cron.schedule('0 6 * * *', () => runDailyScans().catch((e) => console.error('daily scans', e)));
cron.schedule('30 2 * * *', () => enforceRetention().catch((e) => console.error('retention', e)));
cron.schedule('0 * * * *', () => bus.emitDomain('schedule.tick', {
  context: { idempotency_suffix: `tick-${new Date().toISOString().slice(0, 13)}` },
}).catch((e) => console.error('schedule tick', e)));
