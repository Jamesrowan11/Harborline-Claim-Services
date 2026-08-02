import {
  createCipheriv,
  createDecipheriv,
  randomBytes,
  timingSafeEqual,
} from "node:crypto";
import { config } from "./config.js";

/**
 * PII field encryption: AES-256-GCM.
 *
 * ssn_encrypted / dob_encrypted hold `v1:` + base64(iv || tag || ciphertext).
 * The key is PII_ENCRYPTION_KEY, a 32-byte base64 value from the
 * environment. Decrypted values exist only inside a request that has
 * already written its pii_reveal audit row.
 */

function key(): Buffer {
  const k = Buffer.from(config.piiEncryptionKey, "base64");
  if (k.length !== 32) {
    throw new Error("PII_ENCRYPTION_KEY must be 32 bytes of base64");
  }
  return k;
}

export function encryptPII(plaintext: string): string {
  const iv = randomBytes(12);
  const cipher = createCipheriv("aes-256-gcm", key(), iv);
  const ct = Buffer.concat([cipher.update(plaintext, "utf8"), cipher.final()]);
  const tag = cipher.getAuthTag();
  return "v1:" + Buffer.concat([iv, tag, ct]).toString("base64");
}

export function decryptPII(stored: string): string {
  if (!stored.startsWith("v1:")) {
    throw new Error("unrecognized ciphertext format");
  }
  const raw = Buffer.from(stored.slice(3), "base64");
  const iv = raw.subarray(0, 12);
  const tag = raw.subarray(12, 28);
  const ct = raw.subarray(28);
  const decipher = createDecipheriv("aes-256-gcm", key(), iv);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(ct), decipher.final()]).toString("utf8");
}

/** Constant-time string comparison for secrets. */
export function secretsEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ab.length !== bb.length) return false;
  return timingSafeEqual(ab, bb);
}
