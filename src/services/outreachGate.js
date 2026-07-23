import db from '../db.js';
import { config } from '../config.js';
import Settings from './settings.js';

/**
 * Central gate for ALL automated outbound communication:
 *  1. template must be approved,
 *  2. recipient must not have opted out,
 *  3. channel must be permitted (SMS needs separate consent + enabled flag),
 *  4. case must meet the required verification level and outreach approval,
 *  5. contact-frequency limits must be satisfied,
 *  6. case must not be on legal or compliance hold.
 * Returns true, or a human-readable block reason.
 */
export async function checkOutreach(channel, template, caseRow, lead, claimant) {
  if (!template) return 'No communication template specified.';
  if (template.approval_status !== 'approved') return `Template '${template.key}' is not approved.`;

  if (caseRow && (caseRow.legal_hold || caseRow.compliance_hold)) return 'Case is on legal or compliance hold.';

  if (channel === 'sms') {
    if (!config.security.sms.enabled) return 'SMS sending is disabled in configuration.';
    const smsConsent = lead?.sms_consent
      || (claimant && await db('consent_records').where({
        consentable_type: 'claimant', consentable_id: claimant.id, consent_type: 'sms', granted: true,
      }).first());
    if (!smsConsent) return 'Recipient has not given optional SMS consent.';
  }

  const email = claimant
    ? (await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimant.id }).first())?.email
    : lead?.email;
  const phone = claimant
    ? (await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimant.id }).first())?.number
    : lead?.phone;
  const value = channel === 'sms' ? phone : email;
  if (!value) return 'No recipient address on file.';

  const optedOut = await db('opt_outs').where('value', value).whereIn('channel', [channel, 'all']).first();
  if (optedOut) return 'Recipient has opted out of this channel.';

  if (lead && !lead.consent_to_contact) return 'Lead has not consented to contact.';

  if (caseRow) {
    const levels = { none: 0, preliminary: 1, source_confirmed: 2, holder_confirmed: 3 };
    const required = Number(await Settings.get('outreach_min_verification_level', 1));
    if ((levels[caseRow.verification_level] ?? 0) < required) {
      return 'Case has not reached the required verification level for outreach.';
    }
    if (!caseRow.outreach_approved) return 'Outreach has not been approved for this case.';
  }

  const maxPerWeek = Number(await Settings.get('outreach_max_contacts_per_week', 2));
  const weekAgo = new Date(Date.now() - 7 * 86400_000);
  const [{ count }] = await db('communications')
    .where({ direction: 'outbound', recipient: value })
    .whereIn('status', ['queued', 'sent', 'delivered'])
    .where('created_at', '>=', weekAgo)
    .count({ count: '*' });
  if (Number(count) >= maxPerWeek) return 'Contact-frequency limit reached for this recipient.';

  return true;
}
