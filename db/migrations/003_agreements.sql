-- 003_agreements.sql
-- Harborline Claims Workstation — fee agreements.
--
-- Compliance rules enforced here, at the database, regardless of application
-- behavior:
--   * fee_percent must not exceed the county's fee_cap_percent.
--   * A county whose cap is NULL (not yet researched) accepts NO fee at all —
--     unknown never behaves like unlimited.
--   * An agreement cannot be executed before the claim's surplus is verified
--     with the funds holder.
--   * executed_at cannot be earlier than surplus_verified_date.
--   * A claim can have at most one live agreement (not superseded, not void).

BEGIN;

CREATE TABLE agreements (
  id                          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  claim_id                    uuid NOT NULL REFERENCES claims(id),
  fee_percent                 numeric(5,2) NOT NULL
                              CHECK (fee_percent >= 0 AND fee_percent <= 100),
  fee_amount                  numeric(14,2) CHECK (fee_amount >= 0),
  generated_at                timestamptz NOT NULL DEFAULT now(),
  executed_at                 timestamptz,
  superseded_by_agreement_id  uuid REFERENCES agreements(id),
  void_reason                 text
);

-- One live agreement per claim. Live = not superseded and not voided.
CREATE UNIQUE INDEX agreements_one_live_per_claim_idx
  ON agreements (claim_id)
  WHERE superseded_by_agreement_id IS NULL AND void_reason IS NULL;

-- ---------------------------------------------------------------------------
-- Fee cap: checked against county_rules on every insert and update, so a
-- cap can never be bypassed by editing an existing row. The cap comes from
-- the counties table, never from the application.
-- ---------------------------------------------------------------------------
CREATE FUNCTION enforce_fee_cap() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  cap numeric(5,2);
  county text;
BEGIN
  SELECT c.fee_cap_percent, c.county_name || ', ' || c.state
    INTO cap, county
    FROM claims cl
    JOIN counties c ON c.id = cl.county_id
   WHERE cl.id = NEW.claim_id;

  IF cap IS NULL THEN
    RAISE EXCEPTION
      'fee cap for % has not been researched; no fee agreement may be created (unknown cap is not an unlimited cap)',
      county
      USING ERRCODE = 'check_violation';
  END IF;

  IF NEW.fee_percent > cap THEN
    RAISE EXCEPTION
      'fee_percent % exceeds the statutory cap of % percent for %',
      NEW.fee_percent, cap, county
      USING ERRCODE = 'check_violation';
  END IF;

  RETURN NEW;
END;
$$;

CREATE TRIGGER agreements_fee_cap
  BEFORE INSERT OR UPDATE ON agreements
  FOR EACH ROW EXECUTE FUNCTION enforce_fee_cap();

-- ---------------------------------------------------------------------------
-- Execution order: no agreement is executed until the surplus is verified,
-- and the execution date can never precede the verification date. The
-- business publicly commits to never asserting funds exist before
-- verification; this is where that commitment is enforced.
-- ---------------------------------------------------------------------------
CREATE FUNCTION enforce_execution_order() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  verified boolean;
  verified_date date;
BEGIN
  IF NEW.executed_at IS NULL THEN
    RETURN NEW;
  END IF;

  SELECT cl.surplus_verified, cl.surplus_verified_date
    INTO verified, verified_date
    FROM claims cl
   WHERE cl.id = NEW.claim_id;

  IF NOT verified THEN
    RAISE EXCEPTION
      'agreement cannot be executed: surplus on claim % has not been verified with the funds holder',
      NEW.claim_id
      USING ERRCODE = 'check_violation';
  END IF;

  IF NEW.executed_at::date < verified_date THEN
    RAISE EXCEPTION
      'agreement execution date % precedes the surplus verification date %',
      NEW.executed_at::date, verified_date
      USING ERRCODE = 'check_violation';
  END IF;

  RETURN NEW;
END;
$$;

CREATE TRIGGER agreements_execution_order
  BEFORE INSERT OR UPDATE ON agreements
  FOR EACH ROW EXECUTE FUNCTION enforce_execution_order();

GRANT SELECT, INSERT, UPDATE ON agreements TO harborline_app;

COMMIT;
