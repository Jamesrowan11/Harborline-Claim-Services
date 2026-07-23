import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import helmet from 'helmet';
import session from 'express-session';
import { ConnectSessionKnexStore } from 'connect-session-knex';
import rateLimit from 'express-rate-limit';
import { config } from './config.js';
import db from './db.js';
import Settings from './services/settings.js';
import { loadUser } from './middleware/auth.js';
import { csrf, methodOverride } from './middleware/security.js';
import { wireAutomationEngine } from './automation/engine.js';
import siteRoutes from './routes/site.js';
import authRoutes from './routes/auth.js';
import portalRoutes from './routes/portal.js';
import clientRoutes from './routes/client.js';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export function createApp() {
  wireAutomationEngine();

  const app = express();
  app.set('view engine', 'ejs');
  app.set('views', path.join(dirname, 'views'));
  app.set('trust proxy', 1); // behind Plesk nginx/Passenger

  app.use(helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        scriptSrc: ["'self'"],
        styleSrc: ["'self'", "'unsafe-inline'"],
        imgSrc: ["'self'", 'data:'],
        frameAncestors: ["'none'"],
        formAction: ["'self'"],
        baseUri: ["'self'"],
      },
    },
    referrerPolicy: { policy: 'strict-origin-when-cross-origin' },
  }));

  app.use('/assets', express.static(path.join(process.cwd(), 'public/assets'), { maxAge: '30d' }));
  app.use(express.urlencoded({ extended: true, limit: '1mb' }));

  app.use(session({
    store: new ConnectSessionKnexStore({ knex: db, tableName: 'sessions', createTable: true }),
    secret: config.appKey || 'dev-only-secret',
    name: 'hcs_session',
    resave: false,
    saveUninitialized: false,
    rolling: true, // inactivity timeout
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      secure: config.env === 'production',
      maxAge: config.security.sessionLifetimeMinutes * 60_000,
    },
  }));

  app.use(methodOverride);
  app.use(csrf);
  app.use(loadUser);

  // Shared view locals
  app.use(async (req, res, next) => {
    res.locals.brand = await Settings.brandAll();
    res.locals.disclaimer = config.disclaimer;
    res.locals.flash = req.session.flash ?? null;
    res.locals.formErrors = req.session.formErrors ?? {};
    res.locals.old = req.session.old ?? {};
    delete req.session.flash; delete req.session.formErrors; delete req.session.old;
    res.locals.query = req.query;
    next();
  });

  const publicFormLimiter = rateLimit({
    windowMs: 60_000, limit: 10, standardHeaders: true,
    skip: () => config.env === 'test',
  });

  app.get('/up', (req, res) => res.json({ ok: true }));
  app.use('/', authRoutes(publicFormLimiter));
  app.use('/', siteRoutes(publicFormLimiter));
  app.use('/portal', portalRoutes());
  app.use('/my', clientRoutes());

  app.use((req, res) => res.status(404).render('errors/404'));
  // Production error masking: log details, show a generic page.
  // eslint-disable-next-line no-unused-vars
  app.use((error, req, res, next) => {
    console.error(new Date().toISOString(), error);
    const status = error.status ?? 500;
    if (status >= 500) return res.status(500).render('errors/500');
    res.status(status).send(config.env === 'production' ? 'Request failed.' : String(error.message));
  });

  return app;
}
