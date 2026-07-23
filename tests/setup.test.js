import { describe, it, expect, beforeEach } from 'vitest';
import request from 'supertest';
import db from '../src/db.js';
import { createApp } from '../src/app.js';
import { freshDb, createStaff } from './helpers.js';

const app = createApp();

describe('first-run web setup', () => {
  beforeEach(freshDb);

  it('redirects /login to /setup when no users exist and creates the first admin', async () => {
    const login = await request(app).get('/login');
    expect(login.headers.location).toBe('/setup');

    expect((await request(app).get('/setup')).status).toBe(200);

    await request(app).post('/setup').type('form').send({
      name: 'Owner', email: 'owner@example.test',
      password: 'first-admin-password-123', password_confirmation: 'first-admin-password-123',
    });

    const admin = await db('users').where({ email: 'owner@example.test' }).first();
    expect(admin.role).toBe('Super Administrator');
    expect(admin.user_type).toBe('staff');
  });

  it('disables itself once any user exists', async () => {
    await createStaff();
    expect((await request(app).get('/setup')).headers.location).toBe('/login');

    const post = await request(app).post('/setup').type('form').send({
      name: 'Attacker', email: 'attacker@example.test',
      password: 'attacker-password-123', password_confirmation: 'attacker-password-123',
    });
    expect(post.headers.location).toBe('/login');
    expect(await db('users').where({ email: 'attacker@example.test' }).first()).toBeUndefined();
  });

  it('enforces password rules on setup', async () => {
    await request(app).post('/setup').type('form').send({
      name: 'Owner', email: 'owner@example.test',
      password: 'short', password_confirmation: 'short',
    });
    expect(await db('users').where({ email: 'owner@example.test' }).first()).toBeUndefined();
  });
});
