import fs from 'node:fs';
import path from 'node:path';
import db from '../db.js';
import { audit } from './audit.js';

async function underLegalHold(type, record) {
  if (record.legal_hold) return true;
  const hold = await db('legal_holds')
    .where({ holdable_type: type, holdable_id: record.id })
    .whereNull('released_at').first();
  return !!hold;
}

/** Applies active retention policies. Inactive policies do nothing; records
 *  under legal hold are NEVER touched; 'review' only flags via audit. */
export async function enforceRetention({ dryRun = false } = {}) {
  const actions = [];
  const policies = await db('retention_policies').where({ active: true }).whereNotNull('retain_months');

  for (const policy of policies) {
    const cutoff = new Date();
    cutoff.setMonth(cutoff.getMonth() - policy.retain_months);

    let candidates = [];
    let type = null;
    switch (policy.record_type) {
      case 'leads':
        type = 'lead';
        candidates = await db('leads').where('status', 'closed').where('updated_at', '<', cutoff).whereNull('deleted_at');
        break;
      case 'closed_cases':
        type = 'case';
        candidates = await db('cases').whereNotNull('closed_at').where('closed_at', '<', cutoff).whereNull('deleted_at');
        break;
      case 'documents':
        type = 'document';
        candidates = await db('documents').where('created_at', '<', cutoff).whereNull('deleted_at');
        break;
      case 'communications':
        type = 'communication';
        candidates = await db('communications').where('created_at', '<', cutoff);
        break;
      case 'exports':
        type = 'export_log';
        candidates = await db('export_logs').where('created_at', '<', cutoff);
        break;
      default: continue;
    }

    for (const record of candidates) {
      if (await underLegalHold(type, record)) continue;
      const label = `${policy.action_after}: ${type} #${record.id}`;
      actions.push(label);
      if (dryRun) continue;

      if (policy.action_after === 'review') {
        await audit('retention_review_flagged', { type, id: record.id });
      } else if (policy.action_after === 'anonymize') {
        if (type === 'lead') {
          await db('leads').where({ id: record.id }).update({
            first_name: 'Redacted', middle_name: null, last_name: 'Redacted',
            email: null, phone: null, description: null,
          });
        }
        await audit('retention_anonymized', { type, id: record.id });
      } else if (policy.action_after === 'delete') {
        if (type === 'document' && record.path) {
          try { fs.unlinkSync(path.join('./storage/app/private-documents', record.path)); } catch { /* already gone */ }
        }
        await audit('retention_deleted', { type, id: record.id, newValues: { type, id: record.id } });
        const table = { lead: 'leads', case: 'cases', document: 'documents', communication: 'communications', export_log: 'export_logs' }[type];
        if (['leads', 'cases', 'documents'].includes(table)) {
          await db(table).where({ id: record.id }).update({ deleted_at: new Date() });
        } else {
          await db(table).where({ id: record.id }).del();
        }
      }
    }
  }
  return actions;
}
