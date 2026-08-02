import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../api";
import type { SessionUser } from "../api";

export function Login({ onLogin }: { onLogin: (u: SessionUser) => void }) {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [totp, setTotp] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const r = await api<{ user: SessionUser }>("/api/auth/login", {
        method: "POST",
        body: { email, password, totp },
      });
      onLogin(r.user);
      navigate("/");
    } catch (err) {
      setError(err instanceof Error ? err.message : "login failed");
      setBusy(false);
    }
  };

  return (
    <div className="login-wrap">
      <div className="login-card">
        <div className="head">
          HARBORLINE <span>/ Claims Workstation</span>
        </div>
        <form onSubmit={submit}>
          <div className="field">
            <label htmlFor="email">Email</label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoFocus
              required
            />
          </div>
          <div className="field">
            <label htmlFor="password">Password</label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <div className="field">
            <label htmlFor="totp">Authenticator code</label>
            <input
              id="totp"
              className="mono"
              inputMode="numeric"
              pattern="[0-9]{6}"
              maxLength={6}
              value={totp}
              onChange={(e) => setTotp(e.target.value)}
              required
            />
          </div>
          {error && <div className="error">{error}</div>}
          <button className="primary" disabled={busy}>
            Sign in
          </button>
          <div className="sub">
            Sessions end after 15 minutes idle. All access is audited.
          </div>
        </form>
      </div>
    </div>
  );
}
