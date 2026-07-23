import db from '../db.js';

export function normalizeAddress(address) {
  if (!address) return null;
  let a = String(address).toLowerCase().trim();
  const map = {
    street: 'st', avenue: 'ave', drive: 'dr', road: 'rd', court: 'ct', lane: 'ln',
    boulevard: 'blvd', place: 'pl', circle: 'cir', terrace: 'ter',
    north: 'n', south: 's', east: 'e', west: 'w',
  };
  for (const [long, short] of Object.entries(map)) a = a.replace(new RegExp(`\\b${long}\\b`, 'g'), short);
  return a.replace(/[^a-z0-9]/g, '');
}

/** Duplicate detection across address, parcel, court case, email, phone. */
export async function findDuplicatesForLead(lead) {
  const matches = [];
  const push = (type, record, reason) => matches.push({ type, record, reason });

  const others = await db('leads').whereNot('id', lead.id).whereNull('duplicate_of_lead_id').whereNull('deleted_at');
  const normalized = normalizeAddress(lead.property_address);

  for (const other of others) {
    if (normalized && normalizeAddress(other.property_address) === normalized) push('lead', other, 'Same property address');
    for (const [field, reason] of [
      ['parcel_number', 'Same parcel number'], ['court_case_number', 'Same court case number'],
      ['email', 'Same email'], ['phone', 'Same phone'],
    ]) {
      if (lead[field] && other[field] === lead[field]) push('lead', other, reason);
    }
  }

  const cases = await db('cases')
    .leftJoin('properties', 'cases.property_id', 'properties.id')
    .whereNull('cases.deleted_at')
    .select('cases.*', 'properties.address_line1', 'properties.parcel_number as prop_parcel');
  for (const caseRow of cases) {
    if (lead.parcel_number && caseRow.prop_parcel === lead.parcel_number) push('case', caseRow, 'Case with same parcel number');
    else if (normalized && normalizeAddress(caseRow.address_line1) === normalized) push('case', caseRow, 'Case with same property address');
  }

  const seen = new Set();
  return matches.filter((m) => {
    const key = `${m.type}-${m.record.id}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}
