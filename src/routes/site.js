import { Router } from 'express';
import multer from 'multer';
import db from '../db.js';
import { config } from '../config.js';
import { createLead } from '../services/leadIntake.js';
import { audit } from '../services/audit.js';
import { storeDocument } from '../services/documents.js';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.security.maxUploadMb * 1024 * 1024 },
  fileFilter: (req, file, cb) => cb(null, config.security.allowedUploadMimes.includes(file.mimetype)),
});

const PAGES = {
  '/': 'site/home',
  '/how-surplus-funds-work': 'site/how-it-works',
  '/our-process': 'site/process',
  '/faq': 'site/faq',
  '/about': 'site/about',
  '/why-we-contacted-you': 'site/why-contacted',
  '/schedule-a-call': 'site/schedule',
  '/professional-referrals': 'site/referrals',
  '/accessibility': 'site/legal/accessibility',
  '/privacy-policy': 'site/legal/privacy',
  '/terms-of-use': 'site/legal/terms',
  '/legal-disclaimer': 'site/legal/disclaimer',
  '/electronic-communications-consent': 'site/legal/e-consent',
  '/sms-terms': 'site/legal/sms-terms',
  '/document-security': 'site/legal/document-security',
  '/scam-awareness': 'site/scam-awareness',
};

async function matchCase(reference, lastName, zip) {
  const caseRow = await db('cases').where({ case_number: String(reference ?? '').toUpperCase().trim() }).whereNull('deleted_at').first();
  if (!caseRow) return null;
  const claimants = await db('case_claimant').where({ case_id: caseRow.id })
    .join('claimants', 'case_claimant.claimant_id', 'claimants.id').select('claimants.*');
  const nameMatch = claimants.some((c) => String(c.last_name ?? '').toLowerCase() === String(lastName ?? '').toLowerCase());
  const property = caseRow.property_id ? await db('properties').where({ id: caseRow.property_id }).first() : null;
  const zipDigits = String(zip ?? '').replace(/\D/g, '').slice(0, 5);
  const zipMatch = property ? String(property.zip ?? '').startsWith(zipDigits) : true;
  return nameMatch && zipMatch ? caseRow : null;
}

export default function siteRoutes(publicFormLimiter) {
  const router = Router();

  for (const [route, view] of Object.entries(PAGES)) {
    router.get(route, (req, res) => res.render(view));
  }

  router.get('/contact', (req, res) => res.render('site/contact'));
  router.post('/contact', publicFormLimiter, async (req, res) => {
    if (req.body.website) return res.redirect('/contact'); // honeypot
    const { name, email, phone, message } = req.body;
    if (!name || !email || !message) {
      req.session.flash = 'Please fill in your name, email, and message.';
      return res.redirect('/contact');
    }
    await db('communications').insert({
      channel: 'email', direction: 'inbound', sender: String(email).slice(0, 190),
      subject: `Website contact form: ${String(name).slice(0, 120)}`,
      body_rendered: `${String(message).slice(0, 5000)}\n\nPhone: ${phone || 'not given'}`,
      status: 'received',
    });
    req.session.flash = 'Thank you. Your message has been received and a member of our team will reply soon.';
    res.redirect('/contact');
  });

  // Inquiry form ------------------------------------------------------------
  router.get('/check-for-possible-funds', (req, res) => res.render('site/check'));

  router.post('/check-for-possible-funds', publicFormLimiter, async (req, res) => {
    const body = req.body;
    if (body.website) { // honeypot bot protection
      req.session.flash = 'Submission rejected.';
      return res.redirect('/check-for-possible-funds');
    }

    const errors = {};
    const required = ['first_name', 'last_name', 'relationship_to_owner', 'property_address', 'property_county', 'property_state', 'email', 'preferred_contact_method'];
    for (const field of required) if (!String(body[field] ?? '').trim()) errors[field] = 'This field is required.';
    if (body.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.email)) errors.email = 'Please enter a valid email address.';
    // Required consents; SMS consent is optional and separate — never required.
    for (const consent of ['consent_to_contact', 'privacy_policy_agreed', 'electronic_consent']) {
      if (!body[consent]) errors[consent] = 'This agreement is required.';
    }

    if (Object.keys(errors).length) {
      req.session.formErrors = errors;
      req.session.old = body;
      return res.redirect('/check-for-possible-funds');
    }

    const lead = await createLead({
      first_name: String(body.first_name).slice(0, 80),
      middle_name: String(body.middle_name ?? '').slice(0, 80) || null,
      last_name: String(body.last_name).slice(0, 80),
      former_owner_name: String(body.former_owner_name ?? '').slice(0, 160) || null,
      relationship_to_owner: String(body.relationship_to_owner).slice(0, 120),
      former_owner_living: body.former_owner_living === '1' ? true : body.former_owner_living === '0' ? false : null,
      property_address: String(body.property_address).slice(0, 200),
      property_county: String(body.property_county).slice(0, 80),
      property_state: String(body.property_state).slice(0, 2).toUpperCase(),
      approximate_sale_date: String(body.approximate_sale_date ?? '').slice(0, 40) || null,
      email: String(body.email).slice(0, 190),
      phone: String(body.phone ?? '').slice(0, 30) || null,
      preferred_contact_method: ['email', 'phone', 'mail'].includes(body.preferred_contact_method) ? body.preferred_contact_method : 'email',
      court_case_number: String(body.court_case_number ?? '').slice(0, 60) || null,
      parcel_number: String(body.parcel_number ?? '').slice(0, 60) || null,
      tax_account_number: String(body.tax_account_number ?? '').slice(0, 60) || null,
      description: String(body.description ?? '').slice(0, 5000) || null,
      consent_to_contact: true,
      privacy_policy_agreed: true,
      electronic_consent: true,
      sms_consent: !!body.sms_consent,
    }, req.ip);

    res.render('site/check-submitted', { lead });
  });

  // Letter verification: confirms authenticity ONLY — never case data.
  router.get('/verify-a-letter', (req, res) => res.render('site/verify', { checked: false, result: null }));

  router.post('/verify-a-letter', publicFormLimiter, async (req, res) => {
    const { reference, last_name: lastName, zip } = req.body;
    let result = null;

    const caseRow = await matchCase(reference, lastName, zip);
    if (caseRow) {
      const manager = caseRow.case_manager_id ? await db('users').where({ id: caseRow.case_manager_id }).first() : null;
      const assignee = caseRow.assigned_to ? await db('users').where({ id: caseRow.assigned_to }).first() : null;
      result = { reference: caseRow.case_number, representative: manager?.name ?? assignee?.name ?? 'Our intake team' };
    } else {
      const lead = await db('leads').where({ lead_number: String(reference ?? '').toUpperCase().trim() }).first();
      if (lead && String(lead.last_name).toLowerCase() === String(lastName ?? '').toLowerCase()) {
        const assignee = lead.assigned_to ? await db('users').where({ id: lead.assigned_to }).first() : null;
        result = { reference: lead.lead_number, representative: assignee?.name ?? 'Our intake team' };
      }
    }

    await audit('letter_verification_attempt', { req, newValues: { reference: String(reference ?? ''), matched: !!result } });
    res.render('site/verify', { checked: true, result });
  });

  // Public document upload (reference + last name + ZIP gate) ---------------
  router.get('/upload-documents', (req, res) =>
    res.render('site/upload', { verified: !!req.session.publicUploadCaseRef, uploaded: false, reference: req.session.publicUploadCaseRef ?? null }));

  router.post('/upload-documents/verify', publicFormLimiter, async (req, res) => {
    const caseRow = await matchCase(req.body.reference, req.body.last_name, req.body.zip);
    if (!caseRow) {
      req.session.flash = 'We could not match those details. Please check your letter or call us using the number on our Contact page.';
      return res.redirect('/upload-documents');
    }
    req.session.publicUploadCaseId = caseRow.id;
    req.session.publicUploadCaseRef = caseRow.case_number;
    req.session.publicUploadExpires = Date.now() + 30 * 60_000;
    res.redirect('/upload-documents');
  });

  router.post('/upload-documents', publicFormLimiter, upload.single('file'), async (req, res) => {
    const { publicUploadCaseId: caseId, publicUploadExpires: expires } = req.session;
    if (!caseId || (expires ?? 0) < Date.now()) return res.status(403).render('errors/403');
    if (!req.file || !String(req.body.title ?? '').trim()) {
      req.session.flash = 'Please choose a file and describe what it is.';
      return res.redirect('/upload-documents');
    }
    await storeDocument(req.file, { caseId, title: String(req.body.title).slice(0, 160) });
    res.render('site/upload', { verified: true, uploaded: true, reference: req.session.publicUploadCaseRef });
  });

  return router;
}
