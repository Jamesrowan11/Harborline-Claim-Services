import db from '../db.js';
import { bus } from '../events.js';
import Settings from './settings.js';
import { config } from '../config.js';
import { nextCaseNumber } from './caseNumber.js';

export const MD_COUNTY_CODES = {
  Allegany: 'AL', 'Anne Arundel': 'AA', 'Baltimore City': 'BC', Baltimore: 'BA',
  Calvert: 'CV', Caroline: 'CE', Carroll: 'CR', Cecil: 'CC', Charles: 'CH',
  Dorchester: 'DO', Frederick: 'FR', Garrett: 'GA', Harford: 'HA', Howard: 'HO',
  Kent: 'KE', Montgomery: 'MO', "Prince George's": 'PG', "Queen Anne's": 'QA',
  Somerset: 'SO', "St. Mary's": 'SM', Talbot: 'TA', Washington: 'WA',
  Wicomico: 'WI', Worcester: 'WO',
};

export async function countyCode(county) {
  const custom = (await Settings.get('county_codes', {})) ?? {};
  const map = { ...MD_COUNTY_CODES, ...custom };
  for (const [name, code] of Object.entries(map)) {
    if (name.toLowerCase() === String(county ?? '').toLowerCase()) return code;
  }
  return (String(county ?? '').replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase() || 'XX');
}

export async function convertLeadToCase(leadId, userId = null) {
  const lead = await db('leads').where({ id: leadId }).first();
  if (!lead) throw new Error('Lead not found');
  if (lead.status === 'converted') throw Object.assign(new Error('Lead already converted.'), { status: 422 });

  const state = (lead.property_state || config.brand.primary_state).toUpperCase();
  const code = await countyCode(lead.property_county);

  let propertyId = null;
  if (lead.property_address) {
    [{ id: propertyId }] = await db('properties').insert({
      address_line1: lead.property_address,
      county: lead.property_county ?? 'Unknown',
      county_code: code, state,
      parcel_number: lead.parcel_number, tax_account_number: lead.tax_account_number,
      former_owner_names: JSON.stringify([lead.former_owner_name].filter(Boolean)),
    }, ['id']);
  }

  const stage = await db('pipeline_stages').where({ key: 'preliminary_screening' }).first()
    ?? await db('pipeline_stages').orderBy('sort_order').first();

  const [{ id: caseId }] = await db('cases').insert({
    case_number: await nextCaseNumber(state, code),
    lead_id: leadId,
    pipeline_stage_id: stage.id,
    property_id: propertyId,
    county: lead.property_county, county_code: code, state,
    assigned_to: lead.assigned_to ?? userId,
    case_manager_id: userId,
    last_activity_at: new Date(),
  }, ['id']);

  const [{ id: claimantId }] = await db('claimants').insert({
    type: 'individual',
    first_name: lead.first_name, middle_name: lead.middle_name, last_name: lead.last_name,
    relationship_to_owner: lead.relationship_to_owner,
  }, ['id']);
  await db('case_claimant').insert({ case_id: caseId, claimant_id: claimantId, role: 'primary' });

  if (lead.email) await db('email_addresses').insert({ emailable_type: 'claimant', emailable_id: claimantId, email: lead.email });
  if (lead.phone) await db('phone_numbers').insert({ phoneable_type: 'claimant', phoneable_id: claimantId, number: lead.phone, sms_capable: !!lead.sms_consent });

  await db('leads').where({ id: leadId }).update({ status: 'converted', case_id: caseId });
  await db('case_tasks').where({ lead_id: leadId }).whereNull('case_id').update({ case_id: caseId });

  await bus.emitDomain('case.created', { caseId, leadId });

  return db('cases').where({ id: caseId }).first();
}
