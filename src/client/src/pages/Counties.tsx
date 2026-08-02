import { useState } from "react";
import { api } from "../api";
import type { SessionUser } from "../api";
import { Th, fmtDate, useAsync, useSort } from "../components/bits";

interface County {
  id: string;
  state: string;
  county_name: string;
  funds_holder_name: string | null;
  holder_contact: string | null;
  fee_cap_percent: string | null;
  filing_deadline_rule: string | null;
  contact_blackout_days: number | null;
  licensing_required: boolean;
  notes: string | null;
  last_verified_date: string | null;
}

const EMPTY: Partial<County> = {
  state: "",
  county_name: "",
  funds_holder_name: "",
  holder_contact: "",
  fee_cap_percent: null,
  filing_deadline_rule: "",
  contact_blackout_days: null,
  licensing_required: false,
  notes: "",
  last_verified_date: null,
};

export function Counties({ user }: { user: SessionUser }) {
  const isAdmin = user.role === "admin";
  const { data, error, reload } = useAsync(
    () => api<{ counties: County[] }>("/api/counties"),
    []
  );
  const [editing, setEditing] = useState<Partial<County> | null>(null);
  const [err, setErr] = useState<string | null>(null);

  const { sorted, sort, toggle } = useSort<County>(
    data?.counties,
    { key: "state", dir: 1 },
    {
      state: (c) => `${c.state} ${c.county_name}`,
      county: (c) => c.county_name,
      holder: (c) => c.funds_holder_name,
      cap: (c) => (c.fee_cap_percent == null ? null : Number(c.fee_cap_percent)),
      blackout: (c) => c.contact_blackout_days,
      verified: (c) => c.last_verified_date,
    }
  );

  const save = async () => {
    if (!editing) return;
    setErr(null);
    try {
      if (editing.id) {
        await api(`/api/counties/${editing.id}`, { method: "PUT", body: editing });
      } else {
        await api("/api/counties", { method: "POST", body: editing });
      }
      setEditing(null);
      reload();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "failed");
    }
  };

  const remove = async (id: string) => {
    setErr(null);
    try {
      await api(`/api/counties/${id}`, { method: "DELETE" });
      reload();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "failed");
    }
  };

  return (
    <>
      <div className="pagehead">
        <h1>County Rules</h1>
        <div className="count">
          fee caps, deadlines, and blackout periods — the source of truth that gates every action
        </div>
        {isAdmin && (
          <button className="primary" onClick={() => setEditing({ ...EMPTY })}>
            Add county
          </button>
        )}
      </div>
      {!isAdmin && (
        <div className="sub" style={{ marginBottom: 8 }}>
          Read-only: county rules are edited by administrators.
        </div>
      )}
      {error && <div className="error">{error}</div>}
      {err && <div className="error">{err}</div>}

      {editing && (
        <div className="card">
          <div className="cardhead">{editing.id ? "Edit county" : "New county"}</div>
          <div className="cardbody">
            <div className="formgrid">
              <div className="field">
                <label>State (2 letters)</label>
                <input
                  className="mono"
                  maxLength={2}
                  value={editing.state ?? ""}
                  onChange={(e) =>
                    setEditing({ ...editing, state: e.target.value.toUpperCase() })
                  }
                />
              </div>
              <div className="field">
                <label>County name</label>
                <input
                  value={editing.county_name ?? ""}
                  onChange={(e) => setEditing({ ...editing, county_name: e.target.value })}
                />
              </div>
              <div className="field">
                <label>Funds holder</label>
                <input
                  value={editing.funds_holder_name ?? ""}
                  onChange={(e) =>
                    setEditing({ ...editing, funds_holder_name: e.target.value })
                  }
                />
              </div>
              <div className="field">
                <label>Holder contact</label>
                <input
                  value={editing.holder_contact ?? ""}
                  onChange={(e) => setEditing({ ...editing, holder_contact: e.target.value })}
                />
              </div>
              <div className="field">
                <label>Fee cap % (blank = unresearched)</label>
                <input
                  className="mono"
                  inputMode="decimal"
                  value={editing.fee_cap_percent ?? ""}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      fee_cap_percent: e.target.value === "" ? null : e.target.value,
                    })
                  }
                />
              </div>
              <div className="field">
                <label>Filing deadline rule</label>
                <input
                  value={editing.filing_deadline_rule ?? ""}
                  onChange={(e) =>
                    setEditing({ ...editing, filing_deadline_rule: e.target.value })
                  }
                />
              </div>
              <div className="field">
                <label>Contact blackout (days)</label>
                <input
                  className="mono"
                  inputMode="numeric"
                  value={editing.contact_blackout_days ?? ""}
                  onChange={(e) =>
                    setEditing({
                      ...editing,
                      contact_blackout_days:
                        e.target.value === "" ? null : Number(e.target.value),
                    })
                  }
                />
              </div>
              <div className="field">
                <label>Last verified</label>
                <input
                  type="date"
                  value={editing.last_verified_date ?? ""}
                  onChange={(e) =>
                    setEditing({ ...editing, last_verified_date: e.target.value || null })
                  }
                />
              </div>
              <div className="field">
                <label>
                  <input
                    type="checkbox"
                    checked={editing.licensing_required ?? false}
                    onChange={(e) =>
                      setEditing({ ...editing, licensing_required: e.target.checked })
                    }
                  />{" "}
                  Licensing required
                </label>
              </div>
              <div className="field" style={{ gridColumn: "1 / -1" }}>
                <label>Notes</label>
                <textarea
                  rows={2}
                  value={editing.notes ?? ""}
                  onChange={(e) => setEditing({ ...editing, notes: e.target.value })}
                />
              </div>
            </div>
            <div className="formrow" style={{ marginTop: 10 }}>
              <button className="primary" onClick={save}>
                Save
              </button>
              <button onClick={() => setEditing(null)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      <div className="card tablewrap">
        <table>
          <thead>
            <tr>
              <Th label="State" sortKey="state" sort={sort} onSort={toggle} />
              <Th label="County" sortKey="county" sort={sort} onSort={toggle} />
              <Th label="Funds holder" sortKey="holder" sort={sort} onSort={toggle} />
              <Th label="Fee cap" sortKey="cap" sort={sort} onSort={toggle} num />
              <Th label="Deadline rule" />
              <Th label="Blackout" sortKey="blackout" sort={sort} onSort={toggle} num />
              <Th label="Licensing" />
              <Th label="Verified" sortKey="verified" sort={sort} onSort={toggle} />
              {isAdmin && <th />}
            </tr>
          </thead>
          <tbody>
            {sorted?.map((c) => (
              <tr key={c.id}>
                <td className="mono">{c.state}</td>
                <td>{c.county_name}</td>
                <td className="sub">{c.funds_holder_name ?? "—"}</td>
                <td className="num">
                  {c.fee_cap_percent == null ? (
                    <span style={{ color: "var(--amber)", fontWeight: 600 }}>UNRESEARCHED</span>
                  ) : (
                    <span className="mono">{Number(c.fee_cap_percent)}%</span>
                  )}
                </td>
                <td style={{ whiteSpace: "normal" }}>{c.filing_deadline_rule ?? "—"}</td>
                <td className="num mono">
                  {c.contact_blackout_days != null ? `${c.contact_blackout_days} d` : "—"}
                </td>
                <td>{c.licensing_required ? "required" : "—"}</td>
                <td className="mono">{fmtDate(c.last_verified_date)}</td>
                {isAdmin && (
                  <td>
                    <button onClick={() => setEditing({ ...c })}>Edit</button>{" "}
                    <button className="danger" onClick={() => remove(c.id)}>
                      Delete
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
