import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api";
import {
  FilingWindow,
  Stage,
  Surplus,
  Th,
  useAsync,
  useRowNav,
  useSort,
} from "../components/bits";

interface QueueRow {
  id: string;
  ref: string;
  stage: string;
  surplus_amount: string | null;
  surplus_verified: boolean;
  filing_deadline: string | null;
  days_left: number | null;
  county_name: string;
  state: string;
  owner_email: string | null;
  primary_claimant: string | null;
}

export function Queue() {
  const navigate = useNavigate();
  const [all, setAll] = useState(false);
  const { data, error } = useAsync(
    () => api<{ claims: QueueRow[] }>(`/api/queue${all ? "?all=1" : ""}`),
    [all]
  );

  const { sorted, sort, toggle } = useSort<QueueRow>(
    data?.claims,
    { key: "days_left", dir: 1 },
    {
      ref: (r) => r.ref,
      claimant: (r) => r.primary_claimant,
      county: (r) => `${r.state} ${r.county_name}`,
      stage: (r) => r.stage,
      surplus: (r) => (r.surplus_amount == null ? null : Number(r.surplus_amount)),
      days_left: (r) => r.days_left,
      owner: (r) => r.owner_email,
    }
  );

  const rows = sorted ?? [];
  const sel = useRowNav(rows.length, (i) => rows[i] && navigate(`/claims/${rows[i].id}`));

  return (
    <>
      <div className="pagehead">
        <h1>{all ? "All Open Claims" : "My Queue"}</h1>
        <div className="count">
          {data ? `${rows.length} files · sorted by ${sort.key.replace("_", " ")}` : "loading…"}
        </div>
        <button onClick={() => setAll(!all)}>{all ? "Show my queue" : "Show all open"}</button>
      </div>
      {error && <div className="error">{error}</div>}
      <div className="card">
        <table>
          <thead>
            <tr>
              <Th label="Ref" sortKey="ref" sort={sort} onSort={toggle} />
              <Th label="Claimant" sortKey="claimant" sort={sort} onSort={toggle} />
              <Th label="County" sortKey="county" sort={sort} onSort={toggle} />
              <Th label="Stage" sortKey="stage" sort={sort} onSort={toggle} />
              <Th label="Surplus" sortKey="surplus" sort={sort} onSort={toggle} num />
              <Th label="Filing window" sortKey="days_left" sort={sort} onSort={toggle} />
              <Th label="Owner" sortKey="owner" sort={sort} onSort={toggle} />
            </tr>
          </thead>
          <tbody>
            {rows.map((c, i) => (
              <tr
                key={c.id}
                className={`rowlink${i === sel ? " sel" : ""}`}
                onClick={() => navigate(`/claims/${c.id}`)}
              >
                <td className="mono">
                  <a
                    className="ref"
                    href={`/claims/${c.id}`}
                    onClick={(e) => e.preventDefault()}
                    tabIndex={-1}
                  >
                    {c.ref}
                  </a>
                </td>
                <td>{c.primary_claimant ?? <span className="faint">—</span>}</td>
                <td>
                  {c.county_name.replace(/^ZZTEST /, "")}, {c.state}
                </td>
                <td>
                  <Stage value={c.stage} />
                </td>
                <td className="num">
                  <Surplus amount={c.surplus_amount} verified={c.surplus_verified} />
                </td>
                <td>
                  <FilingWindow daysLeft={c.days_left} />
                </td>
                <td className="sub">{c.owner_email?.split("@")[0] ?? "—"}</td>
              </tr>
            ))}
            {data && rows.length === 0 && (
              <tr>
                <td colSpan={7} className="faint">
                  Nothing in this queue.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="kbdhint">
        <kbd>↑</kbd> <kbd>↓</kbd> select row · <kbd>Enter</kbd> open · <kbd>/</kbd> search ·
        click a header to sort
      </div>
    </>
  );
}
