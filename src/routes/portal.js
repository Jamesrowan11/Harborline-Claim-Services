import { Router } from 'express';
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import multer from 'multer';
import db from '../db.js';
import { config } from '../config.js';
import Settings from '../services/settings.js';
import { requireAuth, requireStaff, requireMfa, permission } from '../middleware/auth.js';
import { audit } from '../services/audit.js';
import { createLead } from '../services/leadIntake.js';
import { convertLeadToCase, countyCode } from '../services/caseConversion.js';
import { nextCaseNumber } from '../services/caseNumber.js';
import { changeStage } from '../services/stageChange.js';
import { findDuplicatesForLead } from '../services/duplicates.js';
import { storeDocument, documentAbsolutePath } from '../services/documents.js';
import { renderTemplate } from '../services/templates.js';
import { checkOutreach } from '../services/outreachGate.js';
import { enqueue } from '../services/queue.js';
import { REPORTS, reportColumns, reportRows } from '../services/reports.js';
import { toCsv, toXlsx, toPdf } from '../services/exports.js';
import { TRIGGERS, bus } from '../events.js';
import { SENSITIVE_ACTIONS } from '../automation/actions.js';
import { ROLES, can } from '../services/permissions.js';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.security.maxUploadMb * 1024 * 1024 },
  fileFilter: (req, file, cb) => cb(null, config.security.allowedUploadMimes.includes(file.mimetype)),
});

const flash = (req, res, message, to) => { req.session.flash = message; res.redirect(to); };
const staffList = () => db('users').where({ user_type: 'staff' }).whereNull('deactivated_at').orderBy('name');
const stageList = () => db('pipeline_stages').where({ active: true }).orderBy('sort_order');

async function primaryClaimant(caseId) {
  const pivot = await db('case_claimant').where({ case_id: caseId, role: 'primary' }).first();
  return pivot ? db('claimants').where({ id: pivot.claimant_id }).first() : null;
}

export default function portalRoutes() {
  const router = Router();
  router.use(requireAuth, requireMfa, requireStaff);

  // Dashboard ---------------------------------------------------------------
  router.get('/', async (req, res) => {
    const closedIds = (await db('pipeline_stages').where({ is_closed: true })).map((s) => s.id);
    const count = async (q) => Number((await q.count({ c: '*' }))[0].c);
    const sum = async (q, columnName) => Number((await q.sum({ s: columnName }))[0].s ?? 0);

    const widgets = {
      new_leads: await count(db('leads').where({ status: 'new' }).whereNull('deleted_at')),
      active_cases: await count(db('cases').whereNotIn('pipeline_stage_id', closedIds).whereNull('deleted_at')),
      estimated_surplus: await sum(db('cases').whereNotIn('pipeline_stage_id', closedIds).whereNull('deleted_at'), 'estimated_surplus'),
      verified_surplus: await sum(db('cases').whereNotIn('pipeline_stage_id', closedIds).whereNull('deleted_at'), 'verified_surplus'),
      claims_filed: await count(db('claims').whereNotNull('filed_at')),
      funds_received: await sum(db('payments').where({ payment_type: 'funds_received' }), 'amount'),
      client_distributions: await sum(db('payments').where({ payment_type: 'client_distribution' }), 'amount'),
      company_revenue: await sum(db('payments').where({ payment_type: 'company_fee' }), 'amount'),
      overdue_tasks: await count(db('case_tasks').whereNot('status', 'done').where('due_at', '<', new Date())),
      upcoming_deadlines: await count(db('deadlines').whereNull('met_at').whereBetween('due_at', [new Date(), new Date(Date.now() + 14 * 86400_000)])),
      stale_verifications: await count(db('surplus_records').where('verification_expires_at', '<', new Date())),
      missing_documents: await count(db('document_requests').where({ status: 'requested' })),
      automation_failures: await count(db('automation_runs').where({ status: 'failed' }).where('created_at', '>=', new Date(Date.now() - 7 * 86400_000))),
    };

    const casesByStage = await db('pipeline_stages')
      .leftJoin('cases', function () { this.on('cases.pipeline_stage_id', 'pipeline_stages.id').andOnNull('cases.deleted_at'); })
      .groupBy('pipeline_stages.id', 'pipeline_stages.name', 'pipeline_stages.sort_order')
      .select('pipeline_stages.name').count({ total: 'cases.id' })
      .orderBy('pipeline_stages.sort_order');

    const casesByCounty = await db('cases').whereNotNull('county').whereNull('deleted_at')
      .groupBy('county').select('county').count({ total: '*' }).orderBy('total', 'desc').limit(10);

    const myTasks = await db('case_tasks')
      .leftJoin('cases', 'case_tasks.case_id', 'cases.id')
      .where({ 'case_tasks.assigned_to': req.user.id }).whereNot('case_tasks.status', 'done')
      .select('case_tasks.*', 'cases.case_number').orderBy('case_tasks.due_at').limit(10);

    res.render('portal/dashboard', { widgets, casesByStage, casesByCounty, myTasks });
  });

  // Global search -----------------------------------------------------------
  router.get('/search', async (req, res) => {
    const q = String(req.query.q ?? '').trim();
    const results = [];
    if (q) {
      const like = `%${q}%`;
      if (can(req.user, 'cases.view')) {
        for (const c of await db('cases').where('case_number', 'like', like).orWhere('county', 'like', like).whereNull('deleted_at').limit(20)) {
          results.push({ type: 'Case', label: `${c.case_number} — ${c.county ?? ''}`, url: `/portal/cases/${c.id}` });
        }
        for (const p of await db('properties').where('address_line1', 'like', like).orWhere('parcel_number', 'like', like).orWhere('tax_account_number', 'like', like).limit(20)) {
          const c = await db('cases').where({ property_id: p.id }).first();
          if (c) results.push({ type: 'Property', label: `${p.address_line1}, ${p.county}`, url: `/portal/cases/${c.id}` });
        }
        for (const cl of await db('claimants')
          .where('first_name', 'like', like).orWhere('last_name', 'like', like)
          .orWhere('organization_name', 'like', like).orWhere('prior_names', 'like', like).limit(20)) {
          const pivot = await db('case_claimant').where({ claimant_id: cl.id }).first();
          results.push({
            type: 'Claimant',
            label: cl.organization_name || `${cl.first_name ?? ''} ${cl.last_name ?? ''}`.trim(),
            url: pivot ? `/portal/cases/${pivot.case_id}` : `/portal/cases?q=${encodeURIComponent(cl.last_name ?? '')}`,
          });
        }
        for (const cc of await db('court_cases').where('court_case_number', 'like', like).limit(10)) {
          const c = await db('cases').where({ court_case_id: cc.id }).first();
          if (c) results.push({ type: 'Court Case', label: cc.court_case_number, url: `/portal/cases/${c.id}` });
        }
        for (const s of await db('source_records').whereNotNull('case_id').where((qq) => qq.where('title', 'like', like).orWhere('reference', 'like', like)).limit(10)) {
          results.push({ type: 'Source Record', label: s.title, url: `/portal/cases/${s.case_id}` });
        }
      }
      if (can(req.user, 'leads.view')) {
        for (const l of await db('leads').where((qq) => qq
          .where('lead_number', 'like', like).orWhere('first_name', 'like', like)
          .orWhere('last_name', 'like', like).orWhere('former_owner_name', 'like', like)
          .orWhere('property_address', 'like', like).orWhere('email', 'like', like).orWhere('phone', 'like', like))
          .whereNull('deleted_at').limit(20)) {
          results.push({ type: 'Lead', label: `${l.lead_number} — ${l.first_name} ${l.last_name}`, url: `/portal/leads/${l.id}` });
        }
      }
    }
    res.render('portal/search', { q, results });
  });

  // Leads -------------------------------------------------------------------
  router.get('/leads', permission('leads.view'), async (req, res) => {
    const { q, status, county } = req.query;
    const perPage = Math.min(100, Number(req.query.per_page ?? 25));
    const page = Math.max(1, Number(req.query.page ?? 1));
    let query = db('leads').leftJoin('users as assignee', 'leads.assigned_to', 'assignee.id')
      .whereNull('leads.deleted_at').select('leads.*', 'assignee.name as assignee_name');
    if (status) query.where('leads.status', status);
    if (county) query.where('leads.property_county', county);
    if (q) {
      const like = `%${q}%`;
      query.where((qq) => qq.where('lead_number', 'like', like).orWhere('first_name', 'like', like)
        .orWhere('last_name', 'like', like).orWhere('property_address', 'like', like).orWhere('leads.email', 'like', like));
    }
    const leads = await query.orderBy('leads.created_at', 'desc').limit(perPage).offset((page - 1) * perPage);
    res.render('portal/leads/index', { leads, page, perPage });
  });

  router.get('/leads/create', permission('leads.create'), (req, res) => res.render('portal/leads/create'));

  router.post('/leads', permission('leads.create'), async (req, res) => {
    const b = req.body;
    if (!b.first_name || !b.last_name) return flash(req, res, 'First and last name are required.', '/portal/leads/create');
    const lead = await createLead({
      first_name: b.first_name, middle_name: b.middle_name || null, last_name: b.last_name,
      former_owner_name: b.former_owner_name || null, relationship_to_owner: b.relationship_to_owner || null,
      property_address: b.property_address || null, property_county: b.property_county || null,
      property_state: (b.property_state || '').toUpperCase() || null,
      email: b.email || null, phone: b.phone || null, description: b.description || null,
      consent_to_contact: !!b.consent_to_contact, privacy_policy_agreed: false, electronic_consent: false, sms_consent: false,
    }, req.ip, 'staff_entry');
    flash(req, res, `Lead ${lead.lead_number} created.`, `/portal/leads/${lead.id}`);
  });

  router.get('/leads/:id', permission('leads.view'), async (req, res) => {
    const lead = await db('leads').where({ id: req.params.id }).first();
    if (!lead) return res.status(404).render('errors/404');
    const [tasks, notes, consents, duplicates, staff, linkedCase] = await Promise.all([
      db('case_tasks').where({ lead_id: lead.id }),
      db('notes').leftJoin('users', 'notes.user_id', 'users.id').where({ notable_type: 'lead', notable_id: lead.id }).select('notes.*', 'users.name as author_name'),
      db('consent_records').where({ consentable_type: 'lead', consentable_id: lead.id }),
      findDuplicatesForLead(lead),
      staffList(),
      lead.case_id ? db('cases').where({ id: lead.case_id }).first() : null,
    ]);
    res.render('portal/leads/show', { lead, tasks, notes, consents, duplicates, staff, linkedCase });
  });

  router.put('/leads/:id', permission('leads.update'), async (req, res) => {
    const updates = {};
    if (req.body.assigned_to !== undefined) updates.assigned_to = req.body.assigned_to || null;
    if (['new', 'screening', 'duplicate', 'converted', 'closed'].includes(req.body.status)) updates.status = req.body.status;
    await db('leads').where({ id: req.params.id }).update({ ...updates, updated_at: new Date() });
    await audit('updated', { req, type: 'lead', id: Number(req.params.id), newValues: updates });
    flash(req, res, 'Lead updated.', `/portal/leads/${req.params.id}`);
  });

  router.post('/leads/:id/convert', permission('cases.create'), async (req, res, next) => {
    try {
      const caseRow = await convertLeadToCase(Number(req.params.id), req.user.id);
      flash(req, res, `Case ${caseRow.case_number} created from lead.`, `/portal/cases/${caseRow.id}`);
    } catch (error) { next(error); }
  });

  router.post('/leads/:id/mark-duplicate', permission('leads.update'), async (req, res) => {
    await db('leads').where({ id: req.params.id })
      .update({ status: 'duplicate', duplicate_of_lead_id: req.body.duplicate_of_lead_id || null });
    await audit('updated', { req, type: 'lead', id: Number(req.params.id), newValues: { status: 'duplicate' } });
    flash(req, res, 'Lead marked as duplicate.', `/portal/leads/${req.params.id}`);
  });

  // Cases -------------------------------------------------------------------
  router.get('/cases', permission('cases.view'), async (req, res) => {
    const { q, stage, county } = req.query;
    const perPage = Math.min(100, Number(req.query.per_page ?? 25));
    const page = Math.max(1, Number(req.query.page ?? 1));
    let query = db('cases')
      .leftJoin('pipeline_stages', 'cases.pipeline_stage_id', 'pipeline_stages.id')
      .leftJoin('properties', 'cases.property_id', 'properties.id')
      .leftJoin('users as assignee', 'cases.assigned_to', 'assignee.id')
      .whereNull('cases.deleted_at')
      .select('cases.*', 'pipeline_stages.name as stage_name', 'properties.address_line1', 'assignee.name as assignee_name');
    if (stage) query.where('pipeline_stages.key', stage);
    if (county) query.where('cases.county', county);
    if (q) {
      const like = `%${q}%`;
      query.where((qq) => qq.where('cases.case_number', 'like', like)
        .orWhere('cases.county', 'like', like).orWhere('properties.address_line1', 'like', like)
        .orWhere('properties.parcel_number', 'like', like));
    }
    const cases = await query.orderBy('cases.created_at', 'desc').limit(perPage).offset((page - 1) * perPage);
    res.render('portal/cases/index', { cases, stages: await stageList(), page, perPage });
  });

  router.get('/cases/create', permission('cases.create'), (req, res) => res.render('portal/cases/create'));

  router.post('/cases', permission('cases.create'), async (req, res) => {
    const b = req.body;
    if (!b.county || !b.state) return flash(req, res, 'County and state are required.', '/portal/cases/create');
    const code = await countyCode(b.county);
    const state = String(b.state).toUpperCase().slice(0, 2);
    let propertyId = null;
    if (b.address_line1) {
      [{ id: propertyId }] = await db('properties').insert({
        address_line1: b.address_line1, city: b.city || null, county: b.county,
        county_code: code, state, zip: b.zip || null, parcel_number: b.parcel_number || null,
      }, ['id']);
    }
    const stage = await db('pipeline_stages').where({ key: 'new_lead' }).first();
    const [{ id: caseId }] = await db('cases').insert({
      case_number: await nextCaseNumber(state, code),
      pipeline_stage_id: stage.id, property_id: propertyId,
      county: b.county, county_code: code, state,
      estimated_surplus: b.estimated_surplus || null,
      assigned_to: req.user.id, case_manager_id: req.user.id, last_activity_at: new Date(),
    }, ['id']);
    await audit('created', { req, type: 'case', id: caseId, caseId });
    await bus.emitDomain('case.created', { caseId });
    const caseRow = await db('cases').where({ id: caseId }).first();
    flash(req, res, `Case ${caseRow.case_number} created.`, `/portal/cases/${caseId}`);
  });

  router.get('/pipeline', permission('cases.view'), async (req, res) => {
    const stages = await db('pipeline_stages').where({ active: true, is_closed: false }).orderBy('sort_order');
    for (const stage of stages) {
      stage.cases = await db('cases')
        .leftJoin('users as assignee', 'cases.assigned_to', 'assignee.id')
        .where({ pipeline_stage_id: stage.id }).whereNull('cases.deleted_at')
        .select('cases.*', 'assignee.name as assignee_name')
        .orderBy('cases.last_activity_at', 'desc').limit(15);
      const [{ c }] = await db('cases').where({ pipeline_stage_id: stage.id }).whereNull('deleted_at').count({ c: '*' });
      stage.total = Number(c);
    }
    res.render('portal/pipeline', { stages: stages.filter((s) => s.total > 0) });
  });

  router.get('/cases/:id', permission('cases.view'), async (req, res) => {
    const caseRow = await db('cases').where({ id: req.params.id }).whereNull('deleted_at').first();
    if (!caseRow) return res.status(404).render('errors/404');
    const claimants = await db('case_claimant').where({ case_id: caseRow.id })
      .join('claimants', 'case_claimant.claimant_id', 'claimants.id')
      .select('claimants.*', 'case_claimant.role as pivot_role');
    for (const claimant of claimants) {
      claimant.email = (await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimant.id }).first())?.email;
      claimant.phone = (await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimant.id }).first())?.number;
    }
    const data = {
      caseRow, claimants,
      stage: await db('pipeline_stages').where({ id: caseRow.pipeline_stage_id }).first(),
      stages: await stageList(),
      staff: await staffList(),
      property: caseRow.property_id ? await db('properties').where({ id: caseRow.property_id }).first() : null,
      fundsHolder: caseRow.funds_holder_id ? await db('funds_holders').where({ id: caseRow.funds_holder_id }).first() : null,
      tasks: await db('case_tasks').where({ case_id: caseRow.id }),
      deadlines: await db('deadlines').where({ case_id: caseRow.id }).whereNull('met_at'),
      documents: await db('documents').where({ case_id: caseRow.id }).whereNull('deleted_at'),
      documentRequests: await db('document_requests').where({ case_id: caseRow.id }),
      sources: await db('source_records').where({ case_id: caseRow.id }),
      communications: await db('communications').where({ case_id: caseRow.id }).orderBy('created_at', 'desc').limit(20),
      claims: await db('claims').where({ case_id: caseRow.id }),
      payments: await db('payments').where({ case_id: caseRow.id }),
      notes: await db('notes').leftJoin('users', 'notes.user_id', 'users.id')
        .where({ notable_type: 'case', notable_id: caseRow.id })
        .select('notes.*', 'users.name as author_name').orderBy('notes.created_at', 'desc'),
      transitions: await db('stage_transitions')
        .leftJoin('pipeline_stages as toStage', 'stage_transitions.to_stage_id', 'toStage.id')
        .leftJoin('users', 'stage_transitions.user_id', 'users.id')
        .where({ case_id: caseRow.id })
        .select('stage_transitions.*', 'toStage.name as to_stage_name', 'users.name as user_name')
        .orderBy('stage_transitions.created_at', 'desc'),
      messages: await db('portal_messages').leftJoin('users', 'portal_messages.user_id', 'users.id')
        .where({ case_id: caseRow.id }).select('portal_messages.*', 'users.name as sender_name', 'users.user_type as sender_type')
        .orderBy('portal_messages.created_at', 'desc').limit(30),
    };
    res.render('portal/cases/show', data);
  });

  router.put('/cases/:id', permission('cases.update'), async (req, res) => {
    const b = req.body;
    const updates = {};
    for (const [field, valid] of Object.entries({
      assigned_to: null, case_manager_id: null, estimated_surplus: null, verified_surplus: null,
      verification_level: ['none', 'preliminary', 'source_confirmed', 'holder_confirmed'],
      risk_level: ['low', 'normal', 'elevated', 'high'],
      legal_complexity: ['standard', 'probate', 'heirship', 'trust', 'business', 'bankruptcy', 'disputed'],
    })) {
      if (b[field] !== undefined && b[field] !== '') {
        if (!valid || valid.includes(b[field])) updates[field] = b[field];
      }
    }
    for (const flag of ['compliance_hold', 'automation_paused']) {
      if (flag in b) updates[flag] = b[flag] === '1';
    }
    // Outreach approval is restricted to compliance reviewers.
    if ('outreach_approved' in b && can(req.user, 'compliance.review')) {
      updates.outreach_approved = b.outreach_approved === '1';
    }
    updates.last_activity_at = new Date();
    updates.updated_at = new Date();
    const before = await db('cases').where({ id: req.params.id }).first();
    await db('cases').where({ id: req.params.id }).update(updates);
    await audit('updated', { req, type: 'case', id: Number(req.params.id), caseId: Number(req.params.id), oldValues: before, newValues: updates });
    await bus.emitDomain('case.field_changed', { caseId: Number(req.params.id), context: { changed: Object.keys(updates) } });
    flash(req, res, 'Case updated.', `/portal/cases/${req.params.id}`);
  });

  router.post('/cases/:id/stage', permission('cases.change_stage'), async (req, res) => {
    await changeStage(Number(req.params.id), Number(req.body.stage_id), req.user.id, req.body.reason || null);
    flash(req, res, 'Stage updated.', `/portal/cases/${req.params.id}`);
  });

  router.post('/cases/:id/notes', permission('notes.manage'), async (req, res) => {
    if (String(req.body.body ?? '').trim()) {
      await db('notes').insert({
        notable_type: 'case', notable_id: Number(req.params.id),
        user_id: req.user.id, body: String(req.body.body).slice(0, 10000), is_staff_only: true,
      });
      await db('cases').where({ id: req.params.id }).update({ last_activity_at: new Date() });
    }
    flash(req, res, 'Note added.', `/portal/cases/${req.params.id}`);
  });

  // Tasks -------------------------------------------------------------------
  router.get('/tasks', permission('tasks.view'), async (req, res) => {
    const filter = req.query.filter;
    let query = db('case_tasks')
      .leftJoin('cases', 'case_tasks.case_id', 'cases.id')
      .leftJoin('leads', 'case_tasks.lead_id', 'leads.id')
      .leftJoin('users as assignee', 'case_tasks.assigned_to', 'assignee.id')
      .select('case_tasks.*', 'cases.case_number', 'leads.lead_number', 'assignee.name as assignee_name')
      .whereNot('case_tasks.status', 'done');
    if (filter === 'mine') query.where('case_tasks.assigned_to', req.user.id);
    if (filter === 'overdue') query.where('case_tasks.due_at', '<', new Date());
    const tasks = await query.orderBy('case_tasks.due_at').limit(100);
    res.render('portal/tasks/index', { tasks, filter });
  });

  router.get('/tasks/create', permission('tasks.manage'), async (req, res) =>
    res.render('portal/tasks/create', { staff: await staffList(), caseId: req.query.case_id ?? null }));

  router.post('/tasks', permission('tasks.manage'), async (req, res) => {
    const b = req.body;
    if (!b.title) return flash(req, res, 'A title is required.', '/portal/tasks/create');
    await db('case_tasks').insert({
      title: String(b.title).slice(0, 200), description: b.description || null,
      case_id: b.case_id || null, assigned_to: b.assigned_to || null,
      priority: ['low', 'normal', 'high', 'urgent'].includes(b.priority) ? b.priority : 'normal',
      due_at: b.due_at ? new Date(b.due_at) : null, created_by: req.user.id,
    });
    flash(req, res, 'Task created.', '/portal/tasks');
  });

  router.post('/tasks/:id/complete', permission('tasks.manage'), async (req, res) => {
    const task = await db('case_tasks').where({ id: req.params.id }).first();
    await db('case_tasks').where({ id: req.params.id }).update({ status: 'done', completed_at: new Date() });
    if (task?.case_id) await db('cases').where({ id: task.case_id }).update({ last_activity_at: new Date() });
    flash(req, res, 'Task completed.', req.get('referer') ?? '/portal/tasks');
  });

  // Calendar ----------------------------------------------------------------
  router.get('/calendar', permission('tasks.view'), async (req, res) => {
    const month = req.query.month ? new Date(`${req.query.month}-01T00:00:00`) : new Date();
    const start = new Date(month.getFullYear(), month.getMonth(), 1);
    const end = new Date(month.getFullYear(), month.getMonth() + 1, 0, 23, 59, 59);
    const tasks = await db('case_tasks').leftJoin('cases', 'case_tasks.case_id', 'cases.id')
      .whereBetween('case_tasks.due_at', [start, end]).select('case_tasks.*', 'cases.case_number');
    const deadlines = await db('deadlines').leftJoin('cases', 'deadlines.case_id', 'cases.id')
      .whereBetween('deadlines.due_at', [start, end]).select('deadlines.*', 'cases.case_number');
    res.render('portal/calendar', { tasks, deadlines, start });
  });

  // Documents ---------------------------------------------------------------
  router.get('/documents', permission('documents.view'), async (req, res) => {
    let query = db('documents').leftJoin('cases', 'documents.case_id', 'cases.id')
      .whereNull('documents.deleted_at').select('documents.*', 'cases.case_number');
    if (req.query.status) query.where('documents.status', req.query.status);
    const documents = await query.orderBy('documents.created_at', 'desc').limit(100);
    res.render('portal/documents/index', { documents });
  });

  router.post('/documents', permission('documents.upload'), upload.single('file'), async (req, res) => {
    if (!req.file) return flash(req, res, 'Choose an allowed file type (PDF, image, or Word).', req.get('referer') ?? '/portal/documents');
    await storeDocument(req.file, {
      caseId: Number(req.body.case_id), title: String(req.body.title ?? req.file.originalname).slice(0, 160),
      category: ['identity', 'agreement', 'court', 'property', 'correspondence', 'general'].includes(req.body.category) ? req.body.category : 'general',
      clientVisible: req.body.client_visible === '1', uploadedBy: req.user.id,
    });
    flash(req, res, 'Document uploaded and pending review.', req.get('referer') ?? '/portal/documents');
  });

  router.get('/documents/:id/download', permission('documents.download'), async (req, res) => {
    const document = await db('documents').where({ id: req.params.id }).whereNull('deleted_at').first();
    if (!document) return res.status(404).render('errors/404');
    if (document.virus_scan_status === 'infected') return res.status(403).render('errors/403');
    await audit('document_downloaded', { req, type: 'document', id: document.id, caseId: document.case_id });
    res.download(documentAbsolutePath(document), document.original_filename);
  });

  router.post('/documents/:id/review', permission('documents.approve'), async (req, res) => {
    const status = req.body.status === 'approved' ? 'approved' : 'rejected';
    const document = await db('documents').where({ id: req.params.id }).first();
    await db('documents').where({ id: req.params.id }).update({
      status, review_notes: req.body.review_notes || null, reviewed_by: req.user.id, reviewed_at: new Date(),
    });
    await audit(`document_${status}`, { req, type: 'document', id: Number(req.params.id), caseId: document?.case_id });
    await bus.emitDomain(status === 'approved' ? 'document.approved' : 'document.rejected',
      { caseId: document?.case_id, context: { document_id: document?.id } });
    flash(req, res, `Document ${status}.`, req.get('referer') ?? '/portal/documents');
  });

  // Communications ----------------------------------------------------------
  router.get('/communications', permission('communications.view'), async (req, res) => {
    let query = db('communications').leftJoin('cases', 'communications.case_id', 'cases.id')
      .select('communications.*', 'cases.case_number');
    if (req.query.channel) query.where('communications.channel', req.query.channel);
    if (req.query.status) query.where('communications.status', req.query.status);
    const communications = await query.orderBy('communications.created_at', 'desc').limit(100);
    res.render('portal/communications/index', { communications });
  });

  router.get('/communications/compose', permission('communications.send'), async (req, res) => {
    const templates = await db('document_templates').where({ active: true }).whereIn('category', ['letter', 'email', 'sms']).orderBy('name');
    let preview = null;
    let caseRow = null;
    if (req.query.case_id && req.query.template_id) {
      caseRow = await db('cases').where({ id: req.query.case_id }).first();
      const template = templates.find((t) => t.id === Number(req.query.template_id));
      if (caseRow && template) {
        preview = await renderTemplate(template, caseRow, await primaryClaimant(caseRow.id));
      }
    }
    res.render('portal/communications/compose', { templates, preview, caseRow });
  });

  router.post('/communications', permission('communications.send'), async (req, res) => {
    const caseRow = await db('cases').where({ id: req.body.case_id }).first();
    const template = await db('document_templates').where({ id: req.body.template_id }).first();
    if (!caseRow || !template) return flash(req, res, 'Case and template are required.', '/portal/communications/compose');
    const claimant = await primaryClaimant(caseRow.id);
    const rendered = await renderTemplate(template, caseRow, claimant);
    const channel = ['email', 'sms', 'letter'].includes(req.body.channel) ? req.body.channel : 'letter';
    const recipient = channel === 'email'
      ? (claimant && (await db('email_addresses').where({ emailable_type: 'claimant', emailable_id: claimant.id, opted_out: false }).first())?.email)
      : channel === 'sms'
        ? (claimant && (await db('phone_numbers').where({ phoneable_type: 'claimant', phoneable_id: claimant.id, sms_capable: true }).first())?.number)
        : null;
    await db('communications').insert({
      case_id: caseRow.id, claimant_id: claimant?.id ?? null, channel, direction: 'outbound',
      recipient, subject: rendered.subject, body_rendered: rendered.body,
      document_template_id: template.id, template_version: template.version,
      status: 'draft', generated_by: req.user.id,
    });
    flash(req, res, 'Draft created. It must be approved before sending.', '/portal/communications');
  });

  router.post('/communications/:id/approve', permission('communications.approve'), async (req, res) => {
    await db('communications').where({ id: req.params.id, status: 'draft' })
      .update({ approved_by: req.user.id, approved_at: new Date() });
    flash(req, res, 'Communication approved. It can now be sent.', '/portal/communications');
  });

  router.post('/communications/:id/send', permission('communications.send'), async (req, res) => {
    const communication = await db('communications').where({ id: req.params.id }).first();
    if (!communication?.approved_at || !['draft', 'failed'].includes(communication.status)) {
      return flash(req, res, 'Communication must be approved before sending.', '/portal/communications');
    }
    if (['email', 'sms'].includes(communication.channel)) {
      const template = communication.document_template_id ? await db('document_templates').where({ id: communication.document_template_id }).first() : null;
      const caseRow = communication.case_id ? await db('cases').where({ id: communication.case_id }).first() : null;
      const lead = communication.lead_id ? await db('leads').where({ id: communication.lead_id }).first() : null;
      const claimant = communication.claimant_id ? await db('claimants').where({ id: communication.claimant_id }).first() : null;
      const gate = await checkOutreach(communication.channel, template, caseRow, lead, claimant);
      if (gate !== true) return flash(req, res, `Blocked by outreach rules: ${gate}`, '/portal/communications');
    }
    await db('communications').where({ id: communication.id }).update({ status: 'queued' });
    await enqueue('send_communication', { communicationId: communication.id });
    flash(req, res, 'Communication queued for delivery.', '/portal/communications');
  });

  // Automations -------------------------------------------------------------
  router.get('/automations', permission('automations.view'), async (req, res) => {
    const automations = await db('automations').whereNull('deleted_at').orderBy('name');
    for (const automation of automations) {
      const [{ c: runs }] = await db('automation_runs').where({ automation_id: automation.id }).count({ c: '*' });
      const [{ c: failures }] = await db('automation_runs').where({ automation_id: automation.id, status: 'failed' }).count({ c: '*' });
      automation.runs_count = Number(runs); automation.failed_count = Number(failures);
    }
    res.render('portal/automations/index', {
      automations, globalStop: !!(await Settings.get('automation_global_stop', false)),
    });
  });

  router.get('/automations/create', permission('automations.manage'), (req, res) =>
    res.render('portal/automations/edit', { automation: null, runs: [], versions: [], triggers: TRIGGERS }));

  const parseAutomation = (body) => {
    const conditions = JSON.parse(body.conditions_json || '[]');
    const actions = JSON.parse(body.actions_json || '[]');
    return {
      name: String(body.name ?? '').slice(0, 160),
      description: body.description || null,
      trigger_event: TRIGGERS.includes(body.trigger_event) ? body.trigger_event : TRIGGERS[0],
      conditions: JSON.stringify(conditions),
      actions: JSON.stringify(actions),
      max_runs_per_hour: Math.max(1, Number(body.max_runs_per_hour ?? 100)),
      max_runs_per_case_per_day: Math.max(1, Number(body.max_runs_per_case_per_day ?? 5)),
      is_sensitive: actions.some((a) => SENSITIVE_ACTIONS.includes(a.type)),
    };
  };

  router.post('/automations', permission('automations.manage'), async (req, res, next) => {
    try {
      const data = parseAutomation(req.body);
      const [{ id }] = await db('automations').insert({ ...data, mode: 'draft', created_by: req.user.id }, ['id']);
      await db('automation_versions').insert({ automation_id: id, version: 1, definition: JSON.stringify(data), created_by: req.user.id });
      await audit('automation_created', { req, type: 'automation', id });
      flash(req, res, 'Automation created in draft mode.', `/portal/automations/${id}`);
    } catch (error) { error.status = 422; next(error); }
  });

  router.get('/automations/:id', permission('automations.view'), async (req, res) => {
    const automation = await db('automations').where({ id: req.params.id }).first();
    if (!automation) return res.status(404).render('errors/404');
    const runs = await db('automation_runs').where({ automation_id: automation.id }).orderBy('id', 'desc').limit(25);
    const versions = await db('automation_versions').where({ automation_id: automation.id }).orderBy('version', 'desc').limit(10);
    res.render('portal/automations/edit', { automation, runs, versions, triggers: TRIGGERS });
  });

  router.put('/automations/:id', permission('automations.manage'), async (req, res, next) => {
    try {
      const automation = await db('automations').where({ id: req.params.id }).first();
      const data = parseAutomation(req.body);
      data.version = automation.version + 1;
      if (data.is_sensitive) { // any edit to a sensitive automation clears approval
        data.approved_by = null; data.approved_at = null;
        if (automation.mode === 'active') data.mode = 'draft';
      }
      await db('automations').where({ id: automation.id }).update({ ...data, updated_at: new Date() });
      await db('automation_versions').insert({ automation_id: automation.id, version: data.version, definition: JSON.stringify(data), created_by: req.user.id });
      await audit('automation_updated', { req, type: 'automation', id: automation.id });
      flash(req, res, `Automation updated (version ${data.version}).`, `/portal/automations/${automation.id}`);
    } catch (error) { error.status = 422; next(error); }
  });

  router.post('/automations/:id/mode', permission('automations.approve'), async (req, res) => {
    const mode = ['draft', 'test', 'approval', 'active'].includes(req.body.mode) ? req.body.mode : 'draft';
    const updates = { mode, paused: req.body.paused === '1' };
    const automation = await db('automations').where({ id: req.params.id }).first();
    if (['active', 'approval'].includes(mode) && automation.is_sensitive && !automation.approved_at) {
      updates.approved_by = req.user.id; updates.approved_at = new Date();
    }
    await db('automations').where({ id: req.params.id }).update(updates);
    await audit('automation_mode_changed', { req, type: 'automation', id: Number(req.params.id), newValues: updates });
    flash(req, res, `Automation mode set to ${mode}.`, `/portal/automations/${req.params.id}`);
  });

  router.post('/automations/emergency-stop', permission('automations.approve'), async (req, res) => {
    const stop = req.body.stop !== '0';
    await Settings.set('automation_global_stop', stop, 'automations', req.user.id);
    await audit('automation_global_stop', { req, newValues: { stop } });
    flash(req, res, stop
      ? 'EMERGENCY STOP enabled — no automations will run until re-enabled.'
      : 'Emergency stop lifted.', '/portal/automations');
  });

  // Payments ----------------------------------------------------------------
  router.get('/payments', permission('payments.view'), async (req, res) => {
    let query = db('payments').leftJoin('cases', 'payments.case_id', 'cases.id')
      .leftJoin('users as recorder', 'payments.recorded_by', 'recorder.id')
      .select('payments.*', 'cases.case_number', 'recorder.name as recorder_name');
    if (req.query.type) query.where('payments.payment_type', req.query.type);
    if (req.query.from) query.where('payments.occurred_on', '>=', req.query.from);
    if (req.query.to) query.where('payments.occurred_on', '<=', req.query.to);
    const payments = await query.orderBy('payments.occurred_on', 'desc').limit(100);
    const totalsRows = await db('payments').groupBy('payment_type').select('payment_type').sum({ total: 'amount' });
    const totals = Object.fromEntries(totalsRows.map((r) => [r.payment_type, Number(r.total)]));
    res.render('portal/payments/index', { payments, totals });
  });

  router.post('/payments', permission('payments.manage'), async (req, res) => {
    const b = req.body;
    if (!b.case_id || !b.amount || !b.occurred_on) return flash(req, res, 'Case, amount, and date are required.', req.get('referer') ?? '/portal/payments');
    const [{ id }] = await db('payments').insert({
      case_id: Number(b.case_id),
      payment_type: ['funds_received', 'client_distribution', 'company_fee', 'refund'].includes(b.payment_type) ? b.payment_type : 'funds_received',
      amount: Number(b.amount), method: b.method || null, reference: b.reference || null,
      occurred_on: b.occurred_on, recorded_by: req.user.id, notes: b.notes || null,
    }, ['id']);
    await audit('payment_recorded', { req, type: 'payment', id, caseId: Number(b.case_id) });
    await bus.emitDomain('payment.entered', { caseId: Number(b.case_id), context: { payment_id: id, payment_type: b.payment_type } });
    flash(req, res, 'Payment recorded.', req.get('referer') ?? '/portal/payments');
  });

  // Reports & Print Center --------------------------------------------------
  router.get('/reports', permission('reports.view'), async (req, res) => {
    const from = req.query.from ? new Date(req.query.from) : new Date(Date.now() - 182 * 86400_000);
    const to = req.query.to ? new Date(req.query.to) : new Date();
    const count = async (q) => Number((await q.count({ c: '*' }))[0].c);
    const funnel = {
      leads: await count(db('leads').whereBetween('created_at', [from, to])),
      cases: await count(db('cases').whereBetween('created_at', [from, to])),
      agreements_signed: await count(db('agreements').where({ status: 'signed' }).whereBetween('signed_at', [from, to])),
      claims_filed: await count(db('claims').whereBetween('filed_at', [from, to])),
      funds_received: await count(db('payments').where({ payment_type: 'funds_received' }).whereBetween('occurred_on', [from, to])),
    };
    const monthly = await db('payments').where({ payment_type: 'funds_received' }).whereBetween('occurred_on', [from, to]);
    const monthlyRecoveries = {};
    for (const p of monthly) {
      const key = String(p.occurred_on).slice(0, 7);
      monthlyRecoveries[key] = (monthlyRecoveries[key] ?? 0) + Number(p.amount);
    }
    const workload = await reportRows('employee_workload');
    res.render('portal/reports', { funnel, monthlyRecoveries, workload, from, to });
  });

  router.get('/print', permission('reports.export'), (req, res) => res.render('portal/print/index', { reports: REPORTS }));

  router.get('/print/:report', permission('reports.export'), async (req, res) => {
    const definition = REPORTS[req.params.report];
    if (!definition) return res.status(404).render('errors/404');
    const filters = { from: req.query.from, to: req.query.to, county: req.query.county };
    res.render('portal/print/show', {
      reportKey: req.params.report, reportName: definition.name,
      columns: reportColumns(req.params.report),
      rows: await reportRows(req.params.report, filters),
      filters,
      confidentiality: config.confidentialityFooter.replace('{name}', req.user.name),
    });
  });

  router.get('/print/:report/export', permission('reports.export'), async (req, res, next) => {
    try {
      const definition = REPORTS[req.params.report];
      if (!definition) return res.status(404).render('errors/404');
      const format = ['csv', 'xlsx', 'pdf'].includes(req.query.format) ? req.query.format : 'xlsx';
      const filters = { from: req.query.from, to: req.query.to, county: req.query.county };
      const rows = await reportRows(req.params.report, filters);
      const filename = `${req.params.report}-${new Date().toISOString().slice(0, 10)}`;

      await db('export_logs').insert({
        user_id: req.user.id, report_key: req.params.report, format,
        filters: JSON.stringify(filters), row_count: rows.length,
      });
      await audit('export_created', { req, newValues: { report: req.params.report, format, rows: rows.length } });

      if (format === 'csv') {
        res.set('Content-Type', 'text/csv').set('Content-Disposition', `attachment; filename="${filename}.csv"`);
        return res.send(await toCsv(req.params.report, filters));
      }
      if (format === 'pdf') {
        const buffer = await toPdf(req.params.report, definition.name, filters, req.user.name,
          { orientation: req.query.orientation, paper: req.query.paper });
        res.set('Content-Type', 'application/pdf').set('Content-Disposition', `attachment; filename="${filename}.pdf"`);
        return res.send(buffer);
      }
      const buffer = await toXlsx(req.params.report, definition.name, filters, req.user.name);
      res.set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        .set('Content-Disposition', `attachment; filename="${filename}.xlsx"`);
      res.send(Buffer.from(buffer));
    } catch (error) { next(error); }
  });

  // Administration ----------------------------------------------------------
  router.get('/admin', permission('admin.settings'), async (req, res) => {
    res.render('portal/admin/settings', {
      brandValues: await Settings.brandAll(),
      caseNumberFormat: (await Settings.get('case_number_format')) ?? config.caseNumberFormat,
      countiesServed: (await Settings.get('counties_served', [])) ?? [],
      statesServed: (await Settings.get('states_served', ['MD'])) ?? [],
      assignmentRules: (await Settings.get('assignment_rules', [])) ?? [],
      outreachMinVerification: await Settings.get('outreach_min_verification_level', 1),
      outreachMaxPerWeek: await Settings.get('outreach_max_contacts_per_week', 2),
    });
  });

  router.post('/admin/settings', permission('admin.settings'), async (req, res) => {
    const b = req.body;
    for (const key of Object.keys(config.brand)) {
      if (b[`brand_${key}`] !== undefined) await Settings.set(`brand.${key}`, String(b[`brand_${key}`]).slice(0, 300), 'branding', req.user.id);
    }
    if (b.case_number_format) await Settings.set('case_number_format', b.case_number_format, 'cases', req.user.id);
    if (b.counties_served !== undefined) {
      await Settings.set('counties_served', String(b.counties_served).split(',').map((s) => s.trim()).filter(Boolean), 'service_area', req.user.id);
    }
    if (b.states_served !== undefined) {
      await Settings.set('states_served', String(b.states_served).split(',').map((s) => s.trim()).filter(Boolean), 'service_area', req.user.id);
    }
    if (b.assignment_rules_json) {
      try { await Settings.set('assignment_rules', JSON.parse(b.assignment_rules_json), 'leads', req.user.id); }
      catch { return flash(req, res, 'Assignment rules must be valid JSON.', '/portal/admin'); }
    }
    if (b.outreach_min_verification_level !== undefined) await Settings.set('outreach_min_verification_level', Number(b.outreach_min_verification_level), 'compliance', req.user.id);
    if (b.outreach_max_contacts_per_week !== undefined) await Settings.set('outreach_max_contacts_per_week', Number(b.outreach_max_contacts_per_week), 'compliance', req.user.id);
    await audit('settings_changed', { req, newValues: { keys: Object.keys(b).filter((k) => k !== '_csrf') } });
    flash(req, res, 'Settings saved.', '/portal/admin');
  });

  router.get('/admin/users', permission('admin.users'), async (req, res) => {
    res.render('portal/admin/users', { users: await db('users').orderBy('name'), roles: Object.keys(ROLES) });
  });

  router.post('/admin/users', permission('admin.users'), async (req, res) => {
    const { name, email, role, title } = req.body;
    if (!name || !email || !ROLES[role]) return flash(req, res, 'Name, email, and a valid role are required.', '/portal/admin/users');
    if (await db('users').where({ email: String(email).toLowerCase() }).first()) {
      return flash(req, res, 'That email address is already in use.', '/portal/admin/users');
    }
    const [{ id }] = await db('users').insert({
      name, email: String(email).toLowerCase(), title: title || null, role,
      user_type: role === 'Client' ? 'client' : 'staff',
      password: await bcrypt.hash(crypto.randomBytes(24).toString('hex'), 12),
    }, ['id']);
    await audit('user_created', { req, type: 'user', id, newValues: { role } });
    flash(req, res, 'User created. Send them the password-reset link from the login page to set their password.', '/portal/admin/users');
  });

  router.get('/admin/stages', permission('admin.settings'), async (req, res) =>
    res.render('portal/admin/stages', { stages: await db('pipeline_stages').orderBy('sort_order') }));

  router.post('/admin/stages/:id', permission('admin.settings'), async (req, res) => {
    await db('pipeline_stages').where({ id: req.params.id }).update({
      name: String(req.body.name ?? '').slice(0, 120),
      client_label: String(req.body.client_label ?? '').slice(0, 120),
      sort_order: Number(req.body.sort_order ?? 0),
      active: req.body.active === '1',
    });
    flash(req, res, 'Stage updated.', '/portal/admin/stages');
  });

  router.get('/admin/templates', permission('templates.manage'), async (req, res) =>
    res.render('portal/admin/templates', { templates: await db('document_templates').orderBy('category').orderBy('name') }));

  router.post('/admin/templates/:id', permission('templates.manage'), async (req, res) => {
    const template = await db('document_templates').where({ id: req.params.id }).first();
    const version = template.version + 1;
    await db('document_templates').where({ id: template.id }).update({
      subject: req.body.subject || null, body: String(req.body.body ?? ''),
      version, approval_status: 'draft', approved_by: null, approved_at: null, // edits require re-approval
    });
    await db('document_template_versions').insert({
      document_template_id: template.id, version, subject: req.body.subject || null,
      body: String(req.body.body ?? ''), created_by: req.user.id,
    });
    flash(req, res, `Template saved as version ${version} (requires re-approval before use).`, '/portal/admin/templates');
  });

  router.post('/admin/templates/:id/approve', permission('templates.approve'), async (req, res) => {
    await db('document_templates').where({ id: req.params.id })
      .update({ approval_status: 'approved', approved_by: req.user.id, approved_at: new Date() });
    await audit('template_approved', { req, type: 'document_template', id: Number(req.params.id) });
    flash(req, res, 'Template approved.', '/portal/admin/templates');
  });

  router.get('/admin/retention', permission('admin.retention'), async (req, res) => {
    const types = ['leads', 'closed_cases', 'documents', 'communications', 'audit_logs', 'exports', 'temp_files', 'identity_records', 'backups'];
    for (const type of types) {
      if (!(await db('retention_policies').where({ record_type: type }).first())) {
        await db('retention_policies').insert({ record_type: type });
      }
    }
    res.render('portal/admin/retention', { policies: await db('retention_policies').orderBy('record_type') });
  });

  router.post('/admin/retention', permission('admin.retention'), async (req, res) => {
    for (const [id, values] of Object.entries(req.body.policies ?? {})) {
      await db('retention_policies').where({ id }).update({
        retain_months: values.retain_months ? Number(values.retain_months) : null,
        action_after: ['review', 'anonymize', 'delete'].includes(values.action_after) ? values.action_after : 'review',
        active: values.active === '1',
      });
    }
    flash(req, res, 'Retention policies saved.', '/portal/admin/retention');
  });

  // Audit log ---------------------------------------------------------------
  router.get('/audit', permission('audit.view'), async (req, res) => {
    let query = db('audit_events').leftJoin('users', 'audit_events.user_id', 'users.id')
      .select('audit_events.*', 'users.name as user_name');
    if (req.query.event) query.where('audit_events.event', req.query.event);
    if (req.query.from) query.where('audit_events.created_at', '>=', new Date(req.query.from));
    if (req.query.to) query.where('audit_events.created_at', '<=', new Date(`${req.query.to}T23:59:59`));
    const events = await query.orderBy('audit_events.id', 'desc').limit(200);
    res.render('portal/audit', { events });
  });

  return router;
}
