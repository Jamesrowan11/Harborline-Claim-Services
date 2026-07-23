import db from '../db.js';
import Settings from './settings.js';
import { bus } from '../events.js';

/** Daily scans: time-based situations → domain events (deadline.approaching,
 *  task.overdue, source.stale, case.inactive, verification.expired). Each
 *  event carries a per-day idempotency suffix so re-runs cannot double-fire. */
export async function runDailyScans() {
  const today = new Date().toISOString().slice(0, 10);
  const counts = { deadlines: 0, tasks: 0, sources: 0, inactive: 0, verifications: 0 };
  const now = new Date();

  const deadlines = await db('deadlines').whereNull('met_at')
    .whereBetween('due_at', [now, new Date(Date.now() + 7 * 86400_000)]);
  for (const deadline of deadlines) {
    counts.deadlines++;
    await bus.emitDomain('deadline.approaching', {
      caseId: deadline.case_id,
      context: { deadline_id: deadline.id, deadline_name: deadline.name, idempotency_suffix: `deadline-${deadline.id}-${today}` },
    });
  }

  const overdue = await db('case_tasks').whereNot('status', 'done').where('due_at', '<', now);
  for (const task of overdue) {
    counts.tasks++;
    await bus.emitDomain('task.overdue', {
      caseId: task.case_id, leadId: task.lead_id,
      context: { task_id: task.id, idempotency_suffix: `task-${task.id}-${today}` },
    });
  }

  const staleSources = await db('source_records').where('status', 'current').where('stale_after', '<', now);
  for (const source of staleSources) {
    counts.sources++;
    await db('source_records').where({ id: source.id }).update({ status: 'stale' });
    await bus.emitDomain('source.stale', {
      caseId: source.case_id,
      context: { source_id: source.id, idempotency_suffix: `source-${source.id}-${today}` },
    });
  }

  const inactivityDays = Number(await Settings.get('inactivity_alert_days', 21));
  const cutoff = new Date(Date.now() - inactivityDays * 86400_000);
  const openStages = (await db('pipeline_stages').where({ is_closed: false })).map((s) => s.id);
  const inactive = await db('cases').whereIn('pipeline_stage_id', openStages)
    .whereNull('deleted_at').where('last_activity_at', '<', cutoff);
  for (const caseRow of inactive) {
    counts.inactive++;
    await bus.emitDomain('case.inactive', {
      caseId: caseRow.id, context: { idempotency_suffix: `inactive-${caseRow.id}-${today}` },
    });
  }

  const expired = await db('surplus_records').where('status', 'verified').where('verification_expires_at', '<', now);
  for (const record of expired) {
    counts.verifications++;
    await bus.emitDomain('verification.expired', {
      caseId: record.case_id,
      context: { surplus_record_id: record.id, idempotency_suffix: `verification-${record.id}-${today}` },
    });
  }

  return counts;
}
