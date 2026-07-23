import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { execFile } from 'node:child_process';
import db from '../db.js';
import { config } from '../config.js';
import { bus } from '../events.js';

const ROOT = './storage/app/private-documents';

/** All uploads land on a private, non-web-served path, are hashed, and pass
 *  an optional virus-scan hook before staff review. */
export async function storeDocument(file, { caseId = null, leadId = null, claimantId = null, title, category = 'general', clientVisible = false, uploadedBy = null }) {
  const dir = path.join(ROOT, String(caseId ?? 'unassigned'));
  fs.mkdirSync(dir, { recursive: true });
  const filename = `${Date.now()}-${crypto.randomBytes(8).toString('hex')}${path.extname(file.originalname).slice(0, 10)}`;
  const relative = path.join(String(caseId ?? 'unassigned'), filename);
  fs.writeFileSync(path.join(ROOT, relative), file.buffer);

  const [{ id }] = await db('documents').insert({
    case_id: caseId, lead_id: leadId, claimant_id: claimantId,
    category, title: title ?? file.originalname,
    original_filename: file.originalname, path: relative,
    mime_type: file.mimetype, size_bytes: file.size,
    sha256: crypto.createHash('sha256').update(file.buffer).digest('hex'),
    status: 'pending',
    virus_scan_status: config.security.virusScan.enabled ? 'pending' : 'skipped',
    uploaded_by: uploadedBy, client_visible: clientVisible,
  }, ['id']);

  if (config.security.virusScan.enabled) await scanDocument(id);

  await bus.emitDomain('document.uploaded', { caseId, leadId, context: { document_id: id } });
  return db('documents').where({ id }).first();
}

export function documentAbsolutePath(document) {
  return path.resolve(path.join(ROOT, document.path));
}

export async function scanDocument(documentId) {
  const document = await db('documents').where({ id: documentId }).first();
  const [cmd, ...args] = config.security.virusScan.command.split(' ');
  const clean = await new Promise((resolve) => {
    execFile(cmd, [...args, documentAbsolutePath(document)], { timeout: 120_000 }, (error) => resolve(!error));
  });
  await db('documents').where({ id: documentId }).update({ virus_scan_status: clean ? 'clean' : 'infected' });
  if (!clean) {
    await db('audit_events').insert({ event: 'virus_detected', auditable_type: 'document', auditable_id: documentId, created_at: new Date() });
  }
}
