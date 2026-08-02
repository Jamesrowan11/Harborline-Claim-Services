/**
 * PII-safe logging (non-negotiable rule 5).
 *
 * No claimant names, SSNs, DOBs, or addresses may reach logs, console
 * output, or error messages. Only whitelisted, structurally-safe fields are
 * ever emitted: record ids, error codes, constraint names, table names.
 *
 * PostgreSQL error objects are the sharpest risk: `detail` can echo row
 * values (e.g. "Key (legal_name)=(...) already exists"), so pg errors are
 * reduced to code + constraint + table before logging.
 */

type SafeFields = Record<string, string | number | boolean | null | undefined>;

function line(level: string, msg: string, fields: SafeFields = {}): void {
  const parts = [new Date().toISOString(), level, msg];
  for (const [k, v] of Object.entries(fields)) {
    if (v !== undefined) parts.push(`${k}=${String(v)}`);
  }
  // eslint-disable-next-line no-console
  console.log(parts.join(" "));
}

export const log = {
  info: (msg: string, fields?: SafeFields) => line("INFO", msg, fields),
  warn: (msg: string, fields?: SafeFields) => line("WARN", msg, fields),
  error: (msg: string, fields?: SafeFields) => line("ERROR", msg, fields),
};

interface PgErrorish {
  code?: string;
  constraint?: string;
  table?: string;
  routine?: string;
}

/** Reduce any thrown value to fields that cannot contain PII. */
export function safeError(err: unknown): SafeFields {
  if (err instanceof Error) {
    const pg = err as Error & PgErrorish;
    if (pg.code) {
      // A pg error: log structure only. err.message and err.detail may
      // contain row values, so they are deliberately dropped.
      return {
        kind: "pg",
        code: pg.code,
        constraint: pg.constraint ?? null,
        table: pg.table ?? null,
      };
    }
    // Our own errors are written without PII; their message is safe.
    return { kind: err.name, message: err.message };
  }
  return { kind: "unknown" };
}
