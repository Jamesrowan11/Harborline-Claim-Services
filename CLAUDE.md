# Harborline Claims Workstation

Internal case-management system for Harborline Claim Services, a surplus funds
recovery company. This is a staff-facing operations tool, not a public website.

The public marketing site (harborlineclaimservices.com) already exists and is
separate from this project. Do not modify or replicate it.

---

## Product intent

Staff open this tool at the start of a shift and work a queue of claim files.
Every screen should answer "what do I need to touch right now" before it answers
anything else.

Visual and interaction model is a financial-institution internal system:
dense, disciplined, keyboard-friendly, low decoration. Information density is a
feature, not a flaw. Do not add marketing polish, hero sections, gradients,
illustrations, or animated flourishes.

---

## Non-negotiable rules

These are compliance and safety requirements, not preferences. Do not remove,
weaken, or work around them. If a requested feature conflicts with one of these,
stop and say so instead of implementing it.

1. **PII is masked by default.** SSN, date of birth, and full account numbers
   render masked. Revealing one requires an explicit click that writes an audit
   record containing user, timestamp, record, field, and a reason string.

2. **Reads are audited, not just writes.** Viewing a claim file, revealing a
   masked field, and running an export all produce audit rows. Audit rows are
   append-only — no update or delete path may exist in code.

3. **Verified and unverified surplus amounts are visually distinct everywhere.**
   An unverified amount is any figure not yet confirmed with the funds holder.
   It must never render identically to a verified amount, in any view, export,
   or document. The business publicly commits to never asserting funds exist
   before verification; the system enforces that.

4. **County rules gate actions.** Fee percentages, filing deadlines, and contact
   blackout periods come from the county_rules table, never hardcoded and never
   free-typed per claim. A fee above the jurisdiction's cap is rejected at the
   database level, not just the UI.

5. **PII never enters logs.** No claimant names, SSNs, DOBs, or addresses in
   application logs, console output, error messages, or exception traces. Log
   record IDs instead.

6. **No sample or seed data resembling real people.** Seed data must be
   obviously synthetic and labeled as such in the UI.

7. **Contact-preference checks are enforced in code.** If a claimant has not
   consented to SMS, the system must make sending SMS impossible, not merely
   discouraged by a UI warning.

---

## Stack

- Database: PostgreSQL
- Backend: Node + Express + TypeScript
- Frontend: React + Vite + TypeScript
- Auth: session-based with bcrypt, TOTP two-factor required for all accounts
- No ORM magic — use explicit SQL via a query builder so audit behavior is visible

---

## Data model (core tables)

**counties** — jurisdiction reference
  state, county_name, funds_holder_name, holder_contact,
  fee_cap_percent, filing_deadline_rule, contact_blackout_days,
  licensing_required (bool), notes, last_verified_date

**claims** — one per surplus fund file
  ref (HCS-YYYY-NNNN), county_id, parcel_number, property_address,
  sale_type, sale_date, sale_amount, debt_satisfied,
  surplus_amount, surplus_verified (bool), surplus_verified_date,
  surplus_verified_source, filing_deadline, stage, assigned_to,
  created_at, closed_at, outcome

**claimants** — people who may have a claim
  legal_name, relationship_to_owner, dob_encrypted, ssn_encrypted,
  mailing_address, phone, email, preferred_contact_method,
  sms_consent (bool), sms_consent_date, email_consent (bool),
  do_not_contact (bool), language

**claim_claimants** — join table; a claim can have multiple heirs
  claim_id, claimant_id, share_percent, is_primary

**documents** — uploaded files
  claim_id, doc_type, filename, storage_key, sha256,
  uploaded_by, uploaded_at, received_from, virus_scan_status

**required_documents** — checklist per claim, driven by county + claim type
  claim_id, doc_type, required (bool), satisfied_by_document_id,
  requested_date, last_followup_date

**communications** — every contact attempt
  claim_id, claimant_id, direction, channel, occurred_at, staff_user_id,
  summary, outcome

**agreements** — fee agreements
  claim_id, fee_percent, fee_amount, generated_at, executed_at,
  superseded_by_agreement_id, void_reason
  CONSTRAINT: fee_percent must not exceed the county's fee_cap_percent
  CONSTRAINT: executed_at must be after the claim's surplus_verified_date

**audit_log** — append-only
  occurred_at, user_id, action, entity_type, entity_id,
  field_name, reason, ip_address
  No UPDATE or DELETE permitted. Enforce with a database rule or trigger.

**users**
  email, password_hash, totp_secret, role, active, last_login_at
  Roles: admin, case_manager, researcher, read_only

---

## Screens (build in this order)

1. **Login** — email + password + TOTP. Session timeout 15 minutes idle.
2. **My Queue** — the worklist. Sorted by days-until-filing-deadline ascending.
   Columns: ref, claimant, county, stage, surplus, filing window, owner.
   The filing window column renders a progress track colored by urgency:
   green >60 days, amber 31-60, red <=30. This is the most important
   element on the screen.
3. **Case view** — one claim, tabbed: Overview, Claimant, Documents,
   Chain of title, Communications, Agreement, Audit.
   Overview shows a blocking-issues banner and a filing-readiness checklist.
4. **County rules admin** — CRUD for the counties table, admin role only.
5. **Audit log viewer** — filterable, read-only, no export without a reason string.

Later, not now: client portal, letter generation, skip-trace integration,
county monitoring, reporting dashboards.

---

## Visual system

Colors (use exactly these; do not introduce new hues):
  navy-900  #0B1F3A   top bar
  navy-700  #163254   nav rail
  work      #F5F6F8   page background
  surface   #FFFFFF   cards, tables
  rule      #D8DDE4   borders
  ink       #12161C   primary text
  ink-2     #4A5361   secondary text
  ink-3     #7A8493   tertiary text
  link      #1F5FA8   interactive
  amber     #B26A00   warning
  red       #B3261E   critical
  green     #1B6B4A   success

Type:
  Archivo for UI text
  IBM Plex Mono for all IDs, reference numbers, dates, and money
  Money and dates must use tabular figures so columns align.

Base font size 13px. Table rows compact. Cards use 4px radius, 1px borders,
no shadows.

---

## Working agreement

- Ask before adding any dependency not already in package.json.
- Write the migration before the code that uses it.
- Every table that touches claimant data gets its audit hooks written in the
  same commit as the table, not later.
- Commit after each working screen. Small commits.
- If a request would break a Non-negotiable rule above, say so and stop.
