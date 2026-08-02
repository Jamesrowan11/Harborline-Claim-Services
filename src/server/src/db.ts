import pg from "pg";
import { config } from "./config.js";

// Explicit SQL everywhere — no ORM. Every query is visible in the code so
// audit behavior can be reviewed alongside the statements it covers.
export const pool = new pg.Pool({ connectionString: config.databaseUrl });

export async function query<R extends pg.QueryResultRow = pg.QueryResultRow>(
  text: string,
  params: unknown[] = []
): Promise<pg.QueryResult<R>> {
  return pool.query<R>(text, params);
}
