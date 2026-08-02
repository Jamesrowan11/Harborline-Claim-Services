import { Router } from "express";
import bcrypt from "bcryptjs";
import { authenticator } from "otplib";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { log } from "../log.js";
import { requireAuth } from "../middleware/auth.js";
import type { Role } from "../middleware/auth.js";

export const authRouter = Router();

interface UserRow {
  id: string;
  email: string;
  password_hash: string;
  totp_secret: string | null;
  role: Role;
  active: boolean;
}

/**
 * POST /api/auth/login  { email, password, totp }
 *
 * TOTP is required for all accounts, no exceptions. An account without an
 * enrolled TOTP secret cannot log in. Failures are audited with the user id
 * when the email resolves to one; emails themselves are never logged.
 */
authRouter.post("/login", async (req, res) => {
  const { email, password, totp } = req.body ?? {};
  if (
    typeof email !== "string" ||
    typeof password !== "string" ||
    typeof totp !== "string"
  ) {
    res.status(400).json({ error: "email, password, and totp are required" });
    return;
  }

  const r = await query<UserRow>(
    `SELECT id, email, password_hash, totp_secret, role, active
       FROM users WHERE lower(email) = lower($1)`,
    [email]
  );
  const user = r.rows[0];
  const fail = async () => {
    if (user) {
      await writeAudit({
        userId: user.id,
        action: "login_failed",
        entityType: "user",
        entityId: user.id,
        ip: req.ip,
      });
      log.warn("login failed", { userId: user.id });
    } else {
      log.warn("login failed", { userId: null });
    }
    // One error for every failure mode: no oracle for which part was wrong.
    res.status(401).json({ error: "invalid credentials" });
  };

  if (!user || !user.active) return fail();
  if (!(await bcrypt.compare(password, user.password_hash))) return fail();
  if (!user.totp_secret) return fail();
  if (!authenticator.check(totp, user.totp_secret)) return fail();

  await new Promise<void>((resolve, reject) =>
    req.session.regenerate((err) => (err ? reject(err) : resolve()))
  );
  req.session.user = { id: user.id, email: user.email, role: user.role };

  await query("UPDATE users SET last_login_at = now() WHERE id = $1", [user.id]);
  await writeAudit({
    userId: user.id,
    action: "login",
    entityType: "user",
    entityId: user.id,
    ip: req.ip,
  });
  log.info("login ok", { userId: user.id });

  res.json({ user: req.session.user });
});

authRouter.post("/logout", requireAuth, async (req, res) => {
  const userId = req.session.user!.id;
  await writeAudit({
    userId,
    action: "logout",
    entityType: "user",
    entityId: userId,
    ip: req.ip,
  });
  req.session.destroy(() => res.json({ ok: true }));
});

/** Who am I — also used by the client to detect idle-timeout expiry. */
authRouter.get("/session", (req, res) => {
  if (!req.session.user) {
    res.status(401).json({ error: "authentication required" });
    return;
  }
  res.json({ user: req.session.user });
});
