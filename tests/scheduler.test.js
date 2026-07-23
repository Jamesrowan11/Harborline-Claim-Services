import { describe, it, expect, beforeEach } from 'vitest';
import db from '../src/db.js';
import { runDailyScans } from '../src/services/scans.js';
import { enforceRetention } from '../src/services/retention.js';
import { wireAutomationEngine } from '../src/automation/engine.js';
import { freshDb, createCase } from './helpers.js';

wireAutomationEngine();

describe('scheduled scans', () => {
  beforeEach(async () => {
    await freshDb();
    await db('automations').del();
  });

  it('fires deadline events that active automations consume, idempotently', async () => {
    const caseRow = await createCase();
    await db('deadlines').insert({ case_id: caseRow.id, name: 'Filing follow-up', due_at: new Date(Date.now() + 3 * 86400_000) });
    await db('automations').insert({
      name: 'Deadline alert', trigger_event: 'deadline.approaching',
      conditions: '[]', mode: 'active',
      actions: JSON.stringify([{ type: 'create_task', params: { title: 'Deadline follow-up task' } }]),
    });

    await runDailyScans();
    await runDailyScans(); // same day: idempotent

    expect(Number((await db('case_tasks').where({ title: 'Deadline follow-up task' }).count({ c: '*' }))[0].c)).toBe(1);
    expect(Number((await db('automation_runs').count({ c: '*' }))[0].c)).toBe(1);
  });

  it('marks stale sources during the scan', async () => {
    const caseRow = await createCase();
    const [{ id: sourceId }] = await db('source_records').insert({
      case_id: caseRow.id, source_type: 'court_docket', title: 'Docket pull',
      status: 'current', stale_after: new Date(Date.now() - 86400_000),
    }, ['id']);

    await runDailyScans();
    expect((await db('source_records').where({ id: sourceId }).first()).status).toBe('stale');
  });
});

describe('retention with legal holds', () => {
  beforeEach(freshDb);

  const oldLead = async (leadNumber) => {
    const [{ id }] = await db('leads').insert({
      lead_number: leadNumber, first_name: 'Old', last_name: 'Lead', status: 'closed',
    }, ['id']);
    await db('leads').where({ id }).update({ updated_at: new Date(Date.now() - 2 * 365 * 86400_000) });
    return id;
  };

  it('never deletes records under legal hold', async () => {
    await db('retention_policies').insert({ record_type: 'leads', retain_months: 1, action_after: 'delete', active: true });
    const leadId = await oldLead('L-2020-000001');
    await db('legal_holds').insert({ holdable_type: 'lead', holdable_id: leadId, reason: 'Litigation hold' });

    await enforceRetention();
    const lead = await db('leads').where({ id: leadId }).first();
    expect(lead.deleted_at).toBeNull();
  });

  it('deletes expired records when active and unheld; does nothing when inactive', async () => {
    await db('retention_policies').insert({ record_type: 'leads', retain_months: 1, action_after: 'delete', active: true });
    const deletableId = await oldLead('L-2020-000002');
    await enforceRetention();
    expect((await db('leads').where({ id: deletableId }).first()).deleted_at).toBeTruthy();

    await db('retention_policies').where({ record_type: 'leads' }).update({ active: false });
    const keptId = await oldLead('L-2020-000003');
    await enforceRetention();
    expect((await db('leads').where({ id: keptId }).first()).deleted_at).toBeNull();
  });
});

describe('letter verification exposure', () => {
  beforeEach(freshDb);

  it('confirms authenticity without exposing amounts', async () => {
    const { createApp } = await import('../src/app.js');
    const request = (await import('supertest')).default;
    const app = createApp();
    const { attachClaimant } = await import('./helpers.js');

    const caseRow = await createCase({ estimated_surplus: 42500 });
    await attachClaimant(caseRow, { last_name: 'Demoperson' });

    const response = await request(app).post('/verify-a-letter').type('form')
      .send({ reference: caseRow.case_number, last_name: 'Demoperson', zip: '21401' });
    expect(response.status).toBe(200);
    expect(response.text).toContain('authentic');
    expect(response.text).not.toContain('42,500');
    expect(response.text).not.toContain('42500');

    const wrong = await request(app).post('/verify-a-letter').type('form')
      .send({ reference: caseRow.case_number, last_name: 'Wrongname', zip: '21401' });
    expect(wrong.text).toContain('could not verify');
  });
});

describe('audit trail', () => {
  beforeEach(freshDb);

  it('masks sensitive fields in audit payloads', async () => {
    const { audit, maskAudit } = await import('../src/services/audit.js');
    const masked = maskAudit({ ssn_last_four: '1234', date_of_birth: '1950-01-01', first_name: 'Jo' });
    expect(masked.ssn_last_four).toBe('[redacted]');
    expect(masked.date_of_birth).toBe('[redacted]');
    expect(masked.first_name).toBe('Jo');

    await audit('created', { type: 'claimant', id: 1, newValues: { ssn_last_four: '9999' } });
    const row = await db('audit_events').where({ event: 'created' }).first();
    expect(JSON.parse(row.new_values).ssn_last_four).toBe('[redacted]');
  });
});
