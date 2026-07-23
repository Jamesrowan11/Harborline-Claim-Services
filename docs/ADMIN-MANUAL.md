# Administrator Manual

## First sign-in
Sign in at `/login`, complete two-factor enrollment (any TOTP authenticator
app), and store your recovery codes safely. All staff accounts require MFA.

## Administration areas (left navigation → Administration)

### Settings
- **Branding** — business name, legal name, parent company, tagline, contact
  details, case-number prefix. Changing these rebrands the whole product
  (public site, portal, emails, exports).
- **Case numbers** — format string with tokens `{PREFIX} {YEAR} {STATE}
  {COUNTY} {SEQ:6}`. Existing numbers never change; new cases use the new
  format.
- **Service area** — counties/states served.
- **Assignment rules** — JSON list mapping county/state to a staff email;
  applied automatically to new leads.
- **Outreach compliance** — minimum verification level before any outreach,
  and the per-recipient weekly contact cap.

### Users & Roles
Create users with a role; they receive a password-setup email and must enroll
MFA. Roles: Super Administrator, Company Administrator, Researcher, Case
Manager, Compliance Reviewer, Attorney, Accounting User, Read-Only Auditor,
Client. Deactivate a user by setting `deactivated_at` (blocks login and live
sessions).

### Pipeline Stages
Rename stages, reorder them, change the client-facing label (the ONLY status
text clients see), or deactivate unused stages.

### Templates
Edit letter/email/SMS templates with merge fields. Every edit creates a new
version and returns the template to draft — someone with the
`templates.approve` permission must approve it before anything can be sent.
Templates flagged "ATTORNEY REVIEW REQUIRED" should also be approved by
counsel outside the system.

### Retention
Per-record-type retention: months to retain, then review / anonymize /
delete. Policies ship inactive. Records under legal hold are never touched.
`php artisan hcs:enforce-retention --dry-run` previews effects.

## Automation Center
See AUTOMATIONS.md. Key rules: new automations start in draft; use test mode
to observe matches safely; sensitive (outbound-communication) automations
need your explicit approval and re-approval after every edit; the EMERGENCY
STOP button halts everything instantly.

## Print & Export Center
Choose a report, set date filters, then Preview/Print, XLSX, CSV, or PDF.
Exports are logged (who, what, when, filters, row count) in the audit trail.

## Audit Log
Filterable, append-only record of every significant action. Export it via
the Audit Report in the Print & Export Center.
