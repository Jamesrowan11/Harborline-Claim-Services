import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a, isDbRuleViolation } from "../util.js";

export const auditRouter = Router();

interface Filters {
  where: string;
  params: unknown[];
}

function buildFilters(q: Record<string, unknown>): Filters {
  const clauses: string[] = [];
  const params: unknown[] = [];
  const add = (clause: string, value: unknown) => {
    params.push(value);
    clauses.push(clause.replace("?", `$${params.length}`));
  };
  if (typeof q.action === "string" && q.action) add("al.action = ?", q.action);
  if (typeof q.entity_type === "string" && q.entity_type)
    add("al.entity_type = ?", q.entity_type);
  if (typeof q.entity_id === "string" && q.entity_id)
    add("al.entity_id = ?", q.entity_id);
  if (typeof q.user_email === "string" && q.user_email)
    add("lower(u.email) = lower(?)", q.user_email);
  if (typeof q.from === "string" && q.from) add("al.occurred_at >= ?", q.from);
  if (typeof q.to === "string" && q.to) add("al.occurred_at < ?", q.to);
  return {
    where: clauses.length ? "WHERE " + clauses.join(" AND ") : "",
    params,
  };
}

const SELECT = `
  SELECT al.id, al.occurred_at, al.action, al.entity_type, al.entity_id,
         al.field_name, al.reason, al.ip_address, u.email AS user_email
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id`;

/**
 * GET /api/audit — filterable, read-only. Audit rows contain record ids
 * and reasons, never claimant data, so the viewer itself introduces no
 * PII surface.
 */
auditRouter.get(
  "/",
  a(async (req, res) => {
    const { where, params } = buildFilters(req.query as Record<string, unknown>);
    const limit = Math.min(Number(req.query.limit ?? 100) || 100, 500);
    const offset = Math.max(Number(req.query.offset ?? 0) || 0, 0);
    const r = await query(
      `${SELECT} ${where}
        ORDER BY al.occurred_at DESC, al.id DESC
        LIMIT ${limit} OFFSET ${offset}`,
      params
    );
    res.json({ rows: r.rows, limit, offset });
  })
);

/**
 * POST /api/audit/export  { reason, ...filters }
 *
 * No export without a reason string (screen spec + rule 2): the export is
 * itself an audited action, and the database refuses the audit row — and
 * therefore the export — when the reason is under 8 characters.
 */
auditRouter.post(
  "/export",
  a(async (req, res) => {
    const body = (req.body ?? {}) as Record<string, unknown>;
    try {
      await writeAudit({
        userId: req.session.user!.id,
        action: "export",
        entityType: "audit_log",
        reason: typeof body.reason === "string" ? body.reason : null,
        ip: req.ip,
      });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({
          error: "an export requires a stated reason of at least 8 characters",
        });
        return;
      }
      throw err;
    }
    const { where, params } = buildFilters(body);
    const r = await query(
      `${SELECT} ${where} ORDER BY al.occurred_at DESC, al.id DESC LIMIT 10000`,
      params
    );
    const cols = [
      "occurred_at",
      "user_email",
      "action",
      "entity_type",
      "entity_id",
      "field_name",
      "reason",
      "ip_address",
    ];
    const esc = (v: unknown) =>
      v == null ? "" : `"${String(v instanceof Date ? v.toISOString() : v).replaceAll('"', '""')}"`;
    const csv = [
      cols.join(","),
      ...r.rows.map((row) => cols.map((c) => esc((row as Record<string, unknown>)[c])).join(",")),
    ].join("\r\n");
    res.setHeader("Content-Type", "text/csv; charset=utf-8");
    res.setHeader("Content-Disposition", 'attachment; filename="audit-export.csv"');
    res.send(csv);
  })
);
