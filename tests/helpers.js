import bcrypt from 'bcryptjs';
import { authenticator } from 'otplib';
import request from 'supertest';
import db from '../src/db.js';
import { encrypt } from '../src/crypto.js';
import { seedBaseline } from '../src/seeds/baseline.js';

export async function freshDb() {
  await db.migrate.rollback(undefined, true);
  await db.migrate.latest();
  await seedBaseline(db);
}

let userCounter = 0;

export async function createStaff(role = 'Company Administrator', overrides = {}) {
  const secret = authenticator.generateSecret();
  const [{ id }] = await db('users').insert({
    name: overrides.name ?? `Staff ${++userCounter}`,
    email: overrides.email ?? `staff${userCounter}@example.test`,
    password: await bcrypt.hash('secret-password-123', 4),
    user_type: 'staff', role,
    mfa_secret: encrypt(secret),
    mfa_enabled_at: overrides.mfa_enabled_at === null ? null : new Date(),
    mfa_recovery_codes: encrypt(JSON.stringify(['RECOVERYONE'])),
    ...Object.fromEntries(Object.entries(overrides).filter(([k]) => !['name', 'email', 'mfa_enabled_at'].includes(k))),
  }, ['id']);
  return { id, secret, email: overrides.email ?? `staff${userCounter}@example.test`, role };
}

export async function createClient(claimantId = null) {
  const [{ id }] = await db('users').insert({
    name: `Client ${++userCounter}`,
    email: `client${userCounter}@example.test`,
    password: await bcrypt.hash('secret-password-123', 4),
    user_type: 'client', role: 'Client', claimant_id: claimantId,
  }, ['id']);
  return { id, email: `client${userCounter}@example.test` };
}

/** Logs in through the real routes (including the TOTP challenge for staff). */
export async function loginAgent(app, user, { mfa = true } = {}) {
  const agent = request.agent(app);
  await agent.post('/login').type('form').send({ email: user.email, password: 'secret-password-123' });
  if (mfa && user.secret) {
    await agent.post('/mfa/challenge').type('form').send({ code: authenticator.generate(user.secret) });
  }
  return agent;
}

export async function createCase(overrides = {}, stageKey = 'new_lead') {
  const stage = await db('pipeline_stages').where({ key: stageKey }).first();
  const [{ id }] = await db('cases').insert({
    case_number: overrides.case_number ?? `HCS-2026-MD-AA-${String((await db('cases').count({ c: '*' }))[0].c * 1 + 1).padStart(6, '0')}`,
    pipeline_stage_id: stage.id,
    county: 'Anne Arundel', county_code: 'AA', state: 'MD',
    ...overrides,
  }, ['id']);
  return db('cases').where({ id }).first();
}

export async function attachClaimant(caseRow, overrides = {}) {
  const [{ id }] = await db('claimants').insert({
    type: 'individual', first_name: 'Test', last_name: 'Claimant', ...overrides,
  }, ['id']);
  await db('case_claimant').insert({ case_id: caseRow.id, claimant_id: id, role: 'primary' });
  return db('claimants').where({ id }).first();
}
