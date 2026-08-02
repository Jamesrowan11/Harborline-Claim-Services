import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a } from "../util.js";
import { canWrite, requireRole } from "../middleware/auth.js";

export const communicationsRouter = Router();

const CHANNELS = ["mail", "phone", "sms", "email", "in_person"] as const;

/**
 * POST /api/claims/:id/communications
 *   { claimant_id, direction, channel, summary, outcome }
 *
 * This is the single chokepoint for recording contact, and it is where
 * contact-preference enforcement lives (non-negotiable rule 7):
 *
 *   - No outbound contact of any kind to a do_not_contact claimant.
 *   - No outbound SMS without recorded SMS consent. The check is a hard
 *     refusal in code, not a warning — there is no other code path that
 *     can produce an outbound SMS record.
 *   - No outbound email without recorded email consent.
 */
communicationsRouter.post(
  "/:id/communications",
  requireRole(...canWrite),
  a(async (req, res) => {
    const { claimant_id, direction, channel, summary, outcome } = req.body ?? {};
    if (direction !== "inbound" && direction !== "outbound") {
      res.status(400).json({ error: "direction must be inbound or outbound" });
      return;
    }
    if (!CHANNELS.includes(channel)) {
      res.status(400).json({ error: "unknown channel" });
      return;
    }

    if (claimant_id) {
      const cr = await query<{
        sms_consent: boolean;
        email_consent: boolean;
        do_not_contact: boolean;
      }>(
        `SELECT cm.sms_consent, cm.email_consent, cm.do_not_contact
           FROM claimants cm
           JOIN claim_claimants cc ON cc.claimant_id = cm.id
          WHERE cm.id = $1 AND cc.claim_id = $2`,
        [claimant_id, req.params.id]
      );
      if (cr.rowCount === 0) {
        res.status(404).json({ error: "claimant not found on this claim" });
        return;
      }
      const c = cr.rows[0];
      if (direction === "outbound") {
        if (c.do_not_contact) {
          res.status(403).json({
            error: "claimant is marked do-not-contact; outbound contact is not permitted",
          });
          return;
        }
        if (channel === "sms" && !c.sms_consent) {
          res.status(403).json({
            error: "claimant has not consented to SMS; SMS cannot be sent or recorded",
          });
          return;
        }
        if (channel === "email" && !c.email_consent) {
          res.status(403).json({
            error: "claimant has not consented to email contact",
          });
          return;
        }
      }
    } else if (direction === "outbound") {
      res.status(400).json({ error: "outbound contact must name a claimant" });
      return;
    }

    const r = await query<{ id: string }>(
      `INSERT INTO communications
         (claim_id, claimant_id, direction, channel, staff_user_id, summary, outcome)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [
        req.params.id,
        claimant_id ?? null,
        direction,
        channel,
        req.session.user!.id,
        summary ?? null,
        outcome ?? null,
      ]
    );
    await writeAudit({
      userId: req.session.user!.id,
      action: "create",
      entityType: "communication",
      entityId: r.rows[0].id,
      ip: req.ip,
    });
    res.status(201).json({ id: r.rows[0].id });
  })
);
