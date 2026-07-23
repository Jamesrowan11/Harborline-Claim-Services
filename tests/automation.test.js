import { describe, it, expect, beforeEach } from 'vitest';
import db from '../src/db.js';
import { runAutomation } from '../src/automation/engine.js';
import { bus } from '../src/events.js';
import { wireAutomationEngine } from '../src/automation/engine.js';
import Settings from '../src/services/settings.js';
import { freshDb, createCase } from './helpers.js';

wireAutomationEngine();

async function makeAutomation(overrides = {}) {
  const [{ id }] = await db('automations').insert({
    name: overrides.name ?? 'Test automation',
    trigger_event: 'case.stage_changed',
    conditions: JSON.stringify(overrides.conditions ?? []),
    actions: JSON.stringify(overrides.actions ?? [{ type: 'create_task', params: { title: 'Automation-created task' } }]),
    mode: overrides.mode ?? 'active',
    is_sensitive: overrides.is_sensitive ?? false,
    approved_at: overrides.approved_at ?? null,
    max_runs_per_hour: overrides.max_runs_per_hour ?? 100,
    max_runs_per_case_per_day: overrides.max_runs_per_case_per_day ?? 5,
  }, ['id']);
  return db('automations').where({ id }).first();
}

const fire = (caseRow, context = {}) =>
  bus.emitDomain('case.stage_changed', { caseId: caseRow.id, context: { idempotency_suffix: Math.random().toString(36), ...context } });

const taskCount = async () => Number((await db('case_tasks').where({ title: 'Automation-created task' }).count({ c: '*' }))[0].c);
const runsBy = async (status) => Number((await db('automation_runs').where({ status }).count({ c: '*' }))[0].c);

describe('automation engine safety', () => {
  beforeEach(async () => {
    await freshDb();
    await db('automations').del(); // remove seeded defaults for clean assertions
  });

  it('executes active automations and logs success', async () => {
    await makeAutomation();
    await fire(await createCase());
    expect(await taskCount()).toBe(1);
    expect(await runsBy('success')).toBe(1);
  });

  it('never runs draft automations', async () => {
    await makeAutomation({ mode: 'draft' });
    await fire(await createCase());
    expect(await taskCount()).toBe(0);
    expect(Number((await db('automation_runs').count({ c: '*' }))[0].c)).toBe(0);
  });

  it('logs without acting in test mode', async () => {
    await makeAutomation({ mode: 'test' });
    await fire(await createCase());
    expect(await taskCount()).toBe(0);
    expect(await runsBy('test')).toBe(1);
  });

  it('queues pending approval in approval mode', async () => {
    await makeAutomation({ mode: 'approval' });
    await fire(await createCase());
    expect(await runsBy('pending_approval')).toBe(1);
    expect(await taskCount()).toBe(0);
  });

  it('refuses unapproved sensitive automations', async () => {
    await makeAutomation({ is_sensitive: true, approved_at: null });
    await fire(await createCase());
    expect(Number((await db('automation_runs').count({ c: '*' }))[0].c)).toBe(0);
  });

  it('evaluates conditions before acting', async () => {
    await makeAutomation({ conditions: [{ field: 'county', operator: 'equals', value: 'Anne Arundel' }] });
    await fire(await createCase({ county: 'Howard', case_number: 'HCS-2026-MD-HO-000700' }));
    expect(await taskCount()).toBe(0);
    await fire(await createCase({ case_number: 'HCS-2026-MD-AA-000701' }));
    expect(await taskCount()).toBe(1);
  });

  it('is idempotent for the same suffix', async () => {
    await makeAutomation();
    const caseRow = await createCase();
    await fire(caseRow, { idempotency_suffix: 'same' });
    await fire(caseRow, { idempotency_suffix: 'same' });
    expect(Number((await db('automation_runs').count({ c: '*' }))[0].c)).toBe(1);
  });

  it('skips paused cases and blocks held cases', async () => {
    await makeAutomation();
    await fire(await createCase({ automation_paused: true, case_number: 'HCS-2026-MD-AA-000702' }));
    expect(await runsBy('skipped')).toBe(1);
    await fire(await createCase({ legal_hold: true, case_number: 'HCS-2026-MD-AA-000703' }));
    expect(await runsBy('blocked')).toBe(1);
    expect(await taskCount()).toBe(0);
  });

  it('enforces per-case daily rate limits', async () => {
    await makeAutomation({ max_runs_per_case_per_day: 2 });
    const caseRow = await createCase();
    for (let i = 0; i < 3; i++) await fire(caseRow);
    expect(await runsBy('success')).toBe(2);
    expect(await runsBy('blocked')).toBe(1);
  });

  it('halts under the global emergency stop', async () => {
    await Settings.set('automation_global_stop', true);
    await makeAutomation();
    await fire(await createCase());
    expect(Number((await db('automation_runs').count({ c: '*' }))[0].c)).toBe(0);
    await Settings.set('automation_global_stop', false);
  });

  it('prevents loops via chain depth', async () => {
    await makeAutomation();
    await fire(await createCase(), { chain_depth: 99 });
    expect(await runsBy('blocked')).toBe(1);
    expect(await taskCount()).toBe(0);
  });
});
