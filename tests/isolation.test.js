import { describe, it, expect, beforeEach } from 'vitest';
import db from '../src/db.js';
import { createApp } from '../src/app.js';
import { freshDb, createCase, attachClaimant, createClient, createStaff, loginAgent } from './helpers.js';

const app = createApp();

describe('client portal isolation', () => {
  beforeEach(freshDb);

  it('scopes clients to their own cases only', async () => {
    const caseA = await createCase({ case_number: 'HCS-2026-MD-AA-000601' });
    const claimantA = await attachClaimant(caseA);
    const caseB = await createCase({ case_number: 'HCS-2026-MD-AA-000602' });
    await attachClaimant(caseB, { last_name: 'Other' });

    const client = await createClient(claimantA.id);
    const agent = await loginAgent(app, client, { mfa: false });

    expect((await agent.get(`/my/cases/${caseA.id}`)).status).toBe(200);
    expect((await agent.get(`/my/cases/${caseB.id}`)).status).toBe(403);
  });

  it('never exposes staff-only notes to clients', async () => {
    const caseRow = await createCase();
    const claimant = await attachClaimant(caseRow);
    await db('notes').insert({ notable_type: 'case', notable_id: caseRow.id, body: 'SECRET-SKIP-TRACE-NOTE', is_staff_only: true });

    const client = await createClient(claimant.id);
    const agent = await loginAgent(app, client, { mfa: false });
    const page = await agent.get(`/my/cases/${caseRow.id}`);
    expect(page.status).toBe(200);
    expect(page.text).not.toContain('SECRET-SKIP-TRACE-NOTE');
  });

  it('hides non-client-visible documents and blocks their download', async () => {
    const caseRow = await createCase();
    const claimant = await attachClaimant(caseRow);
    const [{ id: documentId }] = await db('documents').insert({
      case_id: caseRow.id, title: 'Internal research PDF', category: 'general',
      original_filename: 'x.pdf', path: 'x/x.pdf', mime_type: 'application/pdf',
      size_bytes: 10, status: 'approved', client_visible: false, virus_scan_status: 'clean',
    }, ['id']);

    const client = await createClient(claimant.id);
    const agent = await loginAgent(app, client, { mfa: false });
    expect((await agent.get(`/my/cases/${caseRow.id}`)).text).not.toContain('Internal research PDF');
    expect((await agent.get(`/my/cases/${caseRow.id}/documents/${documentId}/download`)).status).toBe(403);
  });

  it('prevents clients from messaging other cases', async () => {
    const caseA = await createCase({ case_number: 'HCS-2026-MD-AA-000603' });
    const claimantA = await attachClaimant(caseA);
    const caseB = await createCase({ case_number: 'HCS-2026-MD-AA-000604' });
    await attachClaimant(caseB, { last_name: 'Other' });

    const client = await createClient(claimantA.id);
    const agent = await loginAgent(app, client, { mfa: false });
    expect((await agent.post(`/my/cases/${caseB.id}/messages`).type('form').send({ body: 'hello' })).status).toBe(403);
    expect(Number((await db('portal_messages').count({ c: '*' }))[0].c)).toBe(0);
  });

  it('signs sent agreements with metadata and rejects non-sent ones', async () => {
    const caseRow = await createCase();
    const claimant = await attachClaimant(caseRow);
    const [{ id: agreementId }] = await db('agreements').insert({
      case_id: caseRow.id, claimant_id: claimant.id, title: 'Assistance Agreement',
      body_rendered: 'Terms…', status: 'sent',
    }, ['id']);

    const client = await createClient(claimant.id);
    const agent = await loginAgent(app, client, { mfa: false });
    await agent.post(`/my/cases/${caseRow.id}/agreements/${agreementId}/sign`).type('form')
      .send({ signature_name: 'Test Claimant', agree: '1' });

    const agreement = await db('agreements').where({ id: agreementId }).first();
    expect(agreement.status).toBe('signed');
    expect(agreement.signature_name).toBe('Test Claimant');
    expect(agreement.signed_at).toBeTruthy();
    expect(agreement.signature_ip).toBeTruthy();

    const [{ id: draftId }] = await db('agreements').insert({
      case_id: caseRow.id, claimant_id: claimant.id, title: 'Draft', status: 'draft',
    }, ['id']);
    const rejected = await agent.post(`/my/cases/${caseRow.id}/agreements/${draftId}/sign`).type('form')
      .send({ signature_name: 'Test Claimant', agree: '1' });
    expect(rejected.status).toBe(422);
  });

  it('blocks infected documents even for staff', async () => {
    const staff = await createStaff();
    const agent = await loginAgent(app, staff);
    const caseRow = await createCase();
    const [{ id: documentId }] = await db('documents').insert({
      case_id: caseRow.id, title: 'Bad file', category: 'general',
      original_filename: 'bad.pdf', path: 'x/bad.pdf', mime_type: 'application/pdf',
      size_bytes: 10, status: 'approved', client_visible: true, virus_scan_status: 'infected',
    }, ['id']);
    expect((await agent.get(`/portal/documents/${documentId}/download`)).status).toBe(403);
  });
});
