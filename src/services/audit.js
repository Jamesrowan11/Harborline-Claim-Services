import db from '../db.js';

const MASKED = ['password', 'ssn_last_four', 'date_of_birth', 'mfa_secret', 'mfa_recovery_codes', 'secret', 'password_reset_token'];

export function maskAudit(values = {}) {
  const out = { ...values };
  for (const field of MASKED) if (field in out) out[field] = '[redacted]';
  delete out.created_at; delete out.updated_at;
  return out;
}

/** Append-only audit trail. No update/delete path exists anywhere in the app. */
export async function audit(event, { req = null, type = null, id = null, caseId = null, oldValues = null, newValues = null } = {}) {
  await db('audit_events').insert({
    user_id: req?.session?.userId ?? null,
    event,
    auditable_type: type,
    auditable_id: id,
    case_id: caseId,
    old_values: oldValues ? JSON.stringify(maskAudit(oldValues)) : null,
    new_values: newValues ? JSON.stringify(maskAudit(newValues)) : null,
    ip_address: req?.ip ?? null,
    user_agent: (req?.get?.('user-agent') ?? '').slice(0, 255) || null,
    created_at: new Date(),
  });
}
