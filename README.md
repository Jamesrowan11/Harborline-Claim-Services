# Harborline Claims Workstation — starter package

Database foundation and design reference for the internal claims system.
Everything here has been applied against a real PostgreSQL instance and the
compliance rules are covered by a passing test suite.

## What is in this package

```
CLAUDE.md                      Persistent project spec — Claude Code reads this automatically
db/migrations/001_foundation   users, roles, counties (the rules table)
db/migrations/002_claims       claims, claimants, documents, communications
db/migrations/003_agreements   fee agreements + fee cap and execution-order triggers
db/migrations/004_audit        append-only audit log
db/test_compliance_rules.py    18 tests proving the rules block what they should
reference/workstation-mockup   Visual target for the UI
.env.example                   Copy to .env and fill in
```

Empty `src/server` and `src/client` folders are where the application goes.
That part is not built yet — Claude Code takes it from here.

## Requirements

PostgreSQL 13 or later. `gen_random_uuid()` is used from core, so no
extensions are needed.

## Setup

```powershell
createdb harborline

psql -d harborline -f db/migrations/001_foundation.sql
psql -d harborline -f db/migrations/002_claims.sql
psql -d harborline -f db/migrations/003_agreements.sql
psql -d harborline -f db/migrations/004_audit.sql
```

Then change the application role password, which ships as a placeholder:

```sql
ALTER ROLE harborline_app PASSWORD 'a_real_password_here';
```

Copy `.env.example` to `.env` and fill in the values. `.env` is gitignored.

## Verifying the compliance rules

```powershell
pip install psycopg2-binary
python db\test_compliance_rules.py
```

Expected: `18 passed, 0 failed`.

Run this after any migration change. If a test starts failing, a compliance
guarantee has been removed — find out why before continuing.

## What the rules actually enforce

The database refuses, regardless of what the application code does:

- A fee above the county's statutory cap
- Any fee at all in a county whose cap has not been researched
  (unknown never behaves like unlimited)
- Executing a fee agreement before the surplus is verified with the funds holder
- Executing an agreement dated earlier than the verification date
- More than one live agreement on a claim
- Marking a surplus verified without recording the date and source
- Recording SMS consent without a consent date
- Revealing PII or exporting data without a stated reason of 8+ characters
- Updating, deleting, or truncating any audit row

## Handing off to Claude Code

From the project folder, run `claude`, then:

```
Read CLAUDE.md, README.md, and every file in db/migrations.
Then read reference/workstation-mockup.html.
Don't write code yet — summarize what exists and what's missing.
```

Then work through the screen list in CLAUDE.md in order, committing after each.

## Before real claimant data enters this system

The schema assumes `ssn_encrypted` and `dob_encrypted` hold ciphertext
produced by the application, not plaintext. That encryption layer is not
built yet. Do not load real PII until it is, the server is hardened, and a
licensed attorney has reviewed the operating model for every state you work in.

Seed and test data must remain obviously synthetic.
