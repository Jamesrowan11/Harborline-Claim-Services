import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a, isDbRuleViolation } from "../util.js";
import { requireRole } from "../middleware/auth.js";

export const agreementsRouter = Router();

/**
 * POST /api/claims/:id/agreements  { fee_percent, fee_amount?, executed_at? }
 *
 * The county fee cap, the verification-before-execution rule, and the
 * one-live-agreement rule are all enforced by database triggers (rule 4 —
 * "rejected at the database level, not just the UI"). This route only
 * relays the database's answer.
 */
agreementsRouter.post(
  "/claims/:id/agreements",
  requireRole("admin", "case_manager"),
  a(async (req, res) => {
    const { fee_percent, fee_amount, executed_at } = req.body ?? {};
    if (typeof fee_percent !== "number") {
      res.status(400).json({ error: "fee_percent is required" });
      return;
    }
    try {
      const r = await query<{ id: string }>(
        `INSERT INTO agreements (claim_id, fee_percent, fee_amount, executed_at)
         VALUES ($1, $2, $3, $4)
         RETURNING id`,
        [req.params.id, fee_percent, fee_amount ?? null, executed_at ?? null]
      );
      await writeAudit({
        userId: req.session.user!.id,
        action: "create",
        entityType: "agreement",
        entityId: r.rows[0].id,
        ip: req.ip,
      });
      res.status(201).json({ id: r.rows[0].id });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        // Trigger messages name counties and percentages, never claimants.
        res.status(400).json({ error: (err as Error).message });
        return;
      }
      throw err;
    }
  })
);

/** POST /api/agreements/:id/void  { reason } */
agreementsRouter.post(
  "/agreements/:id/void",
  requireRole("admin", "case_manager"),
  a(async (req, res) => {
    const { reason } = req.body ?? {};
    if (typeof reason !== "string" || reason.trim() === "") {
      res.status(400).json({ error: "a void reason is required" });
      return;
    }
    const r = await query(
      `UPDATE agreements SET void_reason = $2
        WHERE id = $1 AND void_reason IS NULL AND superseded_by_agreement_id IS NULL
        RETURNING id`,
      [req.params.id, reason.trim()]
    );
    if (r.rowCount === 0) {
      res.status(404).json({ error: "live agreement not found" });
      return;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "update",
      entityType: "agreement",
      entityId: req.params.id,
      fieldName: "void_reason",
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);
