import Stripe from "stripe";
import { config } from "./config.js";

/**
 * Stripe wiring. The client is created only when STRIPE_SECRET_KEY is set;
 * everything that needs it checks stripeEnabled() first and returns a clear
 * "not configured" error instead of a crash. No card data ever touches this
 * server — Stripe Checkout hosts the payment page.
 */

let client: Stripe | null = null;

export function stripeEnabled(): boolean {
  return Boolean(config.stripeSecretKey);
}

export function stripe(): Stripe {
  if (!config.stripeSecretKey) {
    throw new Error("Stripe is not configured (STRIPE_SECRET_KEY is not set)");
  }
  if (!client) {
    client = new Stripe(config.stripeSecretKey);
  }
  return client;
}
