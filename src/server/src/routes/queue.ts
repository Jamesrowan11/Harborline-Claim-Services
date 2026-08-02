import { Router } from "express";
import { query } from "../db.js";
import { a } from "../util.js";

export const queueRouter = Router();

/**
 * GET /api/queue          — open claims assigned to me
 * GET /api/queue?all=1    — every open claim
 *
 * Sorted by days-until-filing-deadline ascending; the most urgent file is
 * always the first row. Surplus rows carry surplus_verified so the client
 * can never render verified and unverified amounts the same way.
 */
queueRouter.get(
  "/",
  a(async (req, res) => {
    const mineOnly = req.query.all !== "1";
    const params: unknown[] = [];
    let where = "cl.closed_at IS NULL";
    if (mineOnly) {
      params.push(req.session.user!.id);
      where += ` AND cl.assigned_to = $1`;
    }
    const r = await query(
      `SELECT cl.id, cl.ref, cl.stage, cl.surplus_amount, cl.surplus_verified,
              cl.filing_deadline,
              (cl.filing_deadline - CURRENT_DATE) AS days_left,
              c.county_name, c.state,
              u.email AS owner_email,
              pc.legal_name AS primary_claimant
         FROM claims cl
         JOIN counties c ON c.id = cl.county_id
         LEFT JOIN users u ON u.id = cl.assigned_to
         LEFT JOIN LATERAL (
           SELECT cm.legal_name
             FROM claim_claimants cc
             JOIN claimants cm ON cm.id = cc.claimant_id
            WHERE cc.claim_id = cl.id
            ORDER BY cc.is_primary DESC, cm.legal_name
            LIMIT 1
         ) pc ON true
        WHERE ${where}
        ORDER BY cl.filing_deadline ASC NULLS LAST, cl.ref`,
      params
    );
    res.json({ claims: r.rows });
  })
);
