import { describe, it, expect, beforeAll, beforeEach } from 'vitest';
import request from 'supertest';
import { createApp } from '../src/app.js';
import db from '../src/db.js';
import { nextCaseNumber } from '../src/services/caseNumber.js';
import Settings from '../src/services/settings.js';
import { convertLeadToCase } from '../src/services/caseConversion.js';
import { changeStage } from '../src/services/stageChange.js';
import { freshDb, createStaff, loginAgent, createCase } from './helpers.js';

const app = createApp();

describe('case numbers', () => {
  beforeAll(freshDb);

  it('generates the documented format with per-county sequences', async () => {
    expect(await nextCaseNumber('MD', 'AA', 2026)).toBe('HCS-2026-MD-AA-000001');
    expect(await nextCaseNumber('MD', 'AA', 2026)).toBe('HCS-2026-MD-AA-000002');
    expect(await nextCaseNumber('MD', 'HO', 2026)).toBe('HCS-2026-MD-HO-000001');
  });

  it('honors an administrator-modified format', async () => {
    await Settings.set('case_number_format', '{PREFIX}/{COUNTY}/{SEQ:4}');
    expect(await nextCaseNumber('MD', 'AA', 2026)).toBe('HCS/AA/0003');
    await Settings.set('case_number_format', null);
    Settings.clearCache();
  });
});

describe('public lead intake', () => {
  beforeAll(freshDb);

  const payload = (overrides = {}) => ({
    first_name: 'Dana', last_name: 'Testperson',
    relationship_to_owner: 'I am the former owner',
    property_address: '123 Fictional Harbor Lane', property_county: 'Anne Arundel', property_state: 'MD',
    email: 'dana@example.test', preferred_contact_method: 'email',
    consent_to_contact: '1', privacy_policy_agreed: '1', electronic_consent: '1',
    ...overrides,
  });

  it('creates a lead with number, consents, and research tasks', async () => {
    const response = await request(app).post('/check-for-possible-funds').type('form').send(payload());
    expect(response.status).toBe(200);
    expect(response.text).toContain('Submitted for Preliminary Review');

    const lead = await db('leads').orderBy('id', 'desc').first();
    expect(lead.lead_number).toMatch(/^L-\d{4}-\d{6}$/);
    const consents = await db('consent_records').where({ consentable_type: 'lead', consentable_id: lead.id });
    expect(consents.find((c) => c.consent_type === 'privacy_policy').granted).toBeTruthy();
    expect(consents.find((c) => c.consent_type === 'sms').granted).toBeFalsy(); // optional & separate
    expect((await db('case_tasks').where({ lead_id: lead.id })).length).toBe(3);
  });

  it('rejects missing consents and honeypot submissions', async () => {
    const missing = await request(app).post('/check-for-possible-funds').type('form')
      .send(payload({ privacy_policy_agreed: '', email: 'other@example.test' }));
    expect(missing.status).toBe(302); // back with errors

    const before = Number((await db('leads').count({ c: '*' }))[0].c);
    await request(app).post('/check-for-possible-funds').type('form').send(payload({ website: 'spambot' }));
    expect(Number((await db('leads').count({ c: '*' }))[0].c)).toBe(before);
  });

  it('flags duplicates by normalized address', async () => {
    await request(app).post('/check-for-possible-funds').type('form')
      .send(payload({ email: 'dupe@example.test', property_address: '123 Fictional Harbor Ln.' }));
    const lead = await db('leads').orderBy('id', 'desc').first();
    expect(lead.status).toBe('screening');
    const note = await db('notes').where({ notable_type: 'lead', notable_id: lead.id }).first();
    expect(note.body).toContain('duplicate');
  });
});

describe('lead conversion and stage changes', () => {
  beforeEach(freshDb);

  it('converts a lead into a case with property, claimant, and number', async () => {
    const [{ id: leadId }] = await db('leads').insert({
      lead_number: 'L-2026-000123', first_name: 'Dana', last_name: 'Testperson',
      property_address: '9 Sample Cove', property_county: 'Howard', property_state: 'MD',
      email: 'dana@example.test', phone: '555-0102', consent_to_contact: true,
    }, ['id']);

    const caseRow = await convertLeadToCase(leadId);
    expect(caseRow.case_number).toBe(`HCS-${new Date().getFullYear()}-MD-HO-000001`);
    expect((await db('case_claimant').where({ case_id: caseRow.id })).length).toBe(1);
    const lead = await db('leads').where({ id: leadId }).first();
    expect(lead.status).toBe('converted');
    expect(lead.case_id).toBe(caseRow.id);
    await expect(convertLeadToCase(leadId)).rejects.toThrow('already converted');
  });

  it('records stage transitions, closes cases, and maps client labels', async () => {
    const staff = await createStaff('Case Manager');
    const caseRow = await createCase();
    const closed = await db('pipeline_stages').where({ key: 'closed_no_surplus' }).first();

    await changeStage(caseRow.id, closed.id, staff.id, 'No surplus found');

    const updated = await db('cases').where({ id: caseRow.id }).first();
    expect(updated.pipeline_stage_id).toBe(closed.id);
    expect(updated.closed_at).toBeTruthy();
    const transition = await db('stage_transitions').where({ case_id: caseRow.id }).first();
    expect(transition.reason).toBe('No surplus found');
    expect(transition.user_id).toBe(staff.id);

    const filed = await createCase({ case_number: 'HCS-2026-MD-AA-000900' }, 'claim_filed');
    const stage = await db('pipeline_stages').where({ id: filed.pipeline_stage_id }).first();
    expect(stage.client_label).toBe('Claim Submitted');
  });

  it('lets authorized staff change stages via the portal', async () => {
    const staff = await createStaff('Case Manager');
    const agent = await loginAgent(app, staff);
    const caseRow = await createCase();
    const target = await db('pipeline_stages').where({ key: 'preliminary_screening' }).first();

    await agent.post(`/portal/cases/${caseRow.id}/stage`).type('form').send({ stage_id: target.id });
    expect((await db('cases').where({ id: caseRow.id }).first()).pipeline_stage_id).toBe(target.id);
  });
});

describe('permissions', () => {
  beforeAll(freshDb);

  it('blocks researchers from admin settings and payments', async () => {
    const researcher = await createStaff('Researcher');
    const agent = await loginAgent(app, researcher);
    expect((await agent.get('/portal/admin')).status).toBe(403);
    expect((await agent.get('/portal/payments')).status).toBe(403);
  });

  it('gives auditors read-only access', async () => {
    const auditor = await createStaff('Read-Only Auditor');
    const agent = await loginAgent(app, auditor);
    expect((await agent.get('/portal/audit')).status).toBe(200);
    const caseRow = await createCase({ case_number: 'HCS-2026-MD-AA-000801' });
    const update = await agent.post(`/portal/cases/${caseRow.id}`).type('form').send({ _method: 'PUT', risk_level: 'high' });
    expect(update.status).toBe(403);
  });
});
