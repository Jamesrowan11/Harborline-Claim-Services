/** Operational CLI: node src/cli.js <migrate|rollback|seed|seed:demo|scans|retention|key:generate|create-admin> */
import crypto from 'node:crypto';
import db from './db.js';

const command = process.argv[2];

try {
  switch (command) {
    case 'migrate': {
      const [batch, applied] = await db.migrate.latest();
      console.log(`Batch ${batch}: ${applied.length ? applied.join(', ') : 'nothing to migrate'}`);
      break;
    }
    case 'rollback': {
      const [batch, rolled] = await db.migrate.rollback();
      console.log(`Rolled back batch ${batch}: ${rolled.join(', ') || 'nothing'}`);
      break;
    }
    case 'seed': {
      const { seedBaseline } = await import('./seeds/baseline.js');
      await seedBaseline(db);
      console.log('Baseline seed complete (roles, stages, templates, automations).');
      break;
    }
    case 'seed:demo': {
      const { seedBaseline } = await import('./seeds/baseline.js');
      const { seedDemo } = await import('./seeds/demo.js');
      await seedBaseline(db);
      await seedDemo(db);
      console.log('Demo seed complete (FICTIONAL data only).');
      break;
    }
    case 'scans': {
      const { runDailyScans } = await import('./services/scans.js');
      console.log('Scan complete:', await runDailyScans());
      break;
    }
    case 'retention': {
      const { enforceRetention } = await import('./services/retention.js');
      const actions = await enforceRetention({ dryRun: process.argv.includes('--dry-run') });
      console.log(`Retention ${process.argv.includes('--dry-run') ? '(dry-run) ' : ''}complete:`, actions.length, 'actions');
      actions.forEach((a) => console.log(' -', a));
      break;
    }
    case 'key:generate':
      console.log('APP_KEY=' + crypto.randomBytes(32).toString('base64'));
      break;
    case 'create-admin': {
      const [, , , email, name, password] = process.argv;
      if (!email || !name || !password) {
        console.log('Usage: node src/cli.js create-admin <email> <name> <password>');
        break;
      }
      const bcrypt = (await import('bcryptjs')).default;
      await db('users').insert({
        email: email.toLowerCase(), name, password: await bcrypt.hash(password, 12),
        user_type: 'staff', role: 'Super Administrator',
      });
      console.log(`Super Administrator ${email} created. They must enroll MFA on first login.`);
      break;
    }
    default:
      console.log('Commands: migrate | rollback | seed | seed:demo | scans | retention [--dry-run] | key:generate | create-admin');
  }
} finally {
  await db.destroy();
}
