import { useEffect, useMemo, useRef, useState } from "react";

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

/** Small uppercase status pill. tone is signal only, never decoration. */
export function Pill({
  children,
  tone,
}: {
  children: React.ReactNode;
  tone?: "green" | "amber" | "red";
}) {
  return <span className={`pill${tone ? ` ${tone}` : ""}`}>{children}</span>;
}

export function Stage({ value }: { value: string }) {
  return <Pill>{value}</Pill>;
}

/* ------------------------------------------------------------------ */
/* Sorting                                                             */

export interface SortState {
  key: string;
  dir: 1 | -1;
}

export function useSort<T>(
  rows: T[] | undefined,
  initial: SortState,
  accessors: Record<string, (row: T) => unknown>
) {
  const [sort, setSort] = useState<SortState>(initial);
  const sorted = useMemo(() => {
    if (!rows) return rows;
    const acc = accessors[sort.key];
    if (!acc) return rows;
    return [...rows].sort((x, y) => {
      const a = acc(x);
      const b = acc(y);
      if (a == null && b == null) return 0;
      if (a == null) return 1; // nulls last regardless of direction
      if (b == null) return -1;
      if (typeof a === "number" && typeof b === "number")
        return (a - b) * sort.dir;
      return String(a).localeCompare(String(b)) * sort.dir;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, sort]);
  const toggle = (key: string) =>
    setSort((s) => (s.key === key ? { key, dir: s.dir === 1 ? -1 : 1 } : { key, dir: 1 }));
  return { sorted, sort, toggle };
}

export function Th({
  label,
  sortKey,
  sort,
  onSort,
  num,
}: {
  label: string;
  sortKey?: string;
  sort?: SortState;
  onSort?: (key: string) => void;
  num?: boolean;
}) {
  const active = sortKey && sort?.key === sortKey;
  const ariaSort = active ? (sort!.dir === 1 ? "ascending" : "descending") : "none";
  if (!sortKey || !onSort) {
    return <th className={num ? "num" : undefined}>{label}</th>;
  }
  return (
    <th
      className={`sortable${num ? " num" : ""}`}
      aria-sort={ariaSort as "ascending" | "descending" | "none"}
      tabIndex={0}
      onClick={() => onSort(sortKey)}
      onKeyDown={(e) => e.key === "Enter" && onSort(sortKey)}
    >
      {label}
      {active && <span className="dir">{sort!.dir === 1 ? " ▲" : " ▼"}</span>}
    </th>
  );
}

/* ------------------------------------------------------------------ */
/* Keyboard row navigation: arrows move, Enter opens.                  */

function typingTarget(e: KeyboardEvent): boolean {
  const t = e.target as HTMLElement | null;
  if (!t) return false;
  const tag = t.tagName;
  return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || t.isContentEditable;
}

export function useRowNav(count: number, onOpen: (index: number) => void) {
  const [sel, setSel] = useState(-1);
  const selRef = useRef(sel);
  selRef.current = sel;

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (typingTarget(e) || count === 0) return;
      if (document.querySelector(".modal-scrim")) return;
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        const next =
          e.key === "ArrowDown"
            ? Math.min(count - 1, selRef.current + 1)
            : Math.max(0, selRef.current - 1);
        setSel(next);
        document
          .querySelectorAll("tbody tr.rowlink")
          [next]?.scrollIntoView({ block: "nearest" });
      } else if (e.key === "Enter" && selRef.current >= 0) {
        e.preventDefault();
        onOpen(selRef.current);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [count, onOpen]);

  return sel;
}

/* ------------------------------------------------------------------ */

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

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

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
      <div className="modal" role="dialog" aria-label={title} onClick={(e) => e.stopPropagation()}>
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
