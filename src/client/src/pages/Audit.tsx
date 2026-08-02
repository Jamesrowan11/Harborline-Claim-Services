import { useState } from "react";
import { api, apiDownload } from "../api";
import { ReasonModal, Th, fmtTs, useAsync, useSort } from "../components/bits";

interface AuditRow {
  id: number;
  occurred_at: string;
  action: string;
  entity_type: string;
  entity_id: string | null;
  field_name: string | null;
  reason: string | null;
  ip_address: string | null;
  user_email: string | null;
}

const ACTIONS = [
  "",
  "login",
  "login_failed",
  "logout",
  "view",
  "pii_reveal",
  "export",
  "create",
  "update",
  "delete",
];

export function AuditPage() {
  const [filters, setFilters] = useState({
    action: "",
    entity_type: "",
    entity_id: "",
    user_email: "",
    from: "",
    to: "",
  });
  const [applied, setApplied] = useState(filters);
  const [exporting, setExporting] = useState(false);

  const qs = new URLSearchParams(
    Object.entries(applied).filter(([, v]) => v !== "")
  ).toString();
  const { data, error } = useAsync(
    () => api<{ rows: AuditRow[] }>(`/api/audit${qs ? `?${qs}` : ""}`),
    [qs]
  );
  const { sorted, sort, toggle } = useSort<AuditRow>(
    data?.rows,
    { key: "when", dir: -1 },
    {
      when: (r) => r.occurred_at,
      user: (r) => r.user_email,
      action: (r) => r.action,
      entity: (r) => `${r.entity_type}:${r.entity_id ?? ""}`,
    }
  );

  const set = (k: keyof typeof filters) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setFilters({ ...filters, [k]: e.target.value });

  return (
    <>
      <div className="pagehead">
        <h1>Audit Log</h1>
        <div className="count">append-only · reads and writes alike</div>
        <button onClick={() => setExporting(true)}>Export CSV…</button>
      </div>

      <div className="card">
        <div className="cardbody">
          <div className="formrow">
            <div className="field">
              <label>Action</label>
              <select value={filters.action} onChange={set("action")}>
                {ACTIONS.map((a) => (
                  <option key={a} value={a}>
                    {a || "any"}
                  </option>
                ))}
              </select>
            </div>
            <div className="field">
              <label>Entity type</label>
              <input value={filters.entity_type} onChange={set("entity_type")} />
            </div>
            <div className="field">
              <label>Entity id</label>
              <input className="mono" value={filters.entity_id} onChange={set("entity_id")} />
            </div>
            <div className="field">
              <label>User email</label>
              <input value={filters.user_email} onChange={set("user_email")} />
            </div>
            <div className="field">
              <label>From</label>
              <input type="date" value={filters.from} onChange={set("from")} />
            </div>
            <div className="field">
              <label>To</label>
              <input type="date" value={filters.to} onChange={set("to")} />
            </div>
            <button className="primary" onClick={() => setApplied({ ...filters })}>
              Apply
            </button>
          </div>
        </div>
      </div>

      {error && <div className="error">{error}</div>}
      <div className="card tablewrap">
        <table>
          <thead>
            <tr>
              <Th label="When" sortKey="when" sort={sort} onSort={toggle} />
              <Th label="User" sortKey="user" sort={sort} onSort={toggle} />
              <Th label="Action" sortKey="action" sort={sort} onSort={toggle} />
              <Th label="Entity" sortKey="entity" sort={sort} onSort={toggle} />
              <Th label="Field" />
              <Th label="Reason" />
              <Th label="IP" />
            </tr>
          </thead>
          <tbody>
            {sorted?.map((r) => (
              <tr key={r.id}>
                <td className="mono">{fmtTs(r.occurred_at)}</td>
                <td className="sub">{r.user_email ?? "—"}</td>
                <td>{r.action}</td>
                <td className="mono sub">
                  {r.entity_type}
                  {r.entity_id ? `:${r.entity_id.slice(0, 8)}` : ""}
                </td>
                <td>{r.field_name ?? "—"}</td>
                <td style={{ whiteSpace: "normal" }}>{r.reason ?? "—"}</td>
                <td className="mono sub">{r.ip_address ?? "—"}</td>
              </tr>
            ))}
            {data && data.rows.length === 0 && (
              <tr>
                <td colSpan={7} className="faint">
                  No audit rows match.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {exporting && (
        <ReasonModal
          title="Export audit log"
          hint="State why this export is needed."
          onSubmit={(reason) =>
            apiDownload(
              "/api/audit/export",
              { reason, ...Object.fromEntries(Object.entries(applied).filter(([, v]) => v !== "")) },
              "audit-export.csv"
            )
          }
          onClose={() => setExporting(false)}
        />
      )}
    </>
  );
}
