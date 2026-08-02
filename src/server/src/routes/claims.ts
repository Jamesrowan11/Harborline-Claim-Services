import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { decryptPII } from "../crypto.js";
import { a, isDbRuleViolation } from "../util.js";
import { canWrite, requireRole } from "../middleware/auth.js";

export const claimsRouter = Router();

/**
 * Masked rendering of a claimant row (non-negotiable rule 1). SSN shows
 * last four only; DOB shows nothing. Full values leave the server solely
 * through the reveal endpoint below, which audits first.
 */
function maskClaimant(row: Record<string, unknown>) {
  const ssnCipher = row.ssn_encrypted as string | null;
  let ssnMasked: string | null = null;
  if (ssnCipher) {
    const last4 = decryptPII(ssnCipher).replace(/\D/g, "").slice(-4);
    ssnMasked = `•••-••-${last4}`;
  }
  return {
    id: row.id,
    legal_name: row.legal_name,
    relationship_to_owner: row.relationship_to_owner,
    ssn_masked: ssnMasked,
    dob_masked: row.dob_encrypted ? "••/••/••••" : null,
    has_ssn: Boolean(row.ssn_encrypted),
    has_dob: Boolean(row.dob_encrypted),
    mailing_address: row.mailing_address,
    phone: row.phone,
    email: row.email,
    preferred_contact_method: row.preferred_contact_method,
    sms_consent: row.sms_consent,
    sms_consent_date: row.sms_consent_date,
    email_consent: row.email_consent,
    do_not_contact: row.do_not_contact,
    language: row.language,
    share_percent: row.share_percent,
    is_primary: row.is_primary,
  };
}

/**
 * GET /api/claims/:id — the full case file. Reading it is itself an
 * audited event (rule 2): the view row is written before any data is
 * returned.
 */
claimsRouter.get(
  "/:id",
  a(async (req, res) => {
    const id = req.params.id;
    const claimR = await query(
      `SELECT cl.*, (cl.filing_deadline - CURRENT_DATE) AS days_left,
              c.county_name, c.state, c.funds_holder_name, c.holder_contact,
              c.fee_cap_percent, c.filing_deadline_rule,
              c.contact_blackout_days, c.licensing_required,
              c.last_verified_date AS county_last_verified_date,
              u.email AS owner_email
         FROM claims cl
         JOIN counties c ON c.id = cl.county_id
         LEFT JOIN users u ON u.id = cl.assigned_to
        WHERE cl.id = $1`,
      [id]
    );
    if (claimR.rowCount === 0) {
      res.status(404).json({ error: "claim not found" });
      return;
    }

    await writeAudit({
      userId: req.session.user!.id,
      action: "view",
      entityType: "claim",
      entityId: id,
      ip: req.ip,
    });

    const [claimants, documents, requiredDocs, communications, agreements, audit] =
      await Promise.all([
        query(
          `SELECT cm.*, cc.share_percent, cc.is_primary
             FROM claim_claimants cc
             JOIN claimants cm ON cm.id = cc.claimant_id
            WHERE cc.claim_id = $1
            ORDER BY cc.is_primary DESC, cm.legal_name`,
          [id]
        ),
        query(
          `SELECT d.id, d.doc_type, d.filename, d.sha256, d.uploaded_at,
                  d.received_from, d.virus_scan_status, u.email AS uploaded_by_email
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.claim_id = $1
            ORDER BY d.uploaded_at DESC`,
          [id]
        ),
        query(
          `SELECT rd.id, rd.doc_type, rd.required, rd.satisfied_by_document_id,
                  rd.requested_date, rd.last_followup_date
             FROM required_documents rd
            WHERE rd.claim_id = $1
            ORDER BY rd.doc_type`,
          [id]
        ),
        query(
          `SELECT co.id, co.claimant_id, co.direction, co.channel, co.occurred_at,
                  co.summary, co.outcome, u.email AS staff_email,
                  cm.legal_name AS claimant_name
             FROM communications co
             LEFT JOIN users u ON u.id = co.staff_user_id
             LEFT JOIN claimants cm ON cm.id = co.claimant_id
            WHERE co.claim_id = $1
            ORDER BY co.occurred_at DESC`,
          [id]
        ),
        query(
          `SELECT ag.id, ag.fee_percent, ag.fee_amount, ag.generated_at,
                  ag.executed_at, ag.superseded_by_agreement_id, ag.void_reason
             FROM agreements ag
            WHERE ag.claim_id = $1
            ORDER BY ag.generated_at DESC`,
          [id]
        ),
        query(
          `SELECT al.id, al.occurred_at, al.action, al.entity_type, al.entity_id,
                  al.field_name, al.reason, u.email AS user_email
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
            WHERE (al.entity_type = 'claim' AND al.entity_id = $1::text)
               OR (al.entity_type = 'claimant' AND al.entity_id IN
                    (SELECT claimant_id::text FROM claim_claimants WHERE claim_id = $1::uuid))
               OR (al.entity_type = 'agreement' AND al.entity_id IN
                    (SELECT id::text FROM agreements WHERE claim_id = $1::uuid))
            ORDER BY al.occurred_at DESC
            LIMIT 200`,
          [id]
        ),
      ]);

    res.json({
      claim: claimR.rows[0],
      claimants: claimants.rows.map(maskClaimant),
      documents: documents.rows,
      required_documents: requiredDocs.rows,
      communications: communications.rows,
      agreements: agreements.rows,
      audit: audit.rows,
    });
  })
);

/**
 * POST /api/claims/:id/claimants/:claimantId/reveal  { field, reason }
 *
 * The only path that returns a full SSN or DOB. The audit row is written
 * first; if the database refuses it (reason under 8 characters), the
 * reveal never happens (rules 1 and 2).
 */
claimsRouter.post(
  "/:id/claimants/:claimantId/reveal",
  requireRole("admin", "case_manager", "researcher"),
  a(async (req, res) => {
    const { field, reason } = req.body ?? {};
    if (field !== "ssn" && field !== "dob") {
      res.status(400).json({ error: "field must be ssn or dob" });
      return;
    }
    const r = await query<{ ssn_encrypted: string | null; dob_encrypted: string | null }>(
      `SELECT cm.ssn_encrypted, cm.dob_encrypted
         FROM claimants cm
         JOIN claim_claimants cc ON cc.claimant_id = cm.id
        WHERE cm.id = $1 AND cc.claim_id = $2`,
      [req.params.claimantId, req.params.id]
    );
    if (r.rowCount === 0) {
      res.status(404).json({ error: "claimant not found on this claim" });
      return;
    }
    try {
      await writeAudit({
        userId: req.session.user!.id,
        action: "pii_reveal",
        entityType: "claimant",
        entityId: req.params.claimantId,
        fieldName: field,
        reason: typeof reason === "string" ? reason : null,
        ip: req.ip,
      });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({
          error: "a reason of at least 8 characters is required to reveal PII",
        });
        return;
      }
      throw err;
    }
    const cipher = field === "ssn" ? r.rows[0].ssn_encrypted : r.rows[0].dob_encrypted;
    if (!cipher) {
      res.status(404).json({ error: "field is not on file" });
      return;
    }
    res.json({ field, value: decryptPII(cipher) });
  })
);

/**
 * POST /api/claims/:id/verify-surplus  { verified_date, source }
 * Marks the surplus verified with the funds holder. The claims CHECK
 * refuses an incomplete verification record.
 */
claimsRouter.post(
  "/:id/verify-surplus",
  requireRole(...canWrite),
  a(async (req, res) => {
    const { verified_date, source } = req.body ?? {};
    try {
      const r = await query(
        `UPDATE claims
            SET surplus_verified = true,
                surplus_verified_date = $2,
                surplus_verified_source = $3
          WHERE id = $1
          RETURNING id`,
        [req.params.id, verified_date ?? null, source ?? null]
      );
      if (r.rowCount === 0) {
        res.status(404).json({ error: "claim not found" });
        return;
      }
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({
          error:
            "verification requires both the date and the source of the funds holder's confirmation",
        });
        return;
      }
      throw err;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "update",
      entityType: "claim",
      entityId: req.params.id,
      fieldName: "surplus_verified",
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);

/** PATCH /api/claims/:id/stage  { stage } */
claimsRouter.patch(
  "/:id/stage",
  requireRole(...canWrite),
  a(async (req, res) => {
    const { stage } = req.body ?? {};
    if (typeof stage !== "string" || stage.trim() === "") {
      res.status(400).json({ error: "stage is required" });
      return;
    }
    const r = await query(
      "UPDATE claims SET stage = $2 WHERE id = $1 RETURNING id",
      [req.params.id, stage.trim()]
    );
    if (r.rowCount === 0) {
      res.status(404).json({ error: "claim not found" });
      return;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "update",
      entityType: "claim",
      entityId: req.params.id,
      fieldName: "stage",
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);
