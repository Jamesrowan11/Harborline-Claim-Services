import { Router } from "express";
import { query } from "../db.js";
import { a } from "../util.js";

export const searchRouter = Router();

/**
 * GET /api/search?q= — global search over claims: ref, parcel, primary
 * claimant name, county. Returns list rows only (same exposure as the
 * queue); opening a result goes through the audited case-view read.
 */
searchRouter.get(
  "/",
  a(async (req, res) => {
    const q = String(req.query.q ?? "").trim();
    if (q.length < 2) {
      res.json({ results: [] });
      return;
    }
    const like = `%${q}%`;
    const r = await query(
      `SELECT DISTINCT ON (cl.id)
              cl.id, cl.ref, cl.stage, cl.closed_at IS NOT NULL AS closed,
              c.county_name, c.state,
              pc.legal_name AS primary_claimant
         FROM claims cl
         JOIN counties c ON c.id = cl.county_id
         LEFT JOIN claim_claimants cc ON cc.claim_id = cl.id
         LEFT JOIN claimants cm ON cm.id = cc.claimant_id
         LEFT JOIN LATERAL (
           SELECT cm2.legal_name
             FROM claim_claimants cc2
             JOIN claimants cm2 ON cm2.id = cc2.claimant_id
            WHERE cc2.claim_id = cl.id
            ORDER BY cc2.is_primary DESC, cm2.legal_name
            LIMIT 1
         ) pc ON true
        WHERE cl.ref ILIKE $1
           OR cl.parcel_number ILIKE $1
           OR cm.legal_name ILIKE $1
           OR c.county_name ILIKE $1
        ORDER BY cl.id, cl.ref
        LIMIT 10`,
      [like]
    );
    res.json({ results: r.rows });
  })
);
