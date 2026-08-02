import { Router } from "express";
import { query } from "../db.js";
import { writeAudit } from "../audit.js";
import { a, isDbRuleViolation } from "../util.js";
import { requireRole } from "../middleware/auth.js";

export const countiesRouter = Router();

/** All staff can read county rules; only admins may change them. */
countiesRouter.get(
  "/",
  a(async (_req, res) => {
    const r = await query(
      `SELECT id, state, county_name, funds_holder_name, holder_contact,
              fee_cap_percent, filing_deadline_rule, contact_blackout_days,
              licensing_required, notes, last_verified_date
         FROM counties
        ORDER BY state, county_name`
    );
    res.json({ counties: r.rows });
  })
);

const FIELDS = [
  "state",
  "county_name",
  "funds_holder_name",
  "holder_contact",
  "fee_cap_percent",
  "filing_deadline_rule",
  "contact_blackout_days",
  "licensing_required",
  "notes",
  "last_verified_date",
] as const;

function pick(body: Record<string, unknown>) {
  return FIELDS.map((f) => {
    const v = body[f];
    return v === "" || v === undefined ? null : v;
  });
}

countiesRouter.post(
  "/",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const r = await query<{ id: string }>(
        `INSERT INTO counties (${FIELDS.join(", ")})
         VALUES ($1,$2,$3,$4,$5,$6,$7,COALESCE($8,false),$9,$10)
         RETURNING id`,
        pick(req.body ?? {})
      );
      await writeAudit({
        userId: req.session.user!.id,
        action: "create",
        entityType: "county",
        entityId: r.rows[0].id,
        ip: req.ip,
      });
      res.status(201).json({ id: r.rows[0].id });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({ error: "county rejected: check state code, uniqueness, and ranges" });
        return;
      }
      throw err;
    }
  })
);

countiesRouter.put(
  "/:id",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const sets = FIELDS.map((f, i) =>
        f === "licensing_required" ? `${f} = COALESCE($${i + 2}, false)` : `${f} = $${i + 2}`
      ).join(", ");
      const r = await query(
        `UPDATE counties SET ${sets} WHERE id = $1 RETURNING id`,
        [req.params.id, ...pick(req.body ?? {})]
      );
      if (r.rowCount === 0) {
        res.status(404).json({ error: "county not found" });
        return;
      }
      await writeAudit({
        userId: req.session.user!.id,
        action: "update",
        entityType: "county",
        entityId: req.params.id,
        ip: req.ip,
      });
      res.json({ ok: true });
    } catch (err) {
      if (isDbRuleViolation(err)) {
        res.status(400).json({ error: "county rejected: check state code, uniqueness, and ranges" });
        return;
      }
      throw err;
    }
  })
);

/**
 * POST /api/counties/import  { csv }
 *
 * Bulk-load county rules from pasted CSV (admin only). Header row required:
 *   state,county_name,funds_holder_name,holder_contact,fee_cap_percent,
 *   filing_deadline_rule,contact_blackout_days,licensing_required,notes,
 *   last_verified_date
 * Leave fee_cap_percent empty for unresearched — the agreements trigger
 * treats an unknown cap as "no fees permitted", never as unlimited.
 * Each row is validated independently; failures are reported per line.
 */
countiesRouter.post(
  "/import",
  requireRole("admin"),
  a(async (req, res) => {
    const { csv } = req.body ?? {};
    if (typeof csv !== "string" || csv.trim() === "") {
      res.status(400).json({ error: "csv text is required" });
      return;
    }
    const lines = parseCsv(csv);
    if (lines.length < 2) {
      res.status(400).json({ error: "expected a header row plus at least one data row" });
      return;
    }
    const header = lines[0].map((h) => h.trim().toLowerCase());
    const missing = ["state", "county_name"].filter((c) => !header.includes(c));
    if (missing.length) {
      res.status(400).json({ error: `missing required columns: ${missing.join(", ")}` });
      return;
    }

    const inserted: string[] = [];
    const errors: Array<{ line: number; error: string }> = [];
    for (let i = 1; i < lines.length; i++) {
      const cells = lines[i];
      if (cells.length === 1 && cells[0].trim() === "") continue;
      const row: Record<string, unknown> = {};
      header.forEach((h, j) => {
        const v = (cells[j] ?? "").trim();
        row[h] = v === "" ? null : v;
      });
      if (row.licensing_required != null) {
        row.licensing_required = ["true", "yes", "1", "y"].includes(
          String(row.licensing_required).toLowerCase()
        );
      }
      try {
        const r = await query<{ id: string }>(
          `INSERT INTO counties (${FIELDS.join(", ")})
           VALUES ($1,$2,$3,$4,$5,$6,$7,COALESCE($8,false),$9,$10)
           RETURNING id`,
          FIELDS.map((f) => row[f] ?? null)
        );
        inserted.push(r.rows[0].id);
        await writeAudit({
          userId: req.session.user!.id,
          action: "create",
          entityType: "county",
          entityId: r.rows[0].id,
          reason: "csv import",
          ip: req.ip,
        });
      } catch (err) {
        const code = (err as { code?: string }).code;
        errors.push({
          line: i + 1,
          error:
            code === "23505"
              ? "duplicate state + county"
              : code === "23514"
                ? "failed a validity check (state code, percent range, or day count)"
                : "rejected",
        });
      }
    }
    res.json({ inserted: inserted.length, errors });
  })
);

/** Minimal CSV parser: quoted fields, embedded commas and doubled quotes. */
function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = "";
  let inQuotes = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (inQuotes) {
      if (ch === '"' && text[i + 1] === '"') {
        cell += '"';
        i++;
      } else if (ch === '"') {
        inQuotes = false;
      } else {
        cell += ch;
      }
    } else if (ch === '"') {
      inQuotes = true;
    } else if (ch === ",") {
      row.push(cell);
      cell = "";
    } else if (ch === "\n" || ch === "\r") {
      if (ch === "\r" && text[i + 1] === "\n") i++;
      row.push(cell);
      rows.push(row);
      row = [];
      cell = "";
    } else {
      cell += ch;
    }
  }
  if (cell !== "" || row.length) {
    row.push(cell);
    rows.push(row);
  }
  return rows;
}

countiesRouter.delete(
  "/:id",
  requireRole("admin"),
  a(async (req, res) => {
    try {
      const r = await query("DELETE FROM counties WHERE id = $1 RETURNING id", [
        req.params.id,
      ]);
      if (r.rowCount === 0) {
        res.status(404).json({ error: "county not found" });
        return;
      }
    } catch (err) {
      const code = (err as { code?: string }).code;
      if (code === "23503") {
        res.status(400).json({
          error: "county is referenced by existing claims and cannot be deleted",
        });
        return;
      }
      throw err;
    }
    await writeAudit({
      userId: req.session.user!.id,
      action: "delete",
      entityType: "county",
      entityId: req.params.id,
      ip: req.ip,
    });
    res.json({ ok: true });
  })
);
