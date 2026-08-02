import { useEffect, useState } from "react";

/** Money in tabular mono. Verified and unverified must never look alike. */
export function Surplus({
  amount,
  verified,
}: {
  amount: string | number | null;
  verified: boolean;
}) {
  if (amount == null) return <span className="faint">—</span>;
  const n = Number(amount);
  const formatted = n.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  if (verified) {
    return (
      <span className="mono surplus-verified">
        {formatted} <span className="flag">✓ ver</span>
      </span>
    );
  }
  return (
    <span className="mono surplus-unverified">
      ~{formatted}
      <span className="flag">UNVERIFIED</span>
    </span>
  );
}

/** Filing window: green &gt;60 days, amber 31–60, red ≤30. */
export function FilingWindow({ daysLeft }: { daysLeft: number | null }) {
  if (daysLeft == null) {
    return <span className="faint">no deadline on file</span>;
  }
  const cls = daysLeft > 60 ? "w-green" : daysLeft > 30 ? "w-amber" : "w-red";
  const pct = Math.max(2, Math.min(100, (daysLeft / 120) * 100));
  return (
    <div className={`window ${cls}`}>
      <div className="track">
        <div className="fill" style={{ width: `${pct}%` }} />
      </div>
      <div className="days mono">{daysLeft} d</div>
    </div>
  );
}

export function Stage({ value }: { value: string }) {
  return <span className="stage">{value}</span>;
}

/** Modal that collects a reason string before a sensitive action. */
export function ReasonModal({
  title,
  hint,
  onSubmit,
  onClose,
}: {
  title: string;
  hint: string;
  onSubmit: (reason: string) => Promise<void>;
  onClose: () => void;
}) {
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);
    try {
      await onSubmit(reason);
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : "failed");
      setBusy(false);
    }
  };

  return (
    <div className="modal-scrim" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h2>{title}</h2>
        <div className="sub">{hint} This action is written to the audit log.</div>
        <input
          autoFocus
          placeholder="Reason (8+ characters)"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && !busy && submit()}
        />
        {error && <div className="error">{error}</div>}
        <div className="row">
          <button onClick={onClose}>Cancel</button>
          <button className="primary" disabled={busy || reason.trim().length < 8} onClick={submit}>
            Continue
          </button>
        </div>
      </div>
    </div>
  );
}

export function useAsync<T>(fn: () => Promise<T>, deps: unknown[]): {
  data: T | null;
  error: string | null;
  reload: () => void;
} {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);
  useEffect(() => {
    let live = true;
    fn().then(
      (d) => live && setData(d),
      (e) => live && setError(e instanceof Error ? e.message : "failed")
    );
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick]);
  return { data, error, reload: () => setTick((t) => t + 1) };
}

export function fmtDate(v: string | null): string {
  if (!v) return "—";
  return String(v).slice(0, 10);
}

export function fmtTs(v: string | null): string {
  if (!v) return "—";
  const d = new Date(v);
  return d.toISOString().replace("T", " ").slice(0, 16) + "Z";
}
