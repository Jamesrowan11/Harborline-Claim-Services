import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a, isDbRuleViolation } from "../util.js";
import { requireRole } from "../middleware/auth.js";

export const countiesRouter = Router();

/** All staff can read county rules; only admins may change them. */
countiesRouter.get(
  "/",
  a(async (_req, res) => {
    const r = await query(
      `SELECT id, state, county_name, funds_holder_name, holder_contact,
              fee_cap_percent, filing_deadline_rule, contact_blackout_days,
              licensing_required, notes, last_verified_date
         FROM counties
        ORDER BY state, county_name`
    );
    res.json({ counties: r.rows });
  })
);

const FIELDS = [
  "state",
  "county_name",
  "funds_holder_name",
  "holder_contact",
  "fee_cap_percent",
  "filing_deadline_rule",
  "contact_blackout_days",
  "licensing_required",
  "notes",
  "last_verified_date",
] as const;

function pick(body: Record<string, unknown>) {
  return FIELDS.map((f) => {
    const v = body[f];
    return v === "" || v === undefined ? null : v;
  });
}

countiesRouter.post(
  "/",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const r = await query<{ id: string }>(
        `INSERT INTO counties (${FIELDS.join(", ")})
         VALUES ($1,$2,$3,$4,$5,$6,$7,COALESCE($8,false),$9,$10)
         RETURNING id`,
        pick(req.body ?? {})
      );
      await writeAudit({
        userId: req.session.user!.id,
        action: "create",
        entityType: "county",
        entityId: r.rows[0].id,
        ip: req.ip,
      });
      res.status(201).json({ id: r.rows[0].id });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({ error: "county rejected: check state code, uniqueness, and ranges" });
        return;
      }
      throw err;
    }
  })
);

countiesRouter.put(
  "/:id",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const sets = FIELDS.map((f, i) =>
        f === "licensing_required" ? `${f} = COALESCE($${i + 2}, false)` : `${f} = $${i + 2}`
      ).join(", ");
      const r = await query(
        `UPDATE counties SET ${sets} WHERE id = $1 RETURNING id`,
        [req.params.id, ...pick(req.body ?? {})]
      );
      if (r.rowCount === 0) {
        res.status(404).json({ error: "county not found" });
        return;
      }
      await writeAudit({
        userId: req.session.user!.id,
        action: "update",
        entityType: "county",
        entityId: req.params.id,
        ip: req.ip,
      });
      res.json({ ok: true });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({ error: "county rejected: check state code, uniqueness, and ranges" });
        return;
      }
      throw err;
    }
  })
);

countiesRouter.delete(
  "/:id",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const r = await query("DELETE FROM counties WHERE id = $1 RETURNING id", [
        req.params.id,
      ]);
      if (r.rowCount === 0) {
        res.status(404).json({ error: "county not found" });
        return;
      }
    } catch (err) {
      const code = (err as { code?: string }).code;
      if (code === "23503") {
        res.status(400).json({
          error: "county is referenced by existing claims and cannot be deleted",
        });
        return;
      }
      throw err;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "delete",
      entityType: "county",
      entityId: req.params.id,
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);
