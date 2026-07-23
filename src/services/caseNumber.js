import db from '../db.js';
import { config } from '../config.js';
import Settings from './settings.js';

/**
 * Case numbers from an admin-editable format:
 *   {PREFIX}-{YEAR}-{STATE}-{COUNTY}-{SEQ:6} → HCS-2026-MD-AA-000001
 * Sequences are per year/state/county, allocated inside a transaction.
 */
export async function nextCaseNumber(state, countyCode, year = null) {
  year = year ?? new Date().getFullYear();
  state = (state || 'XX').toUpperCase();
  countyCode = (countyCode || 'XX').toUpperCase();

  const sequence = await db.transaction(async (trx) => {
    const existing = await trx('case_number_sequences')
      .where({ year, state, county_code: countyCode })
      .forUpdate().first();
    if (!existing) {
      await trx('case_number_sequences').insert({ year, state, county_code: countyCode, last_number: 1 });
      return 1;
    }
    const next = Number(existing.last_number) + 1;
    await trx('case_number_sequences').where({ id: existing.id }).update({ last_number: next });
    return next;
  });

  return formatCaseNumber(sequence, year, state, countyCode);
}

export async function formatCaseNumber(sequence, year, state, countyCode) {
  const format = (await Settings.get('case_number_format')) ?? config.caseNumberFormat;
  const prefix = await Settings.brand('case_prefix');

  return format.replace(/\{(PREFIX|YEAR|STATE|COUNTY|SEQ(?::(\d+))?)\}/g, (match, token, pad) => {
    if (token === 'PREFIX') return prefix;
    if (token === 'YEAR') return String(year);
    if (token === 'STATE') return state;
    if (token === 'COUNTY') return countyCode;
    if (token.startsWith('SEQ')) return String(sequence).padStart(Number(pad ?? 6), '0');
    return match;
  });
}
