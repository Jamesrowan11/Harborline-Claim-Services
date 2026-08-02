import type { NextFunction, Request, Response } from "express";
import { query } from "../db.js";

export type Role = "admin" | "case_manager" | "researcher" | "read_only";

export interface SessionUser {
  id: string;
  email: string;
  role: Role;
}

declare module "express-session" {
  interface SessionData {
    user?: SessionUser;
  }
}

export function requireAuth(req: Request, res: Response, next: NextFunction): void {
  if (!req.session.user) {
    res.status(401).json({ error: "authentication required" });
    return;
  }
  next();
}

export function requireRole(...roles: Role[]) {
  return (req: Request, res: Response, next: NextFunction): void => {
    const user = req.session.user;
    if (!user) {
      res.status(401).json({ error: "authentication required" });
      return;
    }
    if (!roles.includes(user.role)) {
      res.status(403).json({ error: "insufficient role" });
      return;
    }
    next();
  };
}

/** Roles allowed to change data. read_only can never mutate. */
export const canWrite: Role[] = ["admin", "case_manager", "researcher"];

/**
 * Re-check the account on every request: a deactivated user's existing
 * session stops working immediately, not at next login.
 */
export async function refreshAccountState(
  req: Request,
  res: Response,
  next: NextFunction
): Promise<void> {
  const user = req.session.user;
  if (!user) return next();
  const r = await query<{ active: boolean; role: Role }>(
    "SELECT active, role FROM users WHERE id = $1",
    [user.id]
  );
  if (r.rowCount === 0 || !r.rows[0].active) {
    req.session.destroy(() => {
      res.status(401).json({ error: "account is not active" });
    });
    return;
  }
  req.session.user = { ...user, role: r.rows[0].role };
  next();
}
