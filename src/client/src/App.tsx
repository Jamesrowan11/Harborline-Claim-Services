import { useEffect, useState } from "react";
import {
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from "react-router-dom";
import { api, setUnauthorizedHandler } from "./api";
import type { SessionUser } from "./api";
import { Shell } from "./components/Shell";
import { Login } from "./pages/Login";
import { Queue } from "./pages/Queue";
import { CasePage } from "./pages/Case";
import { Counties } from "./pages/Counties";
import { AuditPage } from "./pages/Audit";

export function App() {
  const [user, setUser] = useState<SessionUser | null>(null);
  const [checked, setChecked] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null);
      navigate("/login");
    });
  }, [navigate]);

  useEffect(() => {
    api<{ user: SessionUser }>("/api/auth/session")
      .then((r) => setUser(r.user))
      .catch(() => setUser(null))
      .finally(() => setChecked(true));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (!checked) return null;

  if (!user) {
    return (
      <Routes>
        <Route path="/login" element={<Login onLogin={setUser} />} />
        <Route path="*" element={<Navigate to="/login" replace state={{ from: location }} />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route path="/login" element={<Login onLogin={setUser} />} />
      <Route element={<Shell user={user} />}>
        <Route path="/" element={<Queue />} />
        <Route path="/claims/:id" element={<CasePage user={user} />} />
        <Route path="/counties" element={<Counties user={user} />} />
        <Route path="/audit" element={<AuditPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
