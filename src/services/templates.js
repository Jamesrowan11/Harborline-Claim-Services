import Settings from './settings.js';
import { config } from '../config.js';

/**
 * Renders {{merge_field}} placeholders. Only whitelisted fields are available —
 * templates can never reach arbitrary data or staff-only fields.
 */
export async function mergeFields(caseRow, claimant, extra = {}) {
  const brand = await Settings.brandAll();
  return {
    'brand.name': brand.name,
    'brand.legal_name': brand.legal_name,
    'brand.phone': brand.phone,
    'brand.email': brand.email,
    'brand.address': brand.address,
    'brand.disclaimer': config.disclaimer,
    'case.number': caseRow?.case_number ?? '',
    'case.county': caseRow?.county ?? '',
    'case.state': caseRow?.state ?? '',
    'case.status_label': caseRow?.client_label ?? '',
    'case.manager': caseRow?.manager_name ?? '',
    'claimant.name': claimantName(claimant),
    'claimant.first_name': claimant?.first_name ?? '',
    'claimant.last_name': claimant?.last_name ?? '',
    today: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
    ...extra,
  };
}

export function claimantName(claimant) {
  if (!claimant) return '';
  return claimant.organization_name || [claimant.first_name, claimant.last_name].filter(Boolean).join(' ');
}

export async function renderTemplate(template, caseRow, claimant, extra = {}) {
  const fields = await mergeFields(caseRow, claimant, extra);
  const replace = (text) => String(text ?? '').replace(/\{\{\s*([a-z0-9_.]+)\s*\}\}/gi,
    (_, name) => String(fields[name.toLowerCase()] ?? ''));
  return { subject: replace(template.subject), body: replace(template.body), version: template.version };
}
