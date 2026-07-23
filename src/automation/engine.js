import crypto from 'node:crypto';
import db from '../db.js';
import { config } from '../config.js';
import Settings from '../services/settings.js';
import { conditionsPass } from './conditions.js';
import { executeAction } from './actions.js';
import { bus } from '../events.js';

export const MAX_CHAIN_DEPTH = 3;

/**
 * Automation engine with layered safety controls:
 *  - global emergency stop (env flag OR admin setting)
 *  - modes: draft (never runs), test (logs only), approval (queues
 *    pending-approval runs), active
 *  - sensitive automations (outbound communications) never run unapproved
 *  - per-case automation pause; legal/compliance holds block
 *  - per-automation hourly and per-case daily rate limits
 *  - idempotency keys prevent double execution
 *  - chain-depth counter prevents loops
 */
export async function handleDomainEvent(event) {
  if (config.security.automationGlobalStop || await Settings.get('automation_global_stop', false)) return;

  const automations = await db('automations').where({ trigger_event: event.key }).whereNull('deleted_at');
  for (const automation of automations) {
    await runAutomation(automation, event);
  }
}

export function isRunnable(automation) {
  if (automation.paused) return false;
  if (automation.is_sensitive && !automation.approved_at) return false;
  return ['active', 'test', 'approval'].includes(automation.mode);
}

export async function runAutomation(automation, event) {
  const caseRow = event.caseId ? await db('cases').where({ id: event.caseId }).first() : null;
  const lead = event.leadId ? await db('leads').where({ id: event.leadId }).first() : null;
  const context = event.context ?? {};

  const idempotencyKey = crypto.createHash('sha1').update([
    automation.id, event.key, caseRow?.id ?? 'null', lead?.id ?? 'null',
    context.idempotency_suffix ?? new Date().toISOString().slice(0, 16),
  ].join('|')).digest('hex');

  if (await db('automation_runs').where({ idempotency_key: idempotencyKey }).first()) return null;

  const record = async (status, message) => {
    const [{ id }] = await db('automation_runs').insert({
      automation_id: automation.id, case_id: caseRow?.id ?? null, lead_id: lead?.id ?? null,
      trigger_event: event.key, status, idempotency_key: idempotencyKey,
      message, context: JSON.stringify(context), started_at: new Date(), finished_at: new Date(),
    }, ['id']);
    return db('automation_runs').where({ id }).first();
  };

  if (!isRunnable(automation)) return null; // draft / paused / unapproved-sensitive: silently inert

  if ((context.chain_depth ?? 0) >= MAX_CHAIN_DEPTH) {
    return record('blocked', 'Loop prevention: maximum automation chain depth reached.');
  }
  if (caseRow?.automation_paused) return record('skipped', 'Automations are paused for this case.');
  if (caseRow && (caseRow.compliance_hold || caseRow.legal_hold)) {
    return record('blocked', 'Case is on compliance or legal hold.');
  }

  const hourAgo = new Date(Date.now() - 3600_000);
  const [{ count: hourly }] = await db('automation_runs')
    .where({ automation_id: automation.id }).where('created_at', '>=', hourAgo).count({ count: '*' });
  if (Number(hourly) >= automation.max_runs_per_hour) {
    return record('blocked', 'Hourly rate limit reached for this automation.');
  }

  if (caseRow) {
    const dayAgo = new Date(Date.now() - 86400_000);
    const [{ count: daily }] = await db('automation_runs')
      .where({ automation_id: automation.id, case_id: caseRow.id })
      .where('created_at', '>=', dayAgo).count({ count: '*' });
    if (Number(daily) >= automation.max_runs_per_case_per_day) {
      return record('blocked', 'Per-case daily rate limit reached.');
    }
  }

  const conditions = automation.conditions ? JSON.parse(automation.conditions) : [];
  if (!(await conditionsPass(conditions, caseRow, lead, context))) return null;

  if (automation.mode === 'approval') {
    return record('pending_approval', 'Awaiting manual approval before actions execute.');
  }

  const actions = JSON.parse(automation.actions);

  if (automation.mode === 'test') {
    return record('test', 'Test mode: conditions matched; actions were NOT executed. Actions: '
      + actions.map((a) => a.type).join(', '));
  }

  try {
    const messages = [];
    for (const action of actions) {
      messages.push(await executeAction(automation, action, caseRow, lead, event));
    }
    return record('success', messages.filter(Boolean).join(' | '));
  } catch (error) {
    return record('failed', String(error.message ?? error));
  }
}

/** Wire the engine to the domain bus (idempotent). */
let wired = false;
export function wireAutomationEngine() {
  if (wired) return;
  wired = true;
  bus.on('domain', handleDomainEvent);
}
