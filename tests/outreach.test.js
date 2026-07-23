import { describe, it, expect, beforeEach } from 'vitest';
import db from '../src/db.js';
import { checkOutreach } from '../src/services/outreachGate.js';
import { config } from '../src/config.js';
import { freshDb, createCase, attachClaimant } from './helpers.js';

async function approvedTemplate() {
  await db('document_templates').where({ key: 'inquiry_acknowledgment' })
    .update({ approval_status: 'approved', approved_at: new Date() });
  return db('document_templates').where({ key: 'inquiry_acknowledgment' }).first();
}

async function outreachCase() {
  const caseRow = await createCase({ verification_level: 'source_confirmed', outreach_approved: true });
  const claimant = await attachClaimant(caseRow);
  await db('email_addresses').insert({ emailable_type: 'claimant', emailable_id: claimant.id, email: 'claimant@example.test' });
  await db('phone_numbers').insert({ phoneable_type: 'claimant', phoneable_id: claimant.id, number: '555-0101', sms_capable: true });
  return [caseRow, claimant];
}

describe('outreach compliance gate', () => {
  beforeEach(freshDb);

  it('blocks unapproved templates', async () => {
    const [caseRow, claimant] = await outreachCase();
    const draft = await db('document_templates').where({ key: 'inquiry_acknowledgment' }).first();
    expect(await checkOutreach('email', draft, caseRow, null, claimant)).toContain('not approved');
  });

  it('blocks opted-out recipients', async () => {
    const [caseRow, claimant] = await outreachCase();
    await db('opt_outs').insert({
      channel: 'email', value: 'claimant@example.test',
      optoutable_type: 'claimant', optoutable_id: claimant.id, occurred_at: new Date(),
    });
    expect(await checkOutreach('email', await approvedTemplate(), caseRow, null, claimant)).toContain('opted out');
  });

  it('requires separate SMS consent and the SMS-enabled flag', async () => {
    const [caseRow, claimant] = await outreachCase();
    const template = await approvedTemplate();

    config.security.sms.enabled = false;
    expect(await checkOutreach('sms', template, caseRow, null, claimant)).toContain('disabled');

    config.security.sms.enabled = true;
    expect(await checkOutreach('sms', template, caseRow, null, claimant)).toContain('SMS consent');

    await db('consent_records').insert({
      consentable_type: 'claimant', consentable_id: claimant.id,
      consent_type: 'sms', granted: true, source: 'portal', occurred_at: new Date(),
    });
    expect(await checkOutreach('sms', template, caseRow, null, claimant)).toBe(true);
    config.security.sms.enabled = false;
  });

  it('enforces verification level, outreach approval, and holds', async () => {
    const template = await approvedTemplate();
    const [caseRow, claimant] = await outreachCase();

    await db('cases').where({ id: caseRow.id }).update({ verification_level: 'none' });
    expect(await checkOutreach('email', template, await db('cases').where({ id: caseRow.id }).first(), null, claimant)).toContain('verification level');

    await db('cases').where({ id: caseRow.id }).update({ verification_level: 'source_confirmed', outreach_approved: false });
    expect(await checkOutreach('email', template, await db('cases').where({ id: caseRow.id }).first(), null, claimant)).toContain('not been approved');

    await db('cases').where({ id: caseRow.id }).update({ outreach_approved: true, compliance_hold: true });
    expect(await checkOutreach('email', template, await db('cases').where({ id: caseRow.id }).first(), null, claimant)).toContain('hold');
  });

  it('enforces contact-frequency limits', async () => {
    const [caseRow, claimant] = await outreachCase();
    const template = await approvedTemplate();
    for (let i = 0; i < 2; i++) {
      await db('communications').insert({
        case_id: caseRow.id, channel: 'email', direction: 'outbound',
        recipient: 'claimant@example.test', status: 'sent', body_rendered: 'x',
      });
    }
    expect(await checkOutreach('email', template, caseRow, null, claimant)).toContain('frequency');
  });

  it('blocks leads without consent to contact', async () => {
    const [{ id: leadId }] = await db('leads').insert({
      lead_number: 'L-2026-000900', first_name: 'No', last_name: 'Consent',
      email: 'noconsent@example.test', consent_to_contact: false,
    }, ['id']);
    const lead = await db('leads').where({ id: leadId }).first();
    expect(await checkOutreach('email', await approvedTemplate(), null, lead, null)).toContain('consented');
  });
});
