import { query } from "./db.js";

/**
 * The audit write path. Every read of a claim file, every PII reveal, and
 * every export goes through here (non-negotiable rule 2). The table itself
 * is append-only — triggers refuse UPDATE/DELETE/TRUNCATE — and the
 * database refuses pii_reveal/export rows whose reason is under 8
 * characters, so a missing reason fails the whole action, never just the
 * logging.
 */

export type AuditAction =
  | "login"
  | "login_failed"
  | "logout"
  | "view"
  | "pii_reveal"
  | "export"
  | "create"
  | "update"
  | "delete";

export interface AuditEntry {
  userId: string | null;
  action: AuditAction;
  entityType: string;
  entityId?: string | null;
  fieldName?: string | null;
  reason?: string | null;
  ip?: string | null;
}

export async function writeAudit(e: AuditEntry): Promise<void> {
  await query(
    `INSERT INTO audit_log
       (user_id, action, entity_type, entity_id, field_name, reason, ip_address)
     VALUES ($1, $2, $3, $4, $5, $6, $7)`,
    [
      e.userId,
      e.action,
      e.entityType,
      e.entityId ?? null,
      e.fieldName ?? null,
      e.reason ?? null,
      e.ip ?? null,
    ]
  );
}
