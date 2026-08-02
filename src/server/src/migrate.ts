import fs from "node:fs";
import path from "node:path";
import { repoRoot } from "./config.js";
import { pool } from "./db.js";
import { log } from "./log.js";

/**
 * Applies db/migrations/*.sql in filename order, tracking what has already
 * run. The files are the same ones the README applies with psql; this
 * runner just automates the sequence.
 */
async function main(): Promise<void> {
  const dir = path.join(repoRoot, "db", "migrations");
  const files = fs
    .readdirSync(dir)
    .filter((f) => f.endsWith(".sql"))
    .sort();

  await pool.query(
    `CREATE TABLE IF NOT EXISTS schema_migrations (
       filename text PRIMARY KEY,
       applied_at timestamptz NOT NULL DEFAULT now()
     )`
  );
  const done = new Set(
    (await pool.query("SELECT filename FROM schema_migrations")).rows.map(
      (r) => r.filename
    )
  );

  for (const f of files) {
    if (done.has(f)) {
      log.info("already applied", { file: f });
      continue;
    }
    const sql = fs.readFileSync(path.join(dir, f), "utf8");
    await pool.query(sql);
    await pool.query("INSERT INTO schema_migrations (filename) VALUES ($1)", [f]);
    log.info("applied", { file: f });
  }
  await pool.end();
}

main().catch((err) => {
  log.error("migration failed", { message: (err as Error).message });
  process.exit(1);
});
