import db from '../db.js';
import { sendMail, sendSms } from './mailer.js';

/** Tiny DB-backed job queue. The in-process worker (started from server.js)
 *  polls every 15s; jobs retry up to 3 times with backoff. */
export async function enqueue(jobType, payload, delaySeconds = 0) {
  await db('jobs').insert({
    job_type: jobType,
    payload: JSON.stringify(payload),
    run_after: new Date(Date.now() + delaySeconds * 1000),
  });
}

export async function processJobs(limit = 20) {
  const jobs = await db('jobs')
    .where('status', 'pending')
    .where((q) => q.whereNull('run_after').orWhere('run_after', '<=', new Date()))
    .orderBy('id').limit(limit);

  for (const job of jobs) {
    try {
      await handle(job.job_type, JSON.parse(job.payload));
      await db('jobs').where({ id: job.id }).update({ status: 'done', updated_at: new Date() });
    } catch (error) {
      const attempts = job.attempts + 1;
      await db('jobs').where({ id: job.id }).update({
        attempts,
        status: attempts >= 3 ? 'failed' : 'pending',
        last_error: String(error.message ?? error).slice(0, 2000),
        run_after: new Date(Date.now() + attempts * 60_000),
        updated_at: new Date(),
      });
    }
  }
  return jobs.length;
}

async function handle(jobType, payload) {
  if (jobType === 'send_communication') {
    const communication = await db('communications').where({ id: payload.communicationId }).first();
    if (!communication || communication.status !== 'queued') return;
    if (!communication.recipient) {
      await db('communications').where({ id: communication.id }).update({ status: 'failed', meta: JSON.stringify({ error: 'No recipient' }) });
      return;
    }
    let ok = false;
    if (communication.channel === 'email') {
      await sendMail({ to: communication.recipient, subject: communication.subject ?? 'A message from us', text: communication.body_rendered ?? '' });
      ok = true;
    } else if (communication.channel === 'sms') {
      ok = await sendSms(communication.recipient, communication.body_rendered ?? '');
    }
    await db('communications').where({ id: communication.id })
      .update({ status: ok ? 'sent' : 'failed', sent_at: ok ? new Date() : null });
    return;
  }
  throw new Error(`Unknown job type: ${jobType}`);
}
