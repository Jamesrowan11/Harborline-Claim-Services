-- 004_audit.sql
-- Harborline Claims Workstation — append-only audit log.
--
-- Compliance rules enforced here, at the database, regardless of application
-- behavior:
--   * Audit rows can be inserted and read. UPDATE, DELETE, and TRUNCATE are
--     refused by triggers, which fire for every role including superusers.
--   * Revealing PII or running an export requires a stated reason of at
--     least 8 characters. The reason lands in the audit row or the action
--     is refused.
--
-- Reads are audited, not just writes: viewing a claim file, revealing a
-- masked field, and running an export all produce rows here.

BEGIN;

CREATE TABLE audit_log (
  id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  occurred_at  timestamptz NOT NULL DEFAULT now(),
  user_id      uuid REFERENCES users(id),
  action       text NOT NULL,
  entity_type  text NOT NULL,
  entity_id    text,
  field_name   text,
  reason       text,
  ip_address   inet,

  -- Sensitive actions carry a real reason: 8+ characters after trimming.
  CONSTRAINT sensitive_actions_need_reason CHECK (
    action NOT IN ('pii_reveal', 'export')
    OR length(btrim(coalesce(reason, ''))) >= 8
  )
);

CREATE INDEX audit_log_entity_idx      ON audit_log (entity_type, entity_id);
CREATE INDEX audit_log_occurred_at_idx ON audit_log (occurred_at);

-- ---------------------------------------------------------------------------
-- Append-only enforcement. Privilege revocation alone is not enough (table
-- owners and superusers bypass grants), so triggers refuse the statements
-- outright.
-- ---------------------------------------------------------------------------
CREATE FUNCTION audit_log_is_append_only() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  RAISE EXCEPTION 'audit_log is append-only: % is not permitted', TG_OP
    USING ERRCODE = 'insufficient_privilege';
END;
$$;

CREATE TRIGGER audit_log_no_update
  BEFORE UPDATE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION audit_log_is_append_only();

CREATE TRIGGER audit_log_no_delete
  BEFORE DELETE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION audit_log_is_append_only();

CREATE TRIGGER audit_log_no_truncate
  BEFORE TRUNCATE ON audit_log
  FOR EACH STATEMENT EXECUTE FUNCTION audit_log_is_append_only();

-- Belt and braces: the application role never receives the privileges the
-- triggers refuse.
GRANT SELECT, INSERT ON audit_log TO harborline_app;

COMMIT;
