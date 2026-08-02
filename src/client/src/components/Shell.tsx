import { useEffect, useRef, useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { api, lastActivityAt, onActivity } from "../api";
import type { SessionUser } from "../api";

const IDLE_MS = 15 * 60 * 1000;

interface Meta {
  synthetic_data: boolean;
  queue_count: number;
  open_count: number;
  counties_count: number;
  audit_today_count: number;
}

interface SearchHit {
  id: string;
  ref: string;
  stage: string;
  closed: boolean;
  county_name: string;
  state: string;
  primary_claimant: string | null;
}

function Countdown({ onExpired }: { onExpired: () => void }) {
  const [remaining, setRemaining] = useState(IDLE_MS);
  useEffect(() => {
    const tick = () => {
      const left = IDLE_MS - (Date.now() - lastActivityAt);
      setRemaining(left);
      if (left <= 0) onExpired();
    };
    const iv = setInterval(tick, 1000);
    const off = onActivity(tick);
    return () => {
      clearInterval(iv);
      off();
    };
  }, [onExpired]);
  const s = Math.max(0, Math.floor(remaining / 1000));
  const mm = String(Math.floor(s / 60)).padStart(2, "0");
  const ss = String(s % 60).padStart(2, "0");
  return (
    <span className="timeout mono" title="Session idle timeout">
      {mm}:{ss}
    </span>
  );
}

/** Global search: "/" focuses it from anywhere; arrows + Enter select. */
function GlobalSearch() {
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [q, setQ] = useState("");
  const [hits, setHits] = useState<SearchHit[] | null>(null);
  const [sel, setSel] = useState(0);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const t = e.target as HTMLElement | null;
      const typing =
        t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.tagName === "SELECT");
      if (e.key === "/" && !typing) {
        e.preventDefault();
        inputRef.current?.focus();
        inputRef.current?.select();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    if (q.trim().length < 2) {
      setHits(null);
      return;
    }
    const t = setTimeout(() => {
      api<{ results: SearchHit[] }>(`/api/search?q=${encodeURIComponent(q.trim())}`)
        .then((r) => {
          setHits(r.results);
          setSel(0);
        })
        .catch(() => setHits(null));
    }, 180);
    return () => clearTimeout(t);
  }, [q]);

  const close = () => {
    setQ("");
    setHits(null);
  };

  const open = (hit: SearchHit) => {
    close();
    inputRef.current?.blur();
    navigate(`/claims/${hit.id}`);
  };

  return (
    <div className="gsearch">
      <input
        ref={inputRef}
        className="mono"
        placeholder="Search ref, claimant, county, parcel  ( / )"
        value={q}
        aria-label="Global search"
        onChange={(e) => setQ(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Escape") {
            close();
            inputRef.current?.blur();
          } else if (hits && e.key === "ArrowDown") {
            e.preventDefault();
            setSel((s) => Math.min(hits.length - 1, s + 1));
          } else if (hits && e.key === "ArrowUp") {
            e.preventDefault();
            setSel((s) => Math.max(0, s - 1));
          } else if (hits && e.key === "Enter" && hits[sel]) {
            open(hits[sel]);
          }
        }}
      />
      {hits && (
        <div className="results" role="listbox">
          {hits.map((h, i) => (
            <button
              key={h.id}
              className={`hit${i === sel ? " sel" : ""}`}
              role="option"
              aria-selected={i === sel}
              onMouseDown={(e) => {
                e.preventDefault();
                open(h);
              }}
            >
              <span className="mono">{h.ref}</span>
              {" · "}
              {h.primary_claimant ?? "—"}
              {" · "}
              <span className="sub">
                {h.county_name.replace(/^ZZTEST /, "")}, {h.state}
                {h.closed ? " · closed" : ""}
              </span>
            </button>
          ))}
          {hits.length === 0 && <div className="none">No matches.</div>}
        </div>
      )}
    </div>
  );
}

export function Shell({ user }: { user: SessionUser }) {
  const navigate = useNavigate();
  const [meta, setMeta] = useState<Meta | null>(null);

  useEffect(() => {
    api<Meta>("/api/meta").then(setMeta).catch(() => {});
  }, []);

  const logout = async () => {
    try {
      await api("/api/auth/logout", { method: "POST" });
    } finally {
      navigate("/login");
    }
  };

  const count = (n: number | undefined) =>
    n === undefined ? null : <span className="count mono">{n}</span>;

  return (
    <div className="appframe">
      <div className="topbar">
        <div className="brand">
          HARBORLINE <span>/ Claims Workstation</span>
        </div>
        <GlobalSearch />
        {meta?.synthetic_data && <div className="badge-synthetic">SYNTHETIC DEMO DATA</div>}
        <div className="spacer" />
        <div className="session">
          <span>
            {user.email} · {user.role}
          </span>
          <Countdown onExpired={() => navigate("/login")} />
          <button onClick={logout}>Sign out</button>
        </div>
      </div>
      <div className="shell">
        <nav className="rail">
          <div className="section">Work</div>
          <NavLink to="/" end className={({ isActive }) => (isActive ? "active" : "")}>
            <span>My Queue</span>
            {count(meta?.queue_count)}
          </NavLink>
          <div className="section">Reference</div>
          <NavLink to="/counties" className={({ isActive }) => (isActive ? "active" : "")}>
            <span>County Rules</span>
            {count(meta?.counties_count)}
          </NavLink>
          <div className="section">Oversight</div>
          <NavLink to="/audit" className={({ isActive }) => (isActive ? "active" : "")}>
            <span>Audit Log</span>
            {count(meta?.audit_today_count)}
          </NavLink>
        </nav>
        <main>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
