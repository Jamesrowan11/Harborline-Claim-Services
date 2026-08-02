import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api";
import { FilingWindow, Stage, Surplus, useAsync } from "../components/bits";

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

  return (
    <>
      <div className="pagehead">
        <h1>{all ? "All Open Claims" : "My Queue"}</h1>
        <div className="count">
          {data ? `${data.claims.length} files · sorted by days until filing deadline` : "loading…"}
        </div>
        <button onClick={() => setAll(!all)}>{all ? "Show my queue" : "Show all open"}</button>
      </div>
      {error && <div className="error">{error}</div>}
      <div className="card tablewrap">
        <table>
          <thead>
            <tr>
              <th>Ref</th>
              <th>Claimant</th>
              <th>County</th>
              <th>Stage</th>
              <th className="num">Surplus</th>
              <th>Filing window</th>
              <th>Owner</th>
            </tr>
          </thead>
          <tbody>
            {data?.claims.map((c) => (
              <tr
                key={c.id}
                className="rowlink"
                onClick={() => navigate(`/claims/${c.id}`)}
              >
                <td className="mono">
                  <a
                    className="ref"
                    href={`/claims/${c.id}`}
                    onClick={(e) => e.preventDefault()}
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
            {data && data.claims.length === 0 && (
              <tr>
                <td colSpan={7} className="faint">
                  Nothing in this queue.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}
