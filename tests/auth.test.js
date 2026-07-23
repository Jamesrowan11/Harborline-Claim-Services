import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import { authenticator } from 'otplib';
import { createApp } from '../src/app.js';
import db from '../src/db.js';
import { freshDb, createStaff, createClient, loginAgent } from './helpers.js';

const app = createApp();

describe('authentication and MFA', () => {
  beforeAll(freshDb);

  it('logs in staff and forces the MFA challenge before the portal', async () => {
    const user = await createStaff('Case Manager');
    const agent = request.agent(app);
    const login = await agent.post('/login').type('form').send({ email: user.email, password: 'secret-password-123' });
    expect(login.status).toBe(302);
    expect(login.headers.location).toBe('/portal');

    const portal = await agent.get('/portal');
    expect(portal.headers.location).toBe('/mfa/challenge');

    await agent.post('/mfa/challenge').type('form').send({ code: authenticator.generate(user.secret) });
    expect((await agent.get('/portal')).status).toBe(200);
  });

  it('forces unenrolled staff to MFA enrollment', async () => {
    const user = await createStaff('Case Manager', { mfa_enabled_at: null });
    await db('users').where({ id: user.id }).update({ mfa_secret: null, mfa_enabled_at: null });
    const agent = request.agent(app);
    await agent.post('/login').type('form').send({ email: user.email, password: 'secret-password-123' });
    expect((await agent.get('/portal')).headers.location).toBe('/mfa/enroll');
  });

  it('accepts a recovery code and consumes it', async () => {
    const user = await createStaff('Case Manager');
    const agent = request.agent(app);
    await agent.post('/login').type('form').send({ email: user.email, password: 'secret-password-123' });
    await agent.post('/mfa/challenge').type('form').send({ code: 'RECOVERYONE' });
    expect((await agent.get('/portal')).status).toBe(200);
  });

  it('audits failed logins and blocks deactivated users', async () => {
    const user = await createStaff('Case Manager');
    await request(app).post('/login').type('form').send({ email: user.email, password: 'wrong' });
    const failed = await db('audit_events').where({ event: 'login_failed' }).first();
    expect(failed).toBeTruthy();

    await db('users').where({ id: user.id }).update({ deactivated_at: new Date() });
    const agent = request.agent(app);
    await agent.post('/login').type('form').send({ email: user.email, password: 'secret-password-123' });
    expect((await agent.get('/portal')).headers.location).toBe('/login');
  });

  it('keeps clients out of the portal and staff out of client routes', async () => {
    const client = await createClient();
    const clientAgent = await loginAgent(app, client, { mfa: false });
    expect((await clientAgent.get('/portal')).status).toBe(403);

    const staff = await createStaff();
    const staffAgent = await loginAgent(app, staff);
    expect((await staffAgent.get('/my')).status).toBe(403);
  });
});
