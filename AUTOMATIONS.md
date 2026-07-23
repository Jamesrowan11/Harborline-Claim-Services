# Automation Engine Reference

The Automation Center (Portal → Automation Center) lets administrators build
event-driven workflows without code. An automation = **trigger** +
**conditions** + **actions**, plus a safety mode.

## Triggers

`lead.created`, `case.created`, `case.field_changed`, `case.stage_changed`,
`document.uploaded`, `document.approved`, `document.rejected`,
`agreement.signed`, `claim.filed`, `payment.entered`, `deadline.approaching`,
`task.overdue`, `case.inactive`, `source.stale`, `client.message_received`,
`email.bounced`, `sms.opt_out`, `mail.returned`, `attorney.review_requested`,
`verification.expired`, `schedule.tick` (hourly recurring).

Time-based triggers are produced by the daily scan command
(built into server.js, 06:00 daily; manual run: `node src/cli.js scans`) and the hourly
`schedule.tick`.

## Conditions

JSON array; ALL must pass (AND). `{"field": "...", "operator": "...",
"value": ...}`.

Fields: `county`, `state`, `case_type`, `sale_type`, `surplus_amount`,
`verification_level`, `assigned_employee`, `risk_level`, `legal_complexity`,
`missing_documents`, `consent_status`, `days_since_last_activity`, `stage`,
`outreach_approved`, `from_stage`/`to_stage` (on stage changes), any event
context key, and `custom.<key>` for case custom fields.

Operators: `equals`, `not_equals`, `greater_than`, `less_than`, `contains`,
`in`, `not_in`, `is_true`, `is_false`, `is_empty`, `is_not_empty`.

## Actions

JSON array executed in order. `{"type": "...", "params": {...}}`.

| Type | Key params |
|---|---|
| `create_task` | `title`, `description`, `priority`, `assignee` (email), `due_in_days` |
| `assign_employee` | `assignee` (email or name) |
| `change_stage` | `stage` (stage key) |
| `send_internal_notification` | `message`, `user` (email; defaults to assignee) |
| `send_email` / `send_sms` | `template` (template key) — passes the outreach gate |
| `generate_letter` | `template` — drafts a letter for manual review |
| `generate_pdf` / `generate_spreadsheet` | use Print & Export Center tasks (`create_task`) |
| `request_document` | `name`, `instructions`, `due_in_days` |
| `add_note` | `body` |
| `add_tag` | `tag` |
| `schedule_follow_up` | `title`, `due_in_days` |
| `escalate_to_manager` | `message` |
| `require_compliance_review` | — sets the compliance hold |
| `require_attorney_review` | — moves to the attorney-review stage |
| `lock_workflow` | — pauses automations on the case |
| `add_calendar_item` | `name`, `due_in_days` |
| `trigger_webhook` / `call_external_api` | `endpoint` (configured webhook name), `data` — HMAC-signed |
| `create_audit_record` | `message` |
| `close_duplicate` | — moves to Closed—Duplicate |
| `reverify_source` | — marks sources stale + creates re-verification task |
| `stop_outreach` | — clears outreach approval |
| `send_portal_notification` | `message` |

## Safety model (all enforced in `src/automation/engine.js`)

1. **Modes** — `draft` (never runs), `test` (logs matches, executes nothing),
   `approval` (each run is queued as `pending_approval`), `active`.
2. **Sensitive automations** — anything sending email/SMS/letters/portal
   notifications is flagged sensitive and cannot run until an administrator
   approves it; **any edit resets the approval and returns it to draft**.
3. **Outreach gate** — even an approved sensitive automation can only deliver
   if the template is approved, the recipient hasn't opted out, SMS has its
   own consent and is enabled, the case meets the minimum verification level,
   outreach is approved on the case, frequency limits are unspent, and the
   case isn't on hold. Blocked sends are logged with the reason.
4. **Idempotency** — each run has a unique key (automation + event + record +
   suffix); replays are no-ops.
5. **Rate limiting** — per-automation hourly cap and per-case daily cap.
6. **Loop prevention** — automation-initiated stage changes carry a chain
   depth; chains stop at depth 3.
7. **Per-case pause** — "Pause automations" on any case.
8. **Emergency global stop** — one button in the Automation Center (or
   `AUTOMATION_GLOBAL_STOP=true` env override) halts everything.
9. **History & versions** — every run is logged (success/failure/skip reason);
   every saved edit snapshots a version for rollback reference.
10. **Retries** — queued deliveries retry 3× with backoff; failures land in
    the run log and the jobs table with the last error recorded.

## Default automation templates

22 templates are seeded **in draft mode** (see
`src/seeds/baseline.js`) covering acknowledgment, duplicate
checks, research checklists, verification tasks, stale-source reminders,
missing-document reminders, attorney escalation, deadline/overdue alerts,
claim-filed notification, reconciliation, completion messaging, archiving,
and periodic summaries. Review each one's conditions and actions, then
activate deliberately. Legal, financial, SMS, and client-outreach automations
require explicit administrator approval by design.
