import db from '../db.js';
import { can } from '../services/permissions.js';
import { config } from '../config.js';

export async function loadUser(req, res, next) {
  req.user = null;
  if (req.session?.userId) {
    const user = await db('users').where({ id: req.session.userId }).first();
    if (user && !user.deactivated_at) req.user = user;
    else req.session.destroy(() => {});
  }
  res.locals.user = req.user;
  res.locals.can = (permission) => can(req.user, permission);
  next();
}

export function requireAuth(req, res, next) {
  if (!req.user) return res.redirect('/login');
  next();
}

export function requireStaff(req, res, next) {
  if (!req.user || req.user.user_type !== 'staff') return res.status(403).render('errors/403');
  next();
}

export function requireClient(req, res, next) {
  if (!req.user || req.user.user_type !== 'client') return res.status(403).render('errors/403');
  next();
}

/** MFA gate for staff: unenrolled → enrollment; enrolled → per-session challenge. */
export function requireMfa(req, res, next) {
  const user = req.user;
  if (user && user.user_type === 'staff' && config.security.mfaRequiredForStaff) {
    if (!user.mfa_enabled_at) return res.redirect('/mfa/enroll');
    if (!req.session.mfaPassed) return res.redirect('/mfa/challenge');
  }
  next();
}

export function permission(name) {
  return (req, res, next) => {
    if (!can(req.user, name)) return res.status(403).render('errors/403');
    next();
  };
}

/** Client access to a case: claimant must be attached. */
export async function clientOwnsCase(req, res, next) {
  const caseRow = await db('cases').where({ id: req.params.caseId }).whereNull('deleted_at').first();
  if (!caseRow) return res.status(404).render('errors/404');
  const attached = req.user.claimant_id && await db('case_claimant')
    .where({ case_id: caseRow.id, claimant_id: req.user.claimant_id }).first();
  if (!attached) return res.status(403).render('errors/403');
  req.caseRow = caseRow;
  next();
}
