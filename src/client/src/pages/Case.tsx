import { useState } from "react";
import { useParams } from "react-router-dom";
import { api } from "../api";
import type { SessionUser } from "../api";
import {
  FilingWindow,
  Pill,
  ReasonModal,
  Stage,
  Surplus,
  fmtDate,
  fmtTs,
  useAsync,
} from "../components/bits";

interface Claimant {
  id: string;
  legal_name: string;
  relationship_to_owner: string | null;
  ssn_masked: string | null;
  dob_masked: string | null;
  has_ssn: boolean;
  has_dob: boolean;
  mailing_address: string | null;
  phone: string | null;
  email: string | null;
  preferred_contact_method: string | null;
  sms_consent: boolean;
  sms_consent_date: string | null;
  email_consent: boolean;
  do_not_contact: boolean;
  language: string;
  share_percent: string | null;
  is_primary: boolean;
}

interface CaseData {
  claim: Record<string, any>;
  claimants: Claimant[];
  documents: Record<string, any>[];
  required_documents: Record<string, any>[];
  communications: Record<string, any>[];
  agreements: Record<string, any>[];
  audit: Record<string, any>[];
}

const TABS = [
  "Overview",
  "Claimant",
  "Documents",
  "Chain of title",
  "Communications",
  "Agreement",
  "Audit",
] as const;

export function CasePage({ user }: { user: SessionUser }) {
  const { id } = useParams<{ id: string }>();
  const [tab, setTab] = useState<(typeof TABS)[number]>("Overview");
  const { data, error, reload } = useAsync(() => api<CaseData>(`/api/claims/${id}`), [id]);

  if (error) return <div className="error">{error}</div>;
  if (!data) return <div className="faint">loading…</div>;
  const { claim } = data;
  const canWrite = user.role !== "read_only";

  return (
    <>
      <div className="pagehead">
        <h1 className="mono">{claim.ref}</h1>
        <Stage value={claim.stage} />
        <div className="count">
          {String(claim.county_name).replace(/^ZZTEST /, "")}, {claim.state} · owner{" "}
          {claim.owner_email ?? "—"}
        </div>
        <div style={{ marginLeft: "auto" }}>
          <Surplus amount={claim.surplus_amount} verified={claim.surplus_verified} />
        </div>
      </div>

      <div className="tabs">
        {TABS.map((t) => (
          <button key={t} className={t === tab ? "active" : ""} onClick={() => setTab(t)}>
            {t}
          </button>
        ))}
      </div>

      {tab === "Overview" && <Overview data={data} canWrite={canWrite} reload={reload} />}
      {tab === "Claimant" && <ClaimantTab data={data} claimId={id!} canWrite={canWrite} />}
      {tab === "Documents" && <DocumentsTab data={data} />}
      {tab === "Chain of title" && <ChainTab data={data} />}
      {tab === "Communications" && (
        <CommunicationsTab data={data} claimId={id!} canWrite={canWrite} reload={reload} />
      )}
      {tab === "Agreement" && (
        <AgreementTab data={data} claimId={id!} user={user} reload={reload} />
      )}
      {tab === "Audit" && <AuditTab data={data} />}
    </>
  );
}

/* ------------------------------------------------------------------ */

function Overview({
  data,
  canWrite,
  reload,
}: {
  data: CaseData;
  canWrite: boolean;
  reload: () => void;
}) {
  const { claim } = data;
  const [verifyOpen, setVerifyOpen] = useState(false);
  const [source, setSource] = useState("");
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [err, setErr] = useState<string | null>(null);

  const requiredOutstanding = data.required_documents.filter(
    (rd) => rd.required && !rd.satisfied_by_document_id
  );
  const liveAgreement = data.agreements.find(
    (a) => !a.void_reason && !a.superseded_by_agreement_id
  );

  const blocking: string[] = [];
  if (claim.fee_cap_percent == null)
    blocking.push(
      "County fee cap is UNRESEARCHED — no agreement can be generated until the cap is entered in County Rules."
    );
  if (!claim.surplus_verified)
    blocking.push("Surplus is unverified — do not assert the amount to anyone.");
  if (claim.days_left != null && claim.days_left <= 30)
    blocking.push(`Filing deadline is ${claim.days_left} days away.`);
  for (const c of data.claimants) {
    if (c.do_not_contact) blocking.push(`${c.legal_name} is marked do-not-contact.`);
  }
  if (claim.licensing_required)
    blocking.push("This jurisdiction requires licensing — confirm coverage before filing.");

  const ready: Array<[string, boolean]> = [
    ["Surplus verified with funds holder", Boolean(claim.surplus_verified)],
    ["All required documents satisfied", requiredOutstanding.length === 0],
    ["Fee agreement executed", Boolean(liveAgreement?.executed_at)],
    [
      "Inside filing window",
      claim.days_left == null ? false : Number(claim.days_left) > 0,
    ],
  ];

  const verify = async () => {
    setErr(null);
    try {
      await api(`/api/claims/${claim.id}/verify-surplus`, {
        method: "POST",
        body: { verified_date: date, source },
      });
      setVerifyOpen(false);
      reload();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "failed");
    }
  };

  return (
    <>
      {blocking.length > 0 && (
        <div className="banner-blocking">
          <div className="title">Blocking issues</div>
          <ul>
            {blocking.map((b, i) => (
              <li key={i}>{b}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="card">
        <div className="cardhead">Filing readiness</div>
        <div className="cardbody">
          <ul className="checklist">
            {ready.map(([label, ok]) => (
              <li key={label}>
                <span className={ok ? "ok" : "no"}>{ok ? "✓" : "○"}</span> {label}
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="card">
        <div className="cardhead">File</div>
        <div className="cardbody">
          <dl className="kv">
            <dt>Property</dt>
            <dd>{claim.property_address ?? "—"}</dd>
            <dt>Parcel</dt>
            <dd className="mono">{claim.parcel_number ?? "—"}</dd>
            <dt>Sale</dt>
            <dd>
              {claim.sale_type ?? "—"} · <span className="mono">{fmtDate(claim.sale_date)}</span>
            </dd>
            <dt>Sale amount</dt>
            <dd className="mono">{Number(claim.sale_amount ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2 })}</dd>
            <dt>Debt satisfied</dt>
            <dd className="mono">{Number(claim.debt_satisfied ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2 })}</dd>
            <dt>Surplus</dt>
            <dd>
              <Surplus amount={claim.surplus_amount} verified={claim.surplus_verified} />
              {claim.surplus_verified && (
                <span className="sub">
                  {" "}
                  · verified {fmtDate(claim.surplus_verified_date)} — {claim.surplus_verified_source}
                </span>
              )}
            </dd>
            <dt>Filing deadline</dt>
            <dd>
              <span className="mono">{fmtDate(claim.filing_deadline)}</span>
              {"  "}
              <FilingWindow daysLeft={claim.days_left} />
            </dd>
            <dt>County rule</dt>
            <dd>{claim.filing_deadline_rule ?? "—"}</dd>
            <dt>Funds holder</dt>
            <dd>
              {claim.funds_holder_name ?? "—"}
              {claim.holder_contact && <span className="sub"> · {claim.holder_contact}</span>}
            </dd>
            <dt>Fee cap</dt>
            <dd>
              {claim.fee_cap_percent == null ? (
                <span style={{ color: "var(--amber)", fontWeight: 600 }}>UNRESEARCHED</span>
              ) : (
                <span className="mono">{Number(claim.fee_cap_percent)}%</span>
              )}
            </dd>
            <dt>Contact blackout</dt>
            <dd>{claim.contact_blackout_days != null ? `${claim.contact_blackout_days} days after sale` : "—"}</dd>
          </dl>
          {canWrite && !claim.surplus_verified && (
            <div style={{ marginTop: 10 }}>
              {!verifyOpen ? (
                <button onClick={() => setVerifyOpen(true)}>Record surplus verification…</button>
              ) : (
                <div className="formrow">
                  <div className="field">
                    <label>Verified date</label>
                    <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
                  </div>
                  <div className="field" style={{ flex: 1 }}>
                    <label>Source (who at the funds holder confirmed)</label>
                    <input value={source} onChange={(e) => setSource(e.target.value)} />
                  </div>
                  <button className="primary" onClick={verify}>
                    Record
                  </button>
                  <button onClick={() => setVerifyOpen(false)}>Cancel</button>
                </div>
              )}
              {err && <div className="error">{err}</div>}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

/* ------------------------------------------------------------------ */

function ClaimantTab({
  data,
  claimId,
  canWrite,
}: {
  data: CaseData;
  claimId: string;
  canWrite: boolean;
}) {
  return (
    <>
      {data.claimants.map((c) => (
        <ClaimantCard key={c.id} c={c} claimId={claimId} canWrite={canWrite} />
      ))}
      {data.claimants.length === 0 && <div className="faint">No claimants linked.</div>}
    </>
  );
}

function ClaimantCard({
  c,
  claimId,
  canWrite,
}: {
  c: Claimant;
  claimId: string;
  canWrite: boolean;
}) {
  const [revealed, setRevealed] = useState<{ ssn?: string; dob?: string }>({});
  const [asking, setAsking] = useState<"ssn" | "dob" | null>(null);

  const reveal = async (field: "ssn" | "dob", reason: string) => {
    const r = await api<{ value: string }>(
      `/api/claims/${claimId}/claimants/${c.id}/reveal`,
      { method: "POST", body: { field, reason } }
    );
    setRevealed((prev) => ({ ...prev, [field]: r.value }));
  };

  return (
    <div className="card">
      <div className="cardhead">
        {c.legal_name}
        {c.is_primary ? " · primary" : ""}
        {c.share_percent != null ? ` · ${Number(c.share_percent)}% share` : ""}
      </div>
      <div className="cardbody">
        <dl className="kv">
          <dt>Relationship</dt>
          <dd>{c.relationship_to_owner ?? "—"}</dd>
          <dt>SSN</dt>
          <dd>
            <span className="pii mono">
              {revealed.ssn ? (
                <span className="revealed">{revealed.ssn}</span>
              ) : (
                <span className="masked">{c.ssn_masked ?? "not on file"}</span>
              )}
              {canWrite && c.has_ssn && !revealed.ssn && (
                <button onClick={() => setAsking("ssn")}>Reveal…</button>
              )}
            </span>
          </dd>
          <dt>Date of birth</dt>
          <dd>
            <span className="pii mono">
              {revealed.dob ? (
                <span className="revealed">{revealed.dob}</span>
              ) : (
                <span className="masked">{c.dob_masked ?? "not on file"}</span>
              )}
              {canWrite && c.has_dob && !revealed.dob && (
                <button onClick={() => setAsking("dob")}>Reveal…</button>
              )}
            </span>
          </dd>
          <dt>Mailing address</dt>
          <dd>{c.mailing_address ?? "—"}</dd>
          <dt>Phone</dt>
          <dd className="mono">{c.phone ?? "—"}</dd>
          <dt>Email</dt>
          <dd>{c.email ?? "—"}</dd>
          <dt>Preferred contact</dt>
          <dd>{c.preferred_contact_method ?? "—"}</dd>
          <dt>SMS consent</dt>
          <dd>
            {c.sms_consent ? (
              <span className="notice">yes · {fmtDate(c.sms_consent_date)}</span>
            ) : (
              <span style={{ color: "var(--amber)" }}>no — SMS is blocked</span>
            )}
          </dd>
          <dt>Email consent</dt>
          <dd>{c.email_consent ? <span className="notice">yes</span> : "no"}</dd>
          <dt>Do not contact</dt>
          <dd>
            {c.do_not_contact ? (
              <span style={{ color: "var(--red)", fontWeight: 700 }}>
                YES — all outbound contact is blocked
              </span>
            ) : (
              "no"
            )}
          </dd>
          <dt>Language</dt>
          <dd>{c.language}</dd>
        </dl>
      </div>
      {asking && (
        <ReasonModal
          title={`Reveal ${asking.toUpperCase()} — ${c.legal_name}`}
          hint="State why you need the unmasked value."
          onSubmit={(reason) => reveal(asking, reason)}
          onClose={() => setAsking(null)}
        />
      )}
    </div>
  );
}

/* ------------------------------------------------------------------ */

function DocumentsTab({ data }: { data: CaseData }) {
  const satisfied = new Set(
    data.required_documents
      .filter((rd) => rd.satisfied_by_document_id)
      .map((rd) => rd.doc_type)
  );
  return (
    <>
      <div className="card">
        <div className="cardhead">Required documents</div>
        <div className="cardbody">
          <ul className="checklist">
            {data.required_documents.map((rd) => (
              <li key={rd.id}>
                <span className={satisfied.has(rd.doc_type) ? "ok" : "no"}>
                  {satisfied.has(rd.doc_type) ? "✓" : "○"}
                </span>
                {rd.doc_type}
                {!rd.satisfied_by_document_id && rd.requested_date && (
                  <span className="sub">requested {fmtDate(rd.requested_date)}</span>
                )}
              </li>
            ))}
            {data.required_documents.length === 0 && (
              <li className="faint">No checklist configured.</li>
            )}
          </ul>
        </div>
      </div>
      <div className="card tablewrap">
        <div className="cardhead">Uploaded</div>
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>Filename</th>
              <th>Received from</th>
              <th>Scan</th>
              <th>Uploaded</th>
              <th>SHA-256</th>
            </tr>
          </thead>
          <tbody>
            {data.documents.map((d) => (
              <tr key={d.id}>
                <td>{d.doc_type}</td>
                <td>{d.filename}</td>
                <td className="sub">{d.received_from ?? "—"}</td>
                <td>
                  <Pill
                    tone={
                      d.virus_scan_status === "clean"
                        ? "green"
                        : d.virus_scan_status === "infected"
                          ? "red"
                          : "amber"
                    }
                  >
                    {d.virus_scan_status}
                  </Pill>
                </td>
                <td className="mono">{fmtTs(d.uploaded_at)}</td>
                <td className="mono sub">{String(d.sha256).slice(0, 12)}…</td>
              </tr>
            ))}
            {data.documents.length === 0 && (
              <tr>
                <td colSpan={6} className="faint">
                  No documents uploaded.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}

function ChainTab({ data }: { data: CaseData }) {
  const chainDocs = data.documents.filter((d) =>
    ["chain_of_title", "deed", "title_abstract"].includes(d.doc_type)
  );
  return (
    <div className="card">
      <div className="cardhead">Chain of title</div>
      <div className="cardbody">
        {chainDocs.length === 0 ? (
          <div className="faint">
            No chain-of-title documents on file yet. Upload deeds and title
            abstracts under Documents; they appear here.
          </div>
        ) : (
          <ul className="checklist">
            {chainDocs.map((d) => (
              <li key={d.id}>
                <span className="ok">✓</span>
                {d.doc_type}: {d.filename}
                <span className="sub">{fmtTs(d.uploaded_at)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */

function CommunicationsTab({
  data,
  claimId,
  canWrite,
  reload,
}: {
  data: CaseData;
  claimId: string;
  canWrite: boolean;
  reload: () => void;
}) {
  const [direction, setDirection] = useState("outbound");
  const [channel, setChannel] = useState("phone");
  const [claimantId, setClaimantId] = useState(data.claimants[0]?.id ?? "");
  const [summary, setSummary] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const submit = async () => {
    setErr(null);
    setOk(null);
    try {
      await api(`/api/claims/${claimId}/communications`, {
        method: "POST",
        body: { claimant_id: claimantId || null, direction, channel, summary },
      });
      setSummary("");
      setOk("recorded");
      reload();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "failed");
    }
  };

  return (
    <>
      {canWrite && (
        <div className="card">
          <div className="cardhead">Log contact</div>
          <div className="cardbody">
            <div className="formrow">
              <div className="field">
                <label>Direction</label>
                <select value={direction} onChange={(e) => setDirection(e.target.value)}>
                  <option value="outbound">outbound</option>
                  <option value="inbound">inbound</option>
                </select>
              </div>
              <div className="field">
                <label>Channel</label>
                <select value={channel} onChange={(e) => setChannel(e.target.value)}>
                  {["phone", "mail", "sms", "email", "in_person"].map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field">
                <label>Claimant</label>
                <select value={claimantId} onChange={(e) => setClaimantId(e.target.value)}>
                  {data.claimants.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.legal_name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="field" style={{ flex: 1 }}>
                <label>Summary</label>
                <input value={summary} onChange={(e) => setSummary(e.target.value)} />
              </div>
              <button className="primary" onClick={submit}>
                Record
              </button>
            </div>
            {err && <div className="error">{err}</div>}
            {ok && <div className="notice">{ok}</div>}
            <div className="sub" style={{ marginTop: 6 }}>
              Outbound SMS without recorded consent, and any outbound contact to a
              do-not-contact claimant, is refused — not warned about.
            </div>
          </div>
        </div>
      )}
      <div className="card tablewrap">
        <div className="cardhead">History</div>
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Dir</th>
              <th>Channel</th>
              <th>Claimant</th>
              <th>Staff</th>
              <th>Summary</th>
              <th>Outcome</th>
            </tr>
          </thead>
          <tbody>
            {data.communications.map((co) => (
              <tr key={co.id}>
                <td className="mono">{fmtTs(co.occurred_at)}</td>
                <td>{co.direction === "outbound" ? "→" : "←"}</td>
                <td>{co.channel}</td>
                <td>{co.claimant_name ?? "—"}</td>
                <td className="sub">{co.staff_email?.split("@")[0] ?? "—"}</td>
                <td style={{ whiteSpace: "normal" }}>{co.summary ?? "—"}</td>
                <td className="sub">{co.outcome ?? "—"}</td>
              </tr>
            ))}
            {data.communications.length === 0 && (
              <tr>
                <td colSpan={7} className="faint">
                  No contact recorded.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}

/* ------------------------------------------------------------------ */

function AgreementTab({
  data,
  claimId,
  user,
  reload,
}: {
  data: CaseData;
  claimId: string;
  user: SessionUser;
  reload: () => void;
}) {
  const canManage = user.role === "admin" || user.role === "case_manager";
  const [feePercent, setFeePercent] = useState("");
  const [executeNow, setExecuteNow] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [voiding, setVoiding] = useState<string | null>(null);
  const cap = data.claim.fee_cap_percent;
  const surplus = Number(data.claim.surplus_amount ?? 0);

  const create = async () => {
    setErr(null);
    const pct = Number(feePercent);
    try {
      await api(`/api/claims/${claimId}/agreements`, {
        method: "POST",
        body: {
          fee_percent: pct,
          fee_amount: surplus ? Math.round(surplus * pct) / 100 : null,
          executed_at: executeNow ? new Date().toISOString() : null,
        },
      });
      setFeePercent("");
      reload();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "failed");
    }
  };

  return (
    <>
      <div className="card">
        <div className="cardhead">Jurisdiction limits</div>
        <div className="cardbody">
          <dl className="kv">
            <dt>County fee cap</dt>
            <dd>
              {cap == null ? (
                <span style={{ color: "var(--amber)", fontWeight: 600 }}>
                  UNRESEARCHED — the database refuses any agreement here
                </span>
              ) : (
                <span className="mono">{Number(cap)}%</span>
              )}
            </dd>
            <dt>Surplus status</dt>
            <dd>
              <Surplus amount={data.claim.surplus_amount} verified={data.claim.surplus_verified} />
              {!data.claim.surplus_verified && (
                <span className="sub"> · execution refused until verified</span>
              )}
            </dd>
          </dl>
        </div>
      </div>

      {canManage && (
        <div className="card">
          <div className="cardhead">Generate agreement</div>
          <div className="cardbody">
            <div className="formrow">
              <div className="field">
                <label>Fee percent</label>
                <input
                  className="mono"
                  style={{ width: 90 }}
                  inputMode="decimal"
                  value={feePercent}
                  onChange={(e) => setFeePercent(e.target.value)}
                />
              </div>
              <label style={{ display: "flex", alignItems: "center", gap: 5 }}>
                <input
                  type="checkbox"
                  checked={executeNow}
                  onChange={(e) => setExecuteNow(e.target.checked)}
                />
                mark executed now
              </label>
              <button className="primary" disabled={!feePercent} onClick={create}>
                Generate
              </button>
            </div>
            {err && <div className="error">{err}</div>}
            <div className="sub" style={{ marginTop: 6 }}>
              Caps and execution order are enforced by the database, not this form.
            </div>
          </div>
        </div>
      )}

      <div className="card tablewrap">
        <div className="cardhead">Agreements</div>
        <table>
          <thead>
            <tr>
              <th>Generated</th>
              <th className="num">Fee %</th>
              <th className="num">Fee amount</th>
              <th>Executed</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.agreements.map((ag) => {
              const live = !ag.void_reason && !ag.superseded_by_agreement_id;
              return (
                <tr key={ag.id}>
                  <td className="mono">{fmtTs(ag.generated_at)}</td>
                  <td className="num mono">{Number(ag.fee_percent).toFixed(2)}</td>
                  <td className="num mono">
                    {ag.fee_amount != null
                      ? Number(ag.fee_amount).toLocaleString("en-US", { minimumFractionDigits: 2 })
                      : "—"}
                  </td>
                  <td className="mono">{ag.executed_at ? fmtTs(ag.executed_at) : "—"}</td>
                  <td>
                    {ag.void_reason ? (
                      <>
                        <Pill>void</Pill> <span className="sub">{ag.void_reason}</span>
                      </>
                    ) : ag.superseded_by_agreement_id ? (
                      <Pill>superseded</Pill>
                    ) : (
                      <Pill tone="green">live</Pill>
                    )}
                  </td>
                  <td>
                    {canManage && live && (
                      <button className="danger" onClick={() => setVoiding(ag.id)}>
                        Void…
                      </button>
                    )}
                  </td>
                </tr>
              );
            })}
            {data.agreements.length === 0 && (
              <tr>
                <td colSpan={6} className="faint">
                  No agreements generated.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      {voiding && (
        <ReasonModal
          title="Void agreement"
          hint="State why this agreement is being voided."
          onSubmit={async (reason) => {
            await api(`/api/agreements/${voiding}/void`, {
              method: "POST",
              body: { reason },
            });
            reload();
          }}
          onClose={() => setVoiding(null)}
        />
      )}
    </>
  );
}

/* ------------------------------------------------------------------ */

function AuditTab({ data }: { data: CaseData }) {
  return (
    <div className="card tablewrap">
      <div className="cardhead">Audit trail for this file (read-only)</div>
      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Field</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
          {data.audit.map((al) => (
            <tr key={al.id}>
              <td className="mono">{fmtTs(al.occurred_at)}</td>
              <td className="sub">{al.user_email ?? "—"}</td>
              <td>{al.action}</td>
              <td className="mono sub">
                {al.entity_type}:{String(al.entity_id ?? "").slice(0, 8)}
              </td>
              <td>{al.field_name ?? "—"}</td>
              <td style={{ whiteSpace: "normal" }}>{al.reason ?? "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
