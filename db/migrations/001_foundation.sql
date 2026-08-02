-- 001_foundation.sql
-- Harborline Claims Workstation — foundation: application role, users, counties.
--
-- Run order: 001 -> 002 -> 003 -> 004. Each file is idempotent-hostile on
-- purpose: rerunning against an already-migrated database should fail loudly
-- rather than silently diverge.

BEGIN;

-- ---------------------------------------------------------------------------
-- Application role. Ships with a placeholder password; the README requires
-- changing it before use:  ALTER ROLE harborline_app PASSWORD '...';
-- ---------------------------------------------------------------------------
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'harborline_app') THEN
    CREATE ROLE harborline_app LOGIN PASSWORD 'CHANGE_ME';
  END IF;
END
$$;

-- ---------------------------------------------------------------------------
-- users
-- Roles: admin, case_manager, researcher, read_only.
-- TOTP two-factor is required for all accounts; totp_secret is set at
-- enrollment and the application refuses login while it is NULL.
-- ---------------------------------------------------------------------------
CREATE TABLE users (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email          text NOT NULL UNIQUE,
  password_hash  text NOT NULL,
  totp_secret    text,
  role           text NOT NULL
                 CHECK (role IN ('admin', 'case_manager', 'researcher', 'read_only')),
  active         boolean NOT NULL DEFAULT true,
  last_login_at  timestamptz,
  created_at     timestamptz NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------------
-- counties — the jurisdiction rules table.
--
-- fee_cap_percent is NULL until the cap has been researched for that
-- jurisdiction. NULL means UNKNOWN, and unknown must never behave like
-- unlimited: the agreements trigger (003) rejects any fee in a county whose
-- cap is NULL.
-- ---------------------------------------------------------------------------
CREATE TABLE counties (
  id                     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  state                  text NOT NULL CHECK (state ~ '^[A-Z]{2}$'),
  county_name            text NOT NULL,
  funds_holder_name      text,
  holder_contact         text,
  fee_cap_percent        numeric(5,2)
                         CHECK (fee_cap_percent >= 0 AND fee_cap_percent <= 100),
  filing_deadline_rule   text,
  contact_blackout_days  integer CHECK (contact_blackout_days >= 0),
  licensing_required     boolean NOT NULL DEFAULT false,
  notes                  text,
  last_verified_date     date,
  UNIQUE (state, county_name)
);

GRANT SELECT, INSERT, UPDATE ON users    TO harborline_app;
GRANT SELECT, INSERT, UPDATE ON counties TO harborline_app;

COMMIT;
