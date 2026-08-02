export class ApiError extends Error {
  status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

type Listener = () => void;
const activityListeners = new Set<Listener>();
export let lastActivityAt = Date.now();

export function onActivity(fn: Listener): () => void {
  activityListeners.add(fn);
  return () => activityListeners.delete(fn);
}

function touch(): void {
  lastActivityAt = Date.now();
  activityListeners.forEach((fn) => fn());
}

let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(fn: () => void): void {
  onUnauthorized = fn;
}

export async function api<T>(
  path: string,
  options: { method?: string; body?: unknown } = {}
): Promise<T> {
  const res = await fetch(path, {
    method: options.method ?? "GET",
    credentials: "same-origin",
    headers: options.body !== undefined ? { "Content-Type": "application/json" } : {},
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  });
  if (res.status === 401 && !path.startsWith("/api/auth/")) {
    onUnauthorized?.();
    throw new ApiError(401, "session expired");
  }
  touch();
  if (!res.ok) {
    let message = `request failed (${res.status})`;
    try {
      const data = await res.json();
      if (typeof data?.error === "string") message = data.error;
    } catch {
      /* keep default */
    }
    throw new ApiError(res.status, message);
  }
  return (await res.json()) as T;
}

/** POST that returns a file download (audit export). */
export async function apiDownload(
  path: string,
  body: unknown,
  filename: string
): Promise<void> {
  const res = await fetch(path, {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (res.status === 401) {
    onUnauthorized?.();
    throw new ApiError(401, "session expired");
  }
  touch();
  if (!res.ok) {
    let message = `request failed (${res.status})`;
    try {
      const data = await res.json();
      if (typeof data?.error === "string") message = data.error;
    } catch {
      /* keep default */
    }
    throw new ApiError(res.status, message);
  }
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export interface SessionUser {
  id: string;
  email: string;
  role: "admin" | "case_manager" | "researcher" | "read_only";
}
