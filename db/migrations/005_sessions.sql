-- 005_sessions.sql
-- Harborline Claims Workstation — server-side session store.
--
-- Sessions are held in the database (connect-pg-simple layout) so an idle
-- timeout is enforceable server-side and restarting the app logs nobody in
-- or out unexpectedly. Session rows contain a user id and expiry only —
-- never claimant data.

BEGIN;

CREATE TABLE session (
  sid     varchar PRIMARY KEY,
  sess    json NOT NULL,
  expire  timestamptz NOT NULL
);

CREATE INDEX session_expire_idx ON session (expire);

-- The app must create, refresh, and destroy sessions.
GRANT SELECT, INSERT, UPDATE, DELETE ON session TO harborline_app;

-- County rules admin is full CRUD (admin role only, enforced in the app);
-- deletes of counties still in use are refused by the claims FK.
GRANT DELETE ON counties TO harborline_app;

COMMIT;
