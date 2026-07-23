# Compliance Placeholders — Attorney Review Required

This application deliberately ships with **placeholders, drafts, and disabled
features** wherever the correct answer depends on law, jurisdiction, or
professional judgment. Nothing in the codebase invents laws, fee caps,
deadlines, forms, or filing rights.

## Naming (before launch)

"Harborline Claim Services" is a **provisional working name**. Before public
use, the owner and attorney must confirm:

- [ ] Maryland entity-name availability
- [ ] Federal and state trademark clearance
- [ ] Domain availability
- [ ] Social-media handle availability
- [ ] Whether licensing/registration rules restrict "claim", "recovery",
      "funds", or similar language in the chosen state(s)

All branding is editable in `config/branding.php` / `.env` / Administration →
Settings without a rebuild.

## Items requiring attorney review before use

| Area | Where in the app | Status |
|---|---|---|
| Fee models (percentage/flat, caps) | `agreements` table, Settings fee fields | Placeholder — no fee text shipped |
| Client agreements | `Agreement` model, agreement templates | Demo text only; real text must be drafted by counsel |
| Assignments / powers of attorney | agreement workflow | Not implemented pending counsel |
| Court filings | `Claim` model notes | App records filings; it never generates court forms |
| Probate workflows | `estates` table, `legal_complexity=probate` | Flags for attorney routing only |
| Heirship workflows | `heirs` table | Flags for attorney routing only |
| Minor claimants | intake screening | Route to attorney review |
| Deceased owners | `is_deceased`, estates | Route to attorney review |
| Bankruptcy | `legal_complexity=bankruptcy` | Route to attorney review |
| Multiple claimants / share splits | `case_claimant.share_percent` | Recorded, never auto-calculated |
| Business ownership claims | `businesses` table | Route to attorney review |
| Trust ownership claims | `trusts` table | Route to attorney review |
| Disputed claims | `legal_complexity=disputed` | Route to attorney review |
| SMS outreach (TCPA etc.) | `SMS_ENABLED=false` by default; SMS Terms page | Disabled until counsel approves |
| Automated calling | Not implemented | Deliberately absent |
| Direct mail campaigns | letter templates, draft-only | Templates require approval |
| Identity-verification standard | `identity_verification_status` | Process to be defined with counsel |
| Funds handling / trust accounting | `payments` table | Records transactions only; no client-money handling opinion |
| Client-money distribution | `client_distribution` payments | Same as above |
| Referral-fee arrangements | Referral partners, referrals page | Written arrangements via counsel |
| Privacy Policy / Terms text | `resources/views/site/legal/*` | Working drafts with visible review banners |
| Retention periods | Administration → Retention | Set with counsel; delete actions ship inactive |

## Guardrails built into the software (do not remove)

- The independence disclaimer ("not a government agency, court, trustee,
  county office, or law firm… recovery is not guaranteed") appears near the
  first call to action, in the site footer, in client emails, and on the
  client portal.
- Client-facing language uses "possible funds" until holder verification;
  no screen or template states funds definitely exist.
- No countdown timers, fake testimonials, fabricated amounts, or urgency
  mechanics exist anywhere in the UI.
- Public forms never collect SSNs, bank credentials, card details, or full
  government ID.
- Outbound communication is impossible without an approved template, consent,
  and a compliance-approved case (see AUTOMATIONS.md safety model).
- Stages flagged `requires_attorney_review` surface a visible badge in the
  portal, and the `attorney_review_required` stage exists precisely so legal
  steps are never skipped.
