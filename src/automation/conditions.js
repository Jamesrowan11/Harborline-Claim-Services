import db from '../db.js';

/** All conditions must pass (AND). {field, operator, value} */
export async function conditionsPass(conditions, caseRow, lead, context = {}) {
  for (const condition of conditions ?? []) {
    const actual = await resolve(condition.field ?? '', caseRow, lead, context);
    if (!compare(actual, condition.operator ?? 'equals', condition.value)) return false;
  }
  return true;
}

async function resolve(field, caseRow, lead, context) {
  if (field in context) return context[field];
  if (field.startsWith('custom.')) {
    const custom = caseRow?.custom_fields ? JSON.parse(caseRow.custom_fields) : {};
    return custom[field.slice(7)];
  }
  switch (field) {
    case 'county': return caseRow?.county ?? lead?.property_county;
    case 'state': return caseRow?.state ?? lead?.property_state;
    case 'case_type': return caseRow?.case_type;
    case 'surplus_amount': return caseRow?.verified_surplus ?? caseRow?.estimated_surplus;
    case 'verification_level': return caseRow?.verification_level;
    case 'risk_level': return caseRow?.risk_level;
    case 'legal_complexity': return caseRow?.legal_complexity;
    case 'outreach_approved': return !!caseRow?.outreach_approved;
    case 'consent_status': return lead?.consent_to_contact ?? null;
    case 'missing_documents':
      if (!caseRow) return false;
      return !!(await db('document_requests').where({ case_id: caseRow.id, status: 'requested' }).first());
    case 'days_since_last_activity':
      return caseRow?.last_activity_at ? Math.floor((Date.now() - new Date(caseRow.last_activity_at)) / 86400_000) : null;
    case 'stage': {
      if (!caseRow) return null;
      const stage = await db('pipeline_stages').where({ id: caseRow.pipeline_stage_id }).first();
      return stage?.key;
    }
    case 'assigned_employee': {
      const id = caseRow?.assigned_to ?? lead?.assigned_to;
      if (!id) return null;
      return (await db('users').where({ id }).first())?.email;
    }
    case 'sale_type': {
      if (!caseRow?.sale_id) return null;
      return (await db('sales').where({ id: caseRow.sale_id }).first())?.sale_type;
    }
    default: return caseRow?.[field] ?? lead?.[field];
  }
}

export function compare(actual, operator, expected) {
  switch (operator) {
    case 'equals': return actual == expected;
    case 'not_equals': return actual != expected;
    case 'greater_than': return actual !== null && Number(actual) > Number(expected);
    case 'less_than': return actual !== null && Number(actual) < Number(expected);
    case 'contains': return typeof actual === 'string' && actual.toLowerCase().includes(String(expected).toLowerCase());
    case 'in': return [].concat(expected).includes(actual);
    case 'not_in': return ![].concat(expected).includes(actual);
    case 'is_true': return !!actual === true;
    case 'is_false': return !!actual === false;
    case 'is_empty': return actual === null || actual === undefined || actual === '' ;
    case 'is_not_empty': return !(actual === null || actual === undefined || actual === '');
    default: return false;
  }
}
