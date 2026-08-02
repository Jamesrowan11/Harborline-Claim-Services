import express from "express";
import session from "express-session";
import connectPgSimple from "connect-pg-simple";
import path from "node:path";
import fs from "node:fs";
import { config, repoRoot } from "./config.js";
import { pool, query } from "./db.js";
import { log, safeError } from "./log.js";
import { refreshAccountState, requireAuth } from "./middleware/auth.js";
import { a } from "./util.js";
import { authRouter } from "./routes/auth.js";
import { queueRouter } from "./routes/queue.js";
import { claimsRouter } from "./routes/claims.js";
import { communicationsRouter } from "./routes/communications.js";
import { agreementsRouter } from "./routes/agreements.js";
import { countiesRouter } from "./routes/counties.js";
import { auditRouter } from "./routes/audit.js";

export function buildApp(): express.Express {
  const app = express();
  app.set("trust proxy", 1);
  app.use(express.json({ limit: "256kb" }));

  const PgStore = connectPgSimple(session);
  app.use(
    session({
      store: new PgStore({ pool, tableName: "session" }),
      secret: config.sessionSecret,
      name: "hcs.sid",
      resave: false,
      saveUninitialized: false,
      rolling: true, // every request pushes the idle timeout forward
      cookie: {
        httpOnly: true,
        sameSite: "lax",
        secure: config.nodeEnv === "production",
        maxAge: config.sessionIdleMs, // 15 minutes idle, per spec
      },
    })
  );

  app.use("/api/auth", authRouter);

  // Everything below requires a live session and an active account.
  app.use("/api", requireAuth, a(refreshAccountState));

  // Synthetic-data flag so the client can label demo data in the UI
  // (non-negotiable rule 6).
  app.get(
    "/api/meta",
    a(async (_req, res) => {
      const r = await query(
        "SELECT EXISTS (SELECT 1 FROM claimants WHERE legal_name LIKE 'ZZTEST%') AS synthetic"
      );
      res.json({ synthetic_data: r.rows[0].synthetic });
    })
  );

  app.use("/api/queue", queueRouter);
  app.use("/api/claims", claimsRouter);
  app.use("/api/claims", communicationsRouter);
  app.use("/api", agreementsRouter);
  app.use("/api/counties", countiesRouter);
  app.use("/api/audit", auditRouter);

  // Serve the built client when it exists (production layout).
  const clientDist = path.join(repoRoot, "src", "client", "dist");
  if (fs.existsSync(clientDist)) {
    app.use(express.static(clientDist));
    app.get(/^\/(?!api\/).*/, (_req, res) => {
      res.sendFile(path.join(clientDist, "index.html"));
    });
  }

  // Last: the PII-safe error handler. Nothing from a request body or a
  // database row is ever echoed here (non-negotiable rule 5).
  app.use(
    (err: unknown, req: express.Request, res: express.Response, _next: express.NextFunction) => {
      log.error("request failed", {
        method: req.method,
        path: req.path,
        ...safeError(err),
      });
      res.status(500).json({ error: "internal error" });
    }
  );

  return app;
}
