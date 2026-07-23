import db from '../db.js';

export const CURRENCY = 'currency';
export const DATE = 'date';

const col = (label, value, format = null) => ({ label, value, format });
const dateOnly = (v) => (v ? new Date(v).toISOString().slice(0, 10) : null);
const yesNo = (v) => (v ? 'Yes' : 'No');

/**
 * Central definition of every printable/exportable spreadsheet. The Print &
 * Export Center, CSV/XLSX/PDF exporters, and print views all consume these
 * definitions, so output stays consistent.
 */
export const REPORTS = {
  master_cases: { name: 'Master Case Spreadsheet', group: 'Cases' },
  leads: { name: 'Lead Spreadsheet', group: 'Leads' },
  surplus_verification: { name: 'Surplus-Verification Spreadsheet', group: 'Research' },
  claimant_contact: { name: 'Claimant-Contact Spreadsheet', group: 'Outreach' },
  missing_documents: { name: 'Missing-Documents Spreadsheet', group: 'Documents' },
  attorney_review: { name: 'Attorney-Review Spreadsheet', group: 'Legal' },
  claim_status: { name: 'Claim-Status Spreadsheet', group: 'Claims' },
  payments: { name: 'Payment Spreadsheet', group: 'Financial' },
  revenue: { name: 'Revenue Spreadsheet', group: 'Financial' },
  employee_workload: { name: 'Employee Workload Spreadsheet', group: 'Team' },
  deadlines: { name: 'Deadline Spreadsheet', group: 'Work' },
  county_summary: { name: 'County-by-County Spreadsheet', group: 'Cases' },
  closed_cases: { name: 'Closed-Case Spreadsheet', group: 'Cases' },
  source_verification: { name: 'Source-Verification Report', group: 'Research' },
  accounting_reconciliation: { name: 'Accounting Reconciliation Report', group: 'Financial' },
  audit: { name: 'Audit Report', group: 'Compliance' },
};

const CASE_COLUMNS = [
  col('Case Number', (r) => r.case_number),
  col('Stage', (r) => r.stage_name),
  col('County', (r) => r.county),
  col('State', (r) => r.state),
  col('Primary Claimant', (r) => r.claimant_name),
  col('Property', (r) => r.property_address),
  col('Estimated Surplus', (r) => r.estimated_surplus, CURRENCY),
  col('Verified Surplus', (r) => r.verified_surplus, CURRENCY),
  col('Verification', (r) => r.verification_level),
  col('Assigned To', (r) => r.assignee_name),
  col('Opened', (r) => dateOnly(r.created_at), DATE),
  col('Closed', (r) => dateOnly(r.closed_at), DATE),
];

const PAYMENT_COLUMNS = [
  col('Case Number', (r) => r.case_number),
  col('Type', (r) => String(r.payment_type ?? '').replaceAll('_', ' ')),
  col('Amount', (r) => r.amount, CURRENCY),
  col('Method', (r) => r.method),
  col('Reference', (r) => r.reference),
  col('Date', (r) => dateOnly(r.occurred_on), DATE),
  col('Recorded By', (r) => r.recorder_name),
  col('Notes', (r) => r.notes),
];

export function reportColumns(report) {
  switch (report) {
    case 'master_cases': case 'closed_cases': case 'claimant_contact':
      return report === 'claimant_contact'
        ? [
            col('Case Number', (r) => r.case_number),
            col('Claimant', (r) => r.claimant_name),
            col('Outreach Approved', (r) => yesNo(r.outreach_approved)),
            col('Last Activity', (r) => dateOnly(r.last_activity_at), DATE),
            col('Assigned To', (r) => r.assignee_name),
            col('Stage', (r) => r.stage_name),
          ]
        : CASE_COLUMNS;
    case 'leads':
      return [
        col('Lead Number', (r) => r.lead_number),
        col('Status', (r) => r.status),
        col('Name', (r) => [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ')),
        col('Former Owner', (r) => r.former_owner_name),
        col('Property Address', (r) => r.property_address),
        col('County', (r) => r.property_county),
        col('State', (r) => r.property_state),
        col('Email', (r) => r.email),
        col('Phone', (r) => r.phone),
        col('Consent to Contact', (r) => yesNo(r.consent_to_contact)),
        col('Assigned To', (r) => r.assignee_name),
        col('Received', (r) => dateOnly(r.created_at), DATE),
      ];
    case 'surplus_verification':
      return [
        col('Case Number', (r) => r.case_number),
        col('Status', (r) => r.status),
        col('Estimated', (r) => r.estimated_amount, CURRENCY),
        col('Verified', (r) => r.verified_amount, CURRENCY),
        col('Funds Holder', (r) => r.holder_name),
        col('Verified By', (r) => r.verifier_name),
        col('Verified At', (r) => dateOnly(r.verified_at), DATE),
        col('Expires', (r) => dateOnly(r.verification_expires_at), DATE),
        col('Notes', (r) => r.verification_notes),
      ];
    case 'missing_documents':
      return [
        col('Case Number', (r) => r.case_number),
        col('Document', (r) => r.name),
        col('Claimant', (r) => r.claimant_name),
        col('Status', (r) => r.status),
        col('Requested', (r) => dateOnly(r.created_at), DATE),
        col('Due', (r) => dateOnly(r.due_at), DATE),
      ];
    case 'attorney_review':
      return [
        col('Case Number', (r) => r.case_number),
        col('Legal Complexity', (r) => r.legal_complexity),
        col('Stage', (r) => r.stage_name),
        col('Attorney', (r) => r.attorney_name),
        col('County', (r) => r.county),
        col('Verified Surplus', (r) => r.verified_surplus, CURRENCY),
        col('Opened', (r) => dateOnly(r.created_at), DATE),
      ];
    case 'claim_status':
      return [
        col('Case Number', (r) => r.case_number),
        col('Claim Status', (r) => r.status),
        col('Funds Holder', (r) => r.holder_name),
        col('Amount Claimed', (r) => r.amount_claimed, CURRENCY),
        col('Amount Approved', (r) => r.amount_approved, CURRENCY),
        col('Filed', (r) => dateOnly(r.filed_at), DATE),
        col('Decision', (r) => dateOnly(r.decision_at), DATE),
      ];
    case 'payments': case 'revenue': case 'accounting_reconciliation':
      return PAYMENT_COLUMNS;
    case 'employee_workload':
      return [
        col('Employee', (r) => r.name),
        col('Title', (r) => r.title),
        col('Open Cases', (r) => r.open_cases),
        col('Open Tasks', (r) => r.open_tasks),
        col('Overdue Tasks', (r) => r.overdue_tasks),
      ];
    case 'deadlines':
      return [
        col('Case Number', (r) => r.case_number),
        col('Deadline', (r) => r.name),
        col('Due', (r) => dateOnly(r.due_at), DATE),
        col('Critical', (r) => yesNo(r.is_critical)),
        col('Source', (r) => r.source),
        col('Met', (r) => dateOnly(r.met_at), DATE),
      ];
    case 'county_summary':
      return [
        col('County', (r) => r.county),
        col('State', (r) => r.state),
        col('Cases', (r) => r.total_cases),
        col('Estimated Surplus', (r) => r.total_estimated, CURRENCY),
        col('Verified Surplus', (r) => r.total_verified, CURRENCY),
      ];
    case 'source_verification':
      return [
        col('Case Number', (r) => r.case_number),
        col('Source Type', (r) => r.source_type),
        col('Title', (r) => r.title),
        col('Reference', (r) => r.reference),
        col('Retrieved', (r) => dateOnly(r.retrieved_at), DATE),
        col('Stale After', (r) => dateOnly(r.stale_after), DATE),
        col('Status', (r) => r.status),
      ];
    case 'audit':
      return [
        col('When', (r) => (r.created_at ? new Date(r.created_at).toISOString().replace('T', ' ').slice(0, 19) : null)),
        col('User', (r) => r.user_name ?? 'System'),
        col('Event', (r) => r.event),
        col('Record', (r) => (r.auditable_type ? `${r.auditable_type} #${r.auditable_id}` : '—')),
        col('IP', (r) => r.ip_address),
      ];
    default:
      throw new Error(`Unknown report: ${report}`);
  }
}

export async function reportQuery(report, filters = {}) {
  const from = filters.from ? new Date(filters.from) : null;
  const to = filters.to ? new Date(new Date(filters.to).setHours(23, 59, 59)) : null;
  const between = (q, column) => {
    if (from) q.where(column, '>=', from);
    if (to) q.where(column, '<=', to);
    return q;
  };
  const closedIds = (await db('pipeline_stages').where({ is_closed: true })).map((s) => s.id);

  const caseBase = () => db('cases')
    .leftJoin('pipeline_stages', 'cases.pipeline_stage_id', 'pipeline_stages.id')
    .leftJoin('properties', 'cases.property_id', 'properties.id')
    .leftJoin('users as assignee', 'cases.assigned_to', 'assignee.id')
    .leftJoin('case_claimant', function () {
      this.on('case_claimant.case_id', 'cases.id').andOnVal('case_claimant.role', 'primary');
    })
    .leftJoin('claimants', 'case_claimant.claimant_id', 'claimants.id')
    .whereNull('cases.deleted_at')
    .select('cases.*',
      'pipeline_stages.name as stage_name',
      'properties.address_line1 as property_address',
      'assignee.name as assignee_name',
      db.raw("coalesce(claimants.organization_name, claimants.first_name || ' ' || claimants.last_name) as claimant_name"));

  switch (report) {
    case 'master_cases': {
      const q = caseBase().orderBy('cases.case_number');
      if (filters.county) q.where('cases.county', filters.county);
      return between(q, 'cases.created_at');
    }
    case 'closed_cases':
      return between(caseBase().whereIn('cases.pipeline_stage_id', closedIds), 'cases.closed_at').orderBy('cases.closed_at', 'desc');
    case 'claimant_contact':
      return caseBase().whereNotIn('cases.pipeline_stage_id', closedIds).orderBy('cases.case_number');
    case 'leads':
      return between(
        db('leads').leftJoin('users as assignee', 'leads.assigned_to', 'assignee.id')
          .whereNull('leads.deleted_at').select('leads.*', 'assignee.name as assignee_name'),
        'leads.created_at').orderBy('leads.created_at', 'desc');
    case 'surplus_verification':
      return between(
        db('surplus_records')
          .leftJoin('cases', 'surplus_records.case_id', 'cases.id')
          .leftJoin('funds_holders', 'surplus_records.funds_holder_id', 'funds_holders.id')
          .leftJoin('users as verifier', 'surplus_records.verified_by', 'verifier.id')
          .select('surplus_records.*', 'cases.case_number', 'funds_holders.name as holder_name', 'verifier.name as verifier_name'),
        'surplus_records.created_at');
    case 'missing_documents':
      return db('document_requests')
        .leftJoin('cases', 'document_requests.case_id', 'cases.id')
        .leftJoin('claimants', 'document_requests.claimant_id', 'claimants.id')
        .where('document_requests.status', 'requested')
        .select('document_requests.*', 'cases.case_number',
          db.raw("coalesce(claimants.organization_name, claimants.first_name || ' ' || claimants.last_name) as claimant_name"))
        .orderBy('document_requests.due_at');
    case 'attorney_review':
      return db('cases')
        .leftJoin('pipeline_stages', 'cases.pipeline_stage_id', 'pipeline_stages.id')
        .leftJoin('attorneys', 'cases.attorney_id', 'attorneys.id')
        .whereNull('cases.deleted_at')
        .where((q) => q.whereNot('cases.legal_complexity', 'standard').orWhere('pipeline_stages.requires_attorney_review', true))
        .select('cases.*', 'pipeline_stages.name as stage_name', 'attorneys.name as attorney_name')
        .orderBy('cases.case_number');
    case 'claim_status':
      return between(
        db('claims').leftJoin('cases', 'claims.case_id', 'cases.id')
          .leftJoin('funds_holders', 'claims.funds_holder_id', 'funds_holders.id')
          .select('claims.*', 'cases.case_number', 'funds_holders.name as holder_name'),
        'claims.created_at');
    case 'payments': case 'revenue': case 'accounting_reconciliation': {
      const q = db('payments').leftJoin('cases', 'payments.case_id', 'cases.id')
        .leftJoin('users as recorder', 'payments.recorded_by', 'recorder.id')
        .select('payments.*', 'cases.case_number', 'recorder.name as recorder_name')
        .orderBy('payments.occurred_on');
      if (report === 'revenue') q.where('payments.payment_type', 'company_fee');
      return between(q, 'payments.occurred_on');
    }
    case 'employee_workload': {
      const users = await db('users').where({ user_type: 'staff' }).whereNull('deactivated_at').orderBy('name');
      for (const user of users) {
        const [openCases] = await db('cases').where({ assigned_to: user.id }).whereNotIn('pipeline_stage_id', closedIds).whereNull('deleted_at').count({ c: '*' });
        const [openTasks] = await db('case_tasks').where({ assigned_to: user.id }).whereNot('status', 'done').count({ c: '*' });
        const [overdueTasks] = await db('case_tasks').where({ assigned_to: user.id }).whereNot('status', 'done').where('due_at', '<', new Date()).count({ c: '*' });
        user.open_cases = Number(openCases.c); user.open_tasks = Number(openTasks.c); user.overdue_tasks = Number(overdueTasks.c);
      }
      return users;
    }
    case 'deadlines':
      return between(
        db('deadlines').leftJoin('cases', 'deadlines.case_id', 'cases.id')
          .whereNull('deadlines.met_at').select('deadlines.*', 'cases.case_number'),
        'deadlines.due_at').orderBy('deadlines.due_at');
    case 'county_summary':
      return db('cases').whereNotNull('county').whereNull('deleted_at')
        .groupBy('county', 'state')
        .select('county', 'state')
        .count({ total_cases: '*' })
        .sum({ total_estimated: 'estimated_surplus', total_verified: 'verified_surplus' })
        .orderBy('county');
    case 'source_verification':
      return between(
        db('source_records').leftJoin('cases', 'source_records.case_id', 'cases.id')
          .select('source_records.*', 'cases.case_number'),
        'source_records.retrieved_at').orderBy('source_records.stale_after');
    case 'audit':
      return between(
        db('audit_events').leftJoin('users', 'audit_events.user_id', 'users.id')
          .select('audit_events.*', 'users.name as user_name'),
        'audit_events.created_at').orderBy('audit_events.id', 'desc').limit(5000);
    default:
      throw new Error(`Unknown report: ${report}`);
  }
}

export async function reportRows(report, filters = {}) {
  const columns = reportColumns(report);
  const records = await reportQuery(report, filters);
  return records.map((record) => columns.map((c) => c.value(record) ?? null));
}
