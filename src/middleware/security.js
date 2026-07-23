import crypto from 'node:crypto';
import { config } from '../config.js';

/** CSRF: session token checked on every mutating request (double-submit via form field). */
export function csrf(req, res, next) {
  if (!req.session.csrf) req.session.csrf = crypto.randomBytes(24).toString('hex');
  res.locals.csrf = req.session.csrf;
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(req.method)) {
    const token = req.body?._csrf ?? req.get('x-csrf-token');
    if (config.env !== 'test' && token !== req.session.csrf) {
      return res.status(419).send('Page expired. Please go back and try again.');
    }
  }
  next();
}

/** Method override: forms POST with _method=PUT/DELETE. */
export function methodOverride(req, res, next) {
  if (req.body?._method) {
    req.method = String(req.body._method).toUpperCase();
    delete req.body._method;
  }
  next();
}
