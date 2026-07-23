/**
 * Role → permission matrix (port of the original granular model).
 * Client access is governed by per-record scoping, not staff permissions.
 */
export const PERMISSIONS = [
  'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.assign',
  'cases.view', 'cases.create', 'cases.update', 'cases.delete', 'cases.assign',
  'cases.change_stage', 'cases.merge', 'cases.hold',
  'sources.view', 'sources.manage', 'surplus.view', 'surplus.verify',
  'claimants.view', 'claimants.manage', 'claimants.view_sensitive',
  'tasks.view', 'tasks.manage', 'deadlines.manage', 'notes.manage',
  'documents.view', 'documents.upload', 'documents.approve', 'documents.download',
  'templates.manage', 'templates.approve',
  'communications.view', 'communications.send', 'communications.approve',
  'agreements.view', 'agreements.manage', 'agreements.approve',
  'compliance.review', 'attorney.review', 'legal_holds.manage',
  'claims.view', 'claims.manage', 'payments.view', 'payments.manage', 'expenses.manage',
  'automations.view', 'automations.manage', 'automations.approve',
  'reports.view', 'reports.export', 'audit.view',
  'admin.settings', 'admin.users', 'admin.roles', 'admin.retention', 'admin.integrations',
];

const expand = (grants) => grants.flatMap((grant) =>
  grant.endsWith('.*')
    ? PERMISSIONS.filter((p) => p.startsWith(grant.slice(0, -1)))
    : [grant]);

export const ROLES = {
  'Super Administrator': PERMISSIONS,
  'Company Administrator': expand([
    'leads.*', 'cases.*', 'sources.*', 'surplus.*', 'claimants.view', 'claimants.manage',
    'tasks.*', 'deadlines.manage', 'notes.manage', 'documents.*', 'templates.*',
    'communications.*', 'agreements.view', 'agreements.manage', 'claims.*', 'payments.*',
    'expenses.manage', 'automations.view', 'automations.manage', 'reports.*',
    'admin.settings', 'admin.users', 'compliance.review',
  ]),
  Researcher: expand([
    'leads.view', 'leads.update', 'cases.view', 'cases.update', 'cases.change_stage',
    'sources.*', 'surplus.view', 'surplus.verify', 'claimants.view', 'tasks.view',
    'tasks.manage', 'notes.manage', 'documents.view', 'documents.upload', 'reports.view',
  ]),
  'Case Manager': expand([
    'leads.*', 'cases.view', 'cases.create', 'cases.update', 'cases.assign',
    'cases.change_stage', 'sources.view', 'surplus.view', 'claimants.view',
    'claimants.manage', 'tasks.*', 'deadlines.manage', 'notes.manage', 'documents.view',
    'documents.upload', 'documents.download', 'communications.view', 'communications.send',
    'agreements.view', 'agreements.manage', 'claims.view', 'claims.manage',
    'reports.view', 'reports.export',
  ]),
  'Compliance Reviewer': expand([
    'cases.view', 'cases.hold', 'claimants.view', 'claimants.view_sensitive',
    'documents.view', 'documents.approve', 'templates.approve', 'communications.view',
    'communications.approve', 'agreements.view', 'agreements.approve',
    'compliance.review', 'automations.view', 'automations.approve', 'audit.view', 'reports.view',
  ]),
  Attorney: expand([
    'cases.view', 'claimants.view', 'documents.view', 'documents.download',
    'agreements.view', 'agreements.approve', 'attorney.review', 'notes.manage', 'reports.view',
  ]),
  'Accounting User': expand([
    'cases.view', 'claims.view', 'payments.*', 'expenses.manage', 'reports.view', 'reports.export',
  ]),
  'Read-Only Auditor': expand([
    'leads.view', 'cases.view', 'sources.view', 'surplus.view', 'claimants.view',
    'tasks.view', 'documents.view', 'communications.view', 'agreements.view',
    'claims.view', 'payments.view', 'automations.view', 'reports.view', 'audit.view',
  ]),
  Client: [],
};

export function can(user, permission) {
  if (!user || user.user_type !== 'staff') return false;
  const grants = ROLES[user.role] ?? [];
  return grants.includes(permission);
}
