import crypto from 'node:crypto';
import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { authenticator } from 'otplib';
import db from '../db.js';
import { config } from '../config.js';
import { audit } from '../services/audit.js';
import { encrypt, decrypt } from '../crypto.js';
import { requireAuth } from '../middleware/auth.js';
import { sendMail } from '../services/mailer.js';

const failures = new Map(); // key → {count, until}

function throttled(key, max = 5, windowMs = 300_000) {
  const entry = failures.get(key);
  if (entry && entry.count >= max && Date.now() < entry.until) return true;
  return false;
}
function hitThrottle(key, max = 5, windowMs = 300_000) {
  const entry = failures.get(key) ?? { count: 0, until: 0 };
  entry.count += 1;
  entry.until = Date.now() + windowMs;
  failures.set(key, entry);
}

export default function authRoutes(publicFormLimiter) {
  const router = Router();

  router.get('/login', async (req, res) => {
    const [{ c }] = await db('users').count({ c: '*' });
    if (Number(c) === 0) return res.redirect('/setup');
    res.render('auth/login');
  });

  router.post('/login', publicFormLimiter, async (req, res) => {
    const email = String(req.body.email ?? '').toLowerCase().trim();
    const password = String(req.body.password ?? '');
    const key = `${email}|${req.ip}`;

    if (throttled(key)) {
      req.session.flash = 'Too many login attempts. Please wait a few minutes and try again.';
      return res.redirect('/login');
    }

    const user = await db('users').where({ email }).first();
    if (!user || !(await bcrypt.compare(password, user.password))) {
      hitThrottle(key);
      await audit('login_failed', { req, newValues: { email } });
      req.session.flash = 'Those credentials do not match our records.';
      return res.redirect('/login');
    }
    if (user.deactivated_at) {
      req.session.flash = 'This account has been deactivated.';
      return res.redirect('/login');
    }

    failures.delete(key);
    await new Promise((resolve) => req.session.regenerate(resolve));
    req.session.userId = user.id;
    req.session.mfaPassed = false;

    await db('users').where({ id: user.id }).update({ last_login_at: new Date(), last_login_ip: req.ip });
    await audit('login', { req: { ...req, session: { userId: user.id } }, type: 'user', id: user.id });

    if (config.security.loginAlertsEnabled && user.user_type === 'staff') {
      sendMail({
        to: user.email,
        subject: `New sign-in to your ${config.brand.name} account`,
        text: `Your account was just signed in from IP address ${req.ip}. If this was not you, reset your password immediately and contact your administrator.`,
      }).catch(() => {});
    }

    res.redirect(user.user_type === 'client' ? '/my' : '/portal');
  });

  router.post('/logout', requireAuth, (req, res) => {
    req.session.destroy(() => res.redirect('/'));
  });

  // MFA enrollment (mandatory for staff) -----------------------------------
  router.get('/mfa/enroll', requireAuth, (req, res) => {
    if (!req.session.mfaEnrollSecret) req.session.mfaEnrollSecret = authenticator.generateSecret();
    const otpauthUrl = authenticator.keyuri(req.user.email, config.brand.name, req.session.mfaEnrollSecret);
    res.render('auth/mfa-enroll', { secret: req.session.mfaEnrollSecret, otpauthUrl });
  });

  router.post('/mfa/enroll', requireAuth, async (req, res) => {
    const secret = req.session.mfaEnrollSecret;
    const code = String(req.body.code ?? '').trim();
    if (!secret || !authenticator.check(code, secret)) {
      req.session.flash = 'That code is not valid. Please try again.';
      return res.redirect('/mfa/enroll');
    }
    const recoveryCodes = Array.from({ length: 8 }, () => crypto.randomBytes(5).toString('hex').toUpperCase());
    await db('users').where({ id: req.user.id }).update({
      mfa_secret: encrypt(secret),
      mfa_enabled_at: new Date(),
      mfa_recovery_codes: encrypt(JSON.stringify(recoveryCodes)),
    });
    delete req.session.mfaEnrollSecret;
    req.session.mfaPassed = true;
    await audit('mfa_enrolled', { req, type: 'user', id: req.user.id });
    res.render('auth/mfa-recovery-codes', { recoveryCodes });
  });

  router.get('/mfa/challenge', requireAuth, (req, res) => res.render('auth/mfa-challenge'));

  router.post('/mfa/challenge', requireAuth, async (req, res) => {
    const code = String(req.body.code ?? '').trim();
    const key = `mfa|${req.user.id}`;
    if (throttled(key)) {
      req.session.flash = 'Too many attempts. Please wait and try again.';
      return res.redirect('/mfa/challenge');
    }

    let valid = false;
    if (/^\d{6}$/.test(code)) {
      valid = authenticator.check(code, decrypt(req.user.mfa_secret));
    } else {
      const codes = JSON.parse(decrypt(req.user.mfa_recovery_codes) ?? '[]');
      const index = codes.indexOf(code.toUpperCase());
      if (index >= 0) {
        codes.splice(index, 1);
        await db('users').where({ id: req.user.id }).update({ mfa_recovery_codes: encrypt(JSON.stringify(codes)) });
        valid = true;
      }
    }

    if (!valid) {
      hitThrottle(key);
      await audit('mfa_failed', { req, type: 'user', id: req.user.id });
      req.session.flash = 'That code is not valid.';
      return res.redirect('/mfa/challenge');
    }
    failures.delete(key);
    req.session.mfaPassed = true;
    await audit('mfa_passed', { req, type: 'user', id: req.user.id });
    res.redirect(req.user.user_type === 'client' ? '/my' : '/portal');
  });

  // Password reset (anti-enumeration) ---------------------------------------
  router.get('/forgot-password', (req, res) => res.render('auth/forgot-password'));

  router.post('/forgot-password', publicFormLimiter, async (req, res) => {
    const email = String(req.body.email ?? '').toLowerCase().trim();
    const user = await db('users').where({ email }).first();
    if (user) {
      const token = crypto.randomBytes(32).toString('hex');
      await db('users').where({ id: user.id }).update({
        password_reset_token: crypto.createHash('sha256').update(token).digest('hex'),
        password_reset_expires_at: new Date(Date.now() + 3600_000),
      });
      sendMail({
        to: email,
        subject: `Reset your ${config.brand.name} password`,
        text: `Use this link within one hour to choose a new password:\n\n${config.appUrl}/reset-password/${token}?email=${encodeURIComponent(email)}\n\nIf you did not request this, you can ignore this message.`,
      }).catch(() => {});
    }
    req.session.flash = 'If that email address is on file, a reset link has been sent.';
    res.redirect('/forgot-password');
  });

  router.get('/reset-password/:token', (req, res) =>
    res.render('auth/reset-password', { token: req.params.token, email: req.query.email ?? '' }));

  router.post('/reset-password', publicFormLimiter, async (req, res) => {
    const { token, email, password, password_confirmation: confirmation } = req.body;
    if (!password || password.length < 12 || !/[a-zA-Z]/.test(password) || !/\d/.test(password) || password !== confirmation) {
      req.session.flash = 'Passwords must match and contain at least 12 characters with letters and numbers.';
      return res.redirect(`/reset-password/${token}?email=${encodeURIComponent(email ?? '')}`);
    }
    const hashed = crypto.createHash('sha256').update(String(token ?? '')).digest('hex');
    const user = await db('users').where({ email: String(email ?? '').toLowerCase(), password_reset_token: hashed })
      .where('password_reset_expires_at', '>', new Date()).first();
    if (!user) {
      req.session.flash = 'That reset link is invalid or has expired.';
      return res.redirect('/forgot-password');
    }
    await db('users').where({ id: user.id }).update({
      password: await bcrypt.hash(password, 12),
      password_reset_token: null, password_reset_expires_at: null,
    });
    await audit('password_reset', { type: 'user', id: user.id });
    req.session.flash = 'Your password has been reset. Please sign in.';
    res.redirect('/login');
  });


  // One-time first-run setup: create the initial Super Administrator from the
  // browser. Only available while no users exist; disables itself afterward.
  async function noUsersExist() {
    const [{ c }] = await db('users').count({ c: '*' });
    return Number(c) === 0;
  }

  router.get('/setup', async (req, res) => {
    if (!(await noUsersExist())) return res.redirect('/login');
    res.render('auth/setup');
  });

  router.post('/setup', publicFormLimiter, async (req, res) => {
    if (!(await noUsersExist())) return res.redirect('/login');
    const { name, email, password, password_confirmation: confirmation } = req.body;
    if (!name || !email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      req.session.flash = 'Please enter your name and a valid email address.';
      return res.redirect('/setup');
    }
    if (!password || password.length < 12 || !/[a-zA-Z]/.test(password) || !/\d/.test(password) || password !== confirmation) {
      req.session.flash = 'Passwords must match and contain at least 12 characters with letters and numbers.';
      return res.redirect('/setup');
    }
    const [{ id }] = await db('users').insert({
      name: String(name).slice(0, 120),
      email: String(email).toLowerCase().trim(),
      password: await bcrypt.hash(password, 12),
      user_type: 'staff', role: 'Super Administrator',
    }, ['id']);
    await audit('user_created', { req, type: 'user', id, newValues: { role: 'Super Administrator', via: 'first-run setup' } });
    req.session.flash = 'Administrator created. Sign in, then complete two-factor enrollment.';
    res.redirect('/login');
  });

  return router;
}
