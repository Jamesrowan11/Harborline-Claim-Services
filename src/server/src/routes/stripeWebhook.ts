import express, { Router } from "express";
import type Stripe from "stripe";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { log } from "../log.js";
import { config } from "../config.js";
import { stripe, stripeEnabled } from "../stripe.js";

/**
 * POST /api/stripe/webhook
 *
 * Mounted BEFORE the session and JSON middleware: Stripe signs the raw
 * request body, so this route reads it unparsed and verifies the signature
 * with STRIPE_WEBHOOK_SECRET before trusting a single byte. Status updates
 * land in the payments table and the audit log (user_id null = system).
 */
export const stripeWebhookRouter = Router();

stripeWebhookRouter.post(
  "/",
  express.raw({ type: "application/json" }),
  async (req, res) => {
    if (!stripeEnabled() || !config.stripeWebhookSecret) {
      res.status(503).json({ error: "stripe webhook not configured" });
      return;
    }
    let event: Stripe.Event;
    try {
      event = stripe().webhooks.constructEvent(
        req.body,
        req.headers["stripe-signature"] as string,
        config.stripeWebhookSecret
      );
    } catch {
      log.warn("stripe webhook signature verification failed");
      res.status(400).json({ error: "invalid signature" });
      return;
    }

    try {
      if (event.type === "checkout.session.completed") {
        const session = event.data.object as Stripe.Checkout.Session;
        const r = await query<{ id: string }>(
          `UPDATE payments
              SET status = 'paid',
                  paid_at = now(),
                  stripe_payment_intent_id = $2
            WHERE stripe_checkout_session_id = $1 AND status = 'pending'
            RETURNING id`,
          [session.id, typeof session.payment_intent === "string" ? session.payment_intent : null]
        );
        if (r.rowCount) {
          await writeAudit({
            userId: null,
            action: "update",
            entityType: "payment",
            entityId: r.rows[0].id,
            fieldName: "status",
          });
          log.info("payment marked paid", { paymentId: r.rows[0].id });
        }
      } else if (event.type === "checkout.session.expired") {
        const session = event.data.object as Stripe.Checkout.Session;
        await query(
          `UPDATE payments SET status = 'canceled'
            WHERE stripe_checkout_session_id = $1 AND status = 'pending'`,
          [session.id]
        );
      }
      res.json({ received: true });
    } catch (err) {
      log.error("stripe webhook handling failed", { kind: (err as Error).name });
      res.status(500).json({ error: "webhook handling failed" });
    }
  }
);
