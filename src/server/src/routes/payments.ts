import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a, isDbRuleViolation } from "../util.js";
import { requireRole } from "../middleware/auth.js";
import { stripe, stripeEnabled } from "../stripe.js";
import { config } from "../config.js";
import { log } from "../log.js";

export const paymentsRouter = Router();

/** GET /api/claims/:id/payments — invoices for a claim. */
paymentsRouter.get(
  "/claims/:id/payments",
  a(async (req, res) => {
    const r = await query(
      `SELECT p.id, p.agreement_id, p.amount, p.currency, p.status,
              p.checkout_url, p.created_at, p.paid_at, u.email AS created_by_email
         FROM payments p
         LEFT JOIN users u ON u.id = p.created_by
        WHERE p.claim_id = $1
        ORDER BY p.created_at DESC`,
      [req.params.id]
    );
    res.json({ payments: r.rows, stripe_enabled: stripeEnabled() });
  })
);

/**
 * POST /api/claims/:id/payments  { agreement_id, amount }
 *
 * Creates a fee invoice backed by a Stripe Checkout session. The database
 * refuses the row unless the agreement is executed, live, on this claim,
 * and the total stays within the agreed fee — the county fee cap was
 * already enforced when the agreement was created (rule 4 chain).
 *
 * The payment description sent to Stripe carries the claim ref only —
 * never a claimant name (rule 5 applies to third parties too).
 */
paymentsRouter.post(
  "/claims/:id/payments",
  requireRole("admin", "case_manager"),
  a(async (req, res) => {
    const { agreement_id, amount } = req.body ?? {};
    if (typeof agreement_id !== "string" || typeof amount !== "number" || !(amount > 0)) {
      res.status(400).json({ error: "agreement_id and a positive amount are required" });
      return;
    }
    if (!stripeEnabled()) {
      res.status(503).json({
        error:
          "Stripe is not configured. Set STRIPE_SECRET_KEY (and STRIPE_WEBHOOK_SECRET) in .env, then restart.",
      });
      return;
    }

    const claimR = await query<{ ref: string }>(
      "SELECT ref FROM claims WHERE id = $1",
      [req.params.id]
    );
    if (claimR.rowCount === 0) {
      res.status(404).json({ error: "claim not found" });
      return;
    }

    // Insert first: the trigger vets the agreement before Stripe is called.
    let paymentId: string;
    try {
      const r = await query<{ id: string }>(
        `INSERT INTO payments (claim_id, agreement_id, amount, created_by)
         VALUES ($1, $2, $3, $4)
         RETURNING id`,
        [req.params.id, agreement_id, amount, req.session.user!.id]
      );
      paymentId = r.rows[0].id;
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({ error: (err as Error).message });
        return;
      }
      throw err;
    }

    try {
      const session = await stripe().checkout.sessions.create({
        mode: "payment",
        line_items: [
          {
            price_data: {
              currency: "usd",
              unit_amount: Math.round(amount * 100),
              product_data: {
                name: `Harborline fee — claim ${claimR.rows[0].ref}`,
              },
            },
            quantity: 1,
          },
        ],
        client_reference_id: paymentId,
        success_url: `${config.appUrl}/claims/${req.params.id}?payment=success`,
        cancel_url: `${config.appUrl}/claims/${req.params.id}?payment=canceled`,
      });
      await query(
        `UPDATE payments
            SET stripe_checkout_session_id = $2, checkout_url = $3
          WHERE id = $1`,
        [paymentId, session.id, session.url]
      );
      await writeAudit({
        userId: req.session.user!.id,
        action: "create",
        entityType: "payment",
        entityId: paymentId,
        ip: req.ip,
      });
      res.status(201).json({ id: paymentId, checkout_url: session.url });
    } catch (err) {
      // Stripe refused or is unreachable: mark the row canceled so it never
      // counts against the agreed fee, then surface a clean error.
      await query("UPDATE payments SET status = 'canceled' WHERE id = $1", [paymentId]);
      log.error("stripe checkout create failed", {
        paymentId,
        kind: (err as Error).name,
      });
      res.status(502).json({
        error: "Stripe rejected the checkout session; the invoice was not created",
      });
    }
  })
);

/** POST /api/payments/:id/cancel — cancel a pending invoice. */
paymentsRouter.post(
  "/payments/:id/cancel",
  requireRole("admin", "case_manager"),
  a(async (req, res) => {
    const r = await query(
      `UPDATE payments SET status = 'canceled'
        WHERE id = $1 AND status = 'pending'
        RETURNING id`,
      [req.params.id]
    );
    if (r.rowCount === 0) {
      res.status(404).json({ error: "pending payment not found" });
      return;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "update",
      entityType: "payment",
      entityId: req.params.id,
      fieldName: "status",
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);
