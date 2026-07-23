import { Router } from 'express';
import multer from 'multer';
import db from '../db.js';
import { config } from '../config.js';
import { requireAuth, requireClient, clientOwnsCase } from '../middleware/auth.js';
import { audit } from '../services/audit.js';
import { storeDocument, documentAbsolutePath } from '../services/documents.js';
import { notifyUser } from '../services/mailer.js';
import { bus } from '../events.js';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.security.maxUploadMb * 1024 * 1024 },
  fileFilter: (req, file, cb) => cb(null, config.security.allowedUploadMimes.includes(file.mimetype)),
});

const flash = (req, res, message, to) => { req.session.flash = message; res.redirect(to); };

/**
 * Client portal. Every case route passes clientOwnsCase (claimant must be
 * attached). Views receive only client-safe data: simplified status labels,
 * client-visible tasks/documents, own messages. Staff notes, skip-tracing
 * data, other claimants, and internal analysis are never queried here.
 */
export default function clientRoutes() {
  const router = Router();
  router.use(requireAuth, requireClient);

  router.get('/', async (req, res) => {
    const cases = req.user.claimant_id
      ? await db('case_claimant').where({ claimant_id: req.user.claimant_id })
          .join('cases', 'case_claimant.case_id', 'cases.id')
          .join('pipeline_stages', 'cases.pipeline_stage_id', 'pipeline_stages.id')
          .whereNull('cases.deleted_at')
          .select('cases.id', 'cases.case_number', 'pipeline_stages.client_label')
      : [];
    for (const caseRow of cases) {
      const [{ c }] = await db('document_requests').where({ case_id: caseRow.id, status: 'requested' }).count({ c: '*' });
      caseRow.documents_needed = Number(c);
    }
    res.render('client/dashboard', { cases });
  });

  router.get('/cases/:caseId', clientOwnsCase, async (req, res) => {
    const caseRow = req.caseRow;
    const stage = await db('pipeline_stages').where({ id: caseRow.pipeline_stage_id }).first();
    const claim = await db('claims').where({ case_id: caseRow.id }).orderBy('id', 'desc').first();
    const showPayments = ['Completed', 'Payment Being Processed'].includes(stage?.client_label);
    res.render('client/case', {
      caseRow,
      clientLabel: stage?.client_label ?? 'Information Received',
      claimSubmitted: !!claim?.filed_at,
      documentRequests: await db('document_requests').where({ case_id: caseRow.id }).whereIn('status', ['requested', 'received']),
      tasks: await db('case_tasks').where({ case_id: caseRow.id, client_visible: true }).whereNot('status', 'done'),
      documents: await db('documents').where({ case_id: caseRow.id, client_visible: true, status: 'approved' }).whereNull('deleted_at'),
      agreements: await db('agreements').where({ case_id: caseRow.id }).whereIn('status', ['sent', 'signed']),
      payments: showPayments ? await db('payments').where({ case_id: caseRow.id, payment_type: 'client_distribution' }) : [],
      messages: await db('portal_messages').leftJoin('users', 'portal_messages.user_id', 'users.id')
        .where({ case_id: caseRow.id })
        .select('portal_messages.*', 'users.name as sender_name', 'users.id as sender_id')
        .orderBy('portal_messages.created_at').limit(50),
    });
  });

  router.post('/cases/:caseId/documents', clientOwnsCase, upload.single('file'), async (req, res) => {
    if (!req.file) return flash(req, res, 'Please choose an allowed file type (PDF, image, or Word, up to 20 MB).', `/my/cases/${req.caseRow.id}`);
    const document = await storeDocument(req.file, {
      caseId: req.caseRow.id, claimantId: req.user.claimant_id,
      title: String(req.body.title ?? req.file.originalname).slice(0, 160),
      uploadedBy: req.user.id,
    });
    if (req.body.document_request_id) {
      await db('document_requests')
        .where({ id: req.body.document_request_id, case_id: req.caseRow.id, status: 'requested' })
        .update({ status: 'received', fulfilled_document_id: document.id });
    }
    flash(req, res, 'Thank you — your document was uploaded securely and is pending review.', `/my/cases/${req.caseRow.id}`);
  });

  router.get('/cases/:caseId/documents/:documentId/download', clientOwnsCase, async (req, res) => {
    const document = await db('documents').where({ id: req.params.documentId, case_id: req.caseRow.id }).whereNull('deleted_at').first();
    if (!document || !document.client_visible || document.status !== 'approved') return res.status(403).render('errors/403');
    if (document.virus_scan_status === 'infected') return res.status(403).render('errors/403');
    await audit('document_downloaded', { req, type: 'document', id: document.id, caseId: req.caseRow.id });
    res.download(documentAbsolutePath(document), document.original_filename);
  });

  router.post('/cases/:caseId/messages', clientOwnsCase, async (req, res) => {
    const body = String(req.body.body ?? '').trim().slice(0, 5000);
    if (!body) return flash(req, res, 'Please write a message first.', `/my/cases/${req.caseRow.id}`);
    await db('portal_messages').insert({ case_id: req.caseRow.id, user_id: req.user.id, body });
    if (req.caseRow.case_manager_id) {
      await notifyUser(req.caseRow.case_manager_id, `New client message on ${req.caseRow.case_number}`, body.slice(0, 200), req.caseRow.case_number);
    }
    await bus.emitDomain('client.message_received', { caseId: req.caseRow.id });
    flash(req, res, 'Your message has been sent to your case manager.', `/my/cases/${req.caseRow.id}`);
  });

  router.get('/cases/:caseId/agreements/:agreementId', clientOwnsCase, async (req, res) => {
    const agreement = await db('agreements').where({ id: req.params.agreementId, case_id: req.caseRow.id })
      .whereIn('status', ['sent', 'signed']).first();
    if (!agreement) return res.status(404).render('errors/404');
    await audit('agreement_viewed', { req, type: 'agreement', id: agreement.id, caseId: req.caseRow.id });
    res.render('client/agreement', { caseRow: req.caseRow, agreement });
  });

  router.post('/cases/:caseId/agreements/:agreementId/sign', clientOwnsCase, async (req, res) => {
    const agreement = await db('agreements').where({ id: req.params.agreementId, case_id: req.caseRow.id }).first();
    if (!agreement || agreement.status !== 'sent') {
      return res.status(422).send('This agreement is not available for signature.');
    }
    const signatureName = String(req.body.signature_name ?? '').trim();
    if (!signatureName || !req.body.agree) {
      return flash(req, res, 'Please type your full legal name and confirm your agreement.', `/my/cases/${req.caseRow.id}/agreements/${agreement.id}`);
    }
    await db('agreements').where({ id: agreement.id }).update({
      status: 'signed', signed_at: new Date(),
      signature_name: signatureName.slice(0, 160), signature_ip: req.ip,
      signature_consent_text: 'Signed electronically by typing my name, with consent to electronic records and signatures.',
    });
    await audit('agreement_signed', { req, type: 'agreement', id: agreement.id, caseId: req.caseRow.id });
    await bus.emitDomain('agreement.signed', { caseId: req.caseRow.id, context: { agreement_id: agreement.id } });
    flash(req, res, 'Agreement signed. A copy is available in your documents.', `/my/cases/${req.caseRow.id}`);
  });

  // Profile & preferences ---------------------------------------------------
  router.get('/profile', async (req, res) => {
    const claimant = req.user.claimant_id ? await db('claimants').where({ id: req.user.claimant_id }).first() : null;
    const contact = claimant ? {
      email: (await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimant.id }).first())?.email,
      phone: (await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimant.id }).first())?.number,
      address: await db('addresses').where({ addressable_type: 'claimant', addressable_id: claimant.id }).first(),
    } : {};
    res.render('client/profile', { contact });
  });

  router.put('/profile', async (req, res) => {
    const claimantId = req.user.claimant_id;
    if (!claimantId) return res.status(404).render('errors/404');
    const b = req.body;
    if (b.email) {
      const existing = await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimantId, label: 'primary' }).first();
      if (existing) await db('email_addresses').where({ id: existing.id }).update({ email: String(b.email).slice(0, 190) });
      else await db('email_addresses').insert({ emailable_type: 'claimant', emailable_id: claimantId, email: String(b.email).slice(0, 190) });
    }
    if (b.phone) {
      const existing = await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimantId, label: 'mobile' }).first();
      if (existing) await db('phone_numbers').where({ id: existing.id }).update({ number: String(b.phone).slice(0, 30) });
      else await db('phone_numbers').insert({ phoneable_type: 'claimant', phoneable_id: claimantId, number: String(b.phone).slice(0, 30) });
    }
    if (b.address_line1) {
      const values = {
        line1: String(b.address_line1).slice(0, 200), city: String(b.city ?? '').slice(0, 100),
        state: String(b.state ?? '').toUpperCase().slice(0, 2), zip: String(b.zip ?? '').slice(0, 10),
      };
      const existing = await db('addresses').where({ addressable_type: 'claimant', addressable_id: claimantId, label: 'mailing' }).first();
      if (existing) await db('addresses').where({ id: existing.id }).update(values);
      else await db('addresses').insert({ addressable_type: 'claimant', addressable_id: claimantId, label: 'mailing', ...values });
    }
    await audit('client_profile_updated', { req, type: 'claimant', id: claimantId });
    flash(req, res, 'Your contact details have been updated.', '/my/profile');
  });

  router.post('/profile/withdraw-sms', async (req, res) => {
    const claimantId = req.user.claimant_id;
    if (!claimantId) return res.status(404).render('errors/404');
    await db('consent_records').insert({
      consentable_type: 'claimant', consentable_id: claimantId, consent_type: 'sms',
      granted: false, source: 'portal', ip_address: req.ip, occurred_at: new Date(),
    });
    for (const phone of await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimantId })) {
      const exists = await db('opt_outs').where({ channel: 'sms', value: phone.number }).first();
      if (!exists) {
        await db('opt_outs').insert({
          channel: 'sms', value: phone.number, optoutable_type: 'claimant', optoutable_id: claimantId,
          reason: 'Withdrawn via client portal', occurred_at: new Date(),
        });
      }
    }
    await bus.emitDomain('sms.opt_out', { context: { claimant_id: claimantId } });
    await audit('consent_changed', { req, type: 'claimant', id: claimantId, newValues: { sms: false } });
    flash(req, res, 'You will no longer receive text messages from us.', '/my/profile');
  });

  router.post('/profile/stop-contact', async (req, res) => {
    const claimantId = req.user.claimant_id;
    if (!claimantId) return res.status(404).render('errors/404');
    const emails = await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimantId });
    const phones = await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimantId });
    for (const channel of ['email', 'sms', 'phone', 'mail']) {
      for (const value of [...emails.map((e) => e.email), ...phones.map((p) => p.number)]) {
        const exists = await db('opt_outs').where({ channel, value }).first();
        if (!exists) {
          await db('opt_outs').insert({
            channel, value, optoutable_type: 'claimant', optoutable_id: claimantId,
            reason: 'Stop-contact request via portal', occurred_at: new Date(),
          });
        }
      }
    }
    for (const pivot of await db('case_claimant').where({ claimant_id: claimantId })) {
      await db('cases').where({ id: pivot.case_id }).update({ outreach_approved: false });
      const caseRow = await db('cases').where({ id: pivot.case_id }).first();
      if (caseRow?.case_manager_id) {
        await notifyUser(caseRow.case_manager_id, `Client requested to stop contact on ${caseRow.case_number}`, '', caseRow.case_number);
      }
    }
    await audit('stop_contact_requested', { req, type: 'claimant', id: claimantId });
    flash(req, res, 'We have recorded your request. We will only contact you where legally required.', '/my/profile');
  });

  router.post('/profile/close-account', async (req, res) => {
    const claimantId = req.user.claimant_id;
    if (!claimantId) return res.status(404).render('errors/404');
    await audit('account_closure_requested', { req, type: 'claimant', id: claimantId });
    for (const pivot of await db('case_claimant').where({ claimant_id: claimantId })) {
      const caseRow = await db('cases').where({ id: pivot.case_id }).first();
      if (caseRow?.case_manager_id) {
        await notifyUser(caseRow.case_manager_id,
          `Client requested account closure (${caseRow.case_number}). Review retention and legal-hold rules before actioning.`, '', caseRow.case_number);
      }
    }
    flash(req, res, 'Your closure request has been recorded. A team member will confirm what is possible under our record-keeping obligations.', '/my/profile');
  });

  return router;
}
