import db from '../db.js';
import { bus } from '../events.js';
import Settings from './settings.js';
import { findDuplicatesForLead } from './duplicates.js';
import { sendMail, notifyUser } from './mailer.js';
import { config } from '../config.js';

async function nextLeadNumber() {
  const prefix = `L-${new Date().getFullYear()}-`;
  const last = await db('leads').where('lead_number', 'like', `${prefix}%`).orderBy('id', 'desc').first();
  const sequence = last ? Number(last.lead_number.slice(prefix.length)) + 1 : 1;
  return prefix + String(sequence).padStart(6, '0');
}

/** Creates a lead + consent records, checks duplicates, applies assignment
 *  rules, creates preliminary research tasks, queues confirmation + staff
 *  notifications, and fires lead.created. */
export async function createLead(data, ip = null, source = 'website') {
  const [{ id: leadId }] = await db('leads').insert({
    ...data,
    lead_number: await nextLeadNumber(),
    submitted_ip: ip,
    source,
  }, ['id']);

  const consents = {
    contact: !!data.consent_to_contact,
    privacy_policy: !!data.privacy_policy_agreed,
    electronic_communications: !!data.electronic_consent,
    sms: !!data.sms_consent,
  };
  for (const [type, granted] of Object.entries(consents)) {
    await db('consent_records').insert({
      consentable_type: 'lead', consentable_id: leadId, consent_type: type,
      granted, source: 'web_form',
      text_version: String(await Settings.get(`consent_text_version.${type}`, 'v1')),
      ip_address: ip, occurred_at: new Date(),
    });
  }

  let lead = await db('leads').where({ id: leadId }).first();

  const duplicates = await findDuplicatesForLead(lead);
  if (duplicates.length) {
    await db('leads').where({ id: leadId }).update({ status: 'screening' });
    await db('notes').insert({
      notable_type: 'lead', notable_id: leadId, is_staff_only: true,
      body: 'Possible duplicates found: ' + duplicates.map(
        (m) => `${m.record.lead_number ?? m.record.case_number} (${m.reason})`).join('; '),
    });
  }

  // Configurable assignment rules: [{county, state, assignee_email}]
  const rules = (await Settings.get('assignment_rules', [])) ?? [];
  for (const rule of rules) {
    const countyOk = !rule.county || rule.county.toLowerCase() === String(lead.property_county ?? '').toLowerCase();
    const stateOk = !rule.state || rule.state.toLowerCase() === String(lead.property_state ?? '').toLowerCase();
    if (countyOk && stateOk) {
      const user = await db('users').where({ email: rule.assignee_email }).first();
      if (user) { await db('leads').where({ id: leadId }).update({ assigned_to: user.id }); break; }
    }
  }
  lead = await db('leads').where({ id: leadId }).first();

  for (const title of [
    'Confirm property and sale details from public records',
    'Check for existing or duplicate files',
    'Identify possible funds holder',
  ]) {
    await db('case_tasks').insert({
      lead_id: leadId, title, assigned_to: lead.assigned_to,
      due_at: new Date(Date.now() + 3 * 86400_000),
    });
  }

  if (lead.email && lead.consent_to_contact) {
    try {
      await sendMail({
        to: lead.email,
        subject: `We received your inquiry — ${lead.lead_number}`,
        text: `Hello ${lead.first_name},\n\nThank you for contacting ${config.brand.name}. Your inquiry has been submitted for preliminary review.\n\nYour reference number is ${lead.lead_number}. Please keep it for your records.\n\nImportant: this confirmation does not mean that funds exist or that any recovery is guaranteed. We research public records first and will only contact you about verified findings.\n\n${config.disclaimer}`,
      });
    } catch { /* confirmation email failure never blocks intake */ }
  }

  const recipients = lead.assigned_to
    ? [await db('users').where({ id: lead.assigned_to }).first()]
    : await db('users').where({ role: 'Company Administrator' }).whereNull('deactivated_at');
  for (const recipient of recipients.filter(Boolean)) {
    await notifyUser(recipient.id, `New inquiry received: ${lead.lead_number}`, '', lead.lead_number);
  }

  await bus.emitDomain('lead.created', {
    leadId, context: { county: lead.property_county, state: lead.property_state },
  });

  return lead;
}
