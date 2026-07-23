/**
 * Application startup file for Plesk ("Application Startup File": server.js).
 *
 * IMPORTANT: Phusion Passenger loads this file with require(), so it must
 * contain NO top-level await — otherwise the process dies before any code
 * runs ("application process exited prematurely" with no log). The async
 * boot happens via a dynamic import; any startup failure — including
 * import-time errors — is written to storage/logs/boot-error.log inside the
 * site folder, always visible in Plesk's File Manager.
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

import('./src/boot.js').catch(recordFatal);
