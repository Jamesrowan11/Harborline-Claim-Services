# Harborline Claims Workstation

Internal case-management system for Harborline Claim Services staff:
database foundation, compliance test suite, and the application itself
(Express + TypeScript API, React + Vite workstation UI).

## What is in this package

```
CLAUDE.md                      Persistent project spec — Claude Code reads this automatically
db/migrations/001_foundation   users, roles, counties (the rules table)
db/migrations/002_claims       claims, claimants, documents, communications
db/migrations/003_agreements   fee agreements + fee cap and execution-order triggers
db/migrations/004_audit        append-only audit log
db/test_compliance_rules.py    21 tests proving the rules block what they should
reference/workstation-mockup   Visual target for the UI
.env.example                   Copy to .env and fill in
db/migrations/005_sessions     server-side session store (15-minute idle timeout)
src/server                     Express + TypeScript API
src/client                     React + Vite workstation UI
```

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
`PII_ENCRYPTION_KEY` must be 32 bytes of base64 (`openssl rand -base64 32`).

## Running the application

```bash
npm install
npm run migrate        # applies db/migrations in order (or use psql as above)
npm run seed           # SYNTHETIC demo data only — prints demo logins + TOTP secrets
npm run build          # compiles server and client
npm start              # serves API + UI on PORT (default 3000)
```

For development with hot reload, run `npm run dev:server` and
`npm run dev:client` in two terminals; the Vite dev server proxies `/api`
to the Express server.

Log in with a seeded demo account (the seed prints email, password, and the
base32 TOTP secret — load the secret into any authenticator app). TOTP is
mandatory for every account. Sessions end after 15 minutes idle.

Screens: **My Queue** (deadline-sorted worklist), **Case view** (Overview,
Claimant, Documents, Chain of title, Communications, Agreement, Audit),
**County Rules** (admin-only CRUD), **Audit Log** (filterable, exports
require a stated reason).

## Verifying the compliance rules

```powershell
pip install psycopg2-binary
python db\test_compliance_rules.py
```

Expected: `21 passed, 0 failed`.

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
- Invoicing a payment without an executed, live fee agreement
- Payments that in total would exceed the agreed fee amount

## Working on this codebase

Read CLAUDE.md first — it holds the non-negotiable compliance rules, the
visual system, and the working agreement. Run the compliance suite after
any migration change, and keep audit hooks in the same commit as the
tables they cover.

## Before real claimant data enters this system

`ssn_encrypted` and `dob_encrypted` hold AES-256-GCM ciphertext produced by
the application (`src/server/src/crypto.ts`); the key never leaves the
environment. Do not load real PII until the server is hardened and a
licensed attorney has reviewed the operating model for every state you work in.

Seed and test data must remain obviously synthetic.
