import { useEffect, useState } from "react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { api, lastActivityAt, onActivity } from "../api";
import type { SessionUser } from "../api";

const IDLE_MS = 15 * 60 * 1000;

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

export function Shell({ user }: { user: SessionUser }) {
  const navigate = useNavigate();
  const [synthetic, setSynthetic] = useState(false);

  useEffect(() => {
    api<{ synthetic_data: boolean }>("/api/meta")
      .then((m) => setSynthetic(m.synthetic_data))
      .catch(() => {});
  }, []);

  const logout = async () => {
    try {
      await api("/api/auth/logout", { method: "POST" });
    } finally {
      navigate("/login");
    }
  };

  return (
    <>
      <div className="topbar">
        <div className="brand">
          HARBORLINE <span>/ Claims Workstation</span>
        </div>
        {synthetic && <div className="badge-synthetic">SYNTHETIC DEMO DATA</div>}
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
            My Queue
          </NavLink>
          <div className="section">Reference</div>
          <NavLink to="/counties" className={({ isActive }) => (isActive ? "active" : "")}>
            County Rules
          </NavLink>
          <div className="section">Oversight</div>
          <NavLink to="/audit" className={({ isActive }) => (isActive ? "active" : "")}>
            Audit Log
          </NavLink>
        </nav>
        <main>
          <Outlet />
        </main>
      </div>
    </>
  );
}
