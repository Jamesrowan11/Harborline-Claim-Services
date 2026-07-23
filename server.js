/**
 * Application startup file for Plesk ("Application Startup File": server.js).
 * This thin loader captures ANY startup failure — including import-time
 * errors — and writes it to storage/logs/boot-error.log inside the site
 * folder, so the real error is always visible in Plesk's File Manager even
 * when Passenger only says "the application process exited prematurely".
 */
import fs from 'node:fs';
import path from 'node:path';

function recordFatal(error) {
  const message = `[${new Date().toISOString()}] ${error?.stack ?? error}\n\n`;
  try {
    const dir = path.join(process.cwd(), 'storage', 'logs');
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, 'boot-error.log'), message);
  } catch { /* fall through to stderr */ }
  console.error(message);
  process.exit(1);
}

process.on('uncaughtException', recordFatal);
process.on('unhandledRejection', recordFatal);

try {
  await import('./src/boot.js');
} catch (error) {
  recordFatal(error);
}
