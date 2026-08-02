import dotenv from "dotenv";
import path from "node:path";
import { fileURLToPath } from "node:url";

// .env lives at the repository root, two levels up from src/server.
const here = path.dirname(fileURLToPath(import.meta.url));
export const repoRoot = path.resolve(here, "..", "..", "..");
dotenv.config({ path: path.join(repoRoot, ".env") });

function required(name: string): string {
  const v = process.env[name];
  if (!v) {
    // Startup config errors never echo values, only names.
    throw new Error(`missing required environment variable ${name}`);
  }
  return v;
}

export const config = {
  databaseUrl: required("DATABASE_URL"),
  sessionSecret: required("SESSION_SECRET"),
  piiEncryptionKey: required("PII_ENCRYPTION_KEY"),
  nodeEnv: process.env.NODE_ENV ?? "development",
  port: Number(process.env.PORT ?? 3000),
  // Stripe is optional: without a secret key the payments module stays
  // visible but clearly disabled. Keys never have defaults.
  stripeSecretKey: process.env.STRIPE_SECRET_KEY ?? null,
  stripeWebhookSecret: process.env.STRIPE_WEBHOOK_SECRET ?? null,
  appUrl: process.env.APP_URL ?? `http://localhost:${process.env.PORT ?? 3000}`,
  // Session timeout: 15 minutes idle, per spec. Rolling: every request
  // pushes the expiry forward.
  sessionIdleMs: 15 * 60 * 1000,
};
