-- 006_payments.sql
-- Harborline Claims Workstation — fee payments (Stripe-backed invoicing).
--
-- Compliance rules enforced here, at the database, regardless of application
-- behavior:
--   * A payment must reference an EXECUTED, live agreement on the same
--     claim. No executed agreement, no invoice — money is never collected
--     ahead of a signed, compliant fee agreement.
--   * When the agreement records a fee_amount, the sum of its payments
--     (excluding failed/canceled) can never exceed that amount.
--   * Payment amounts are positive.
--
-- Stripe identifiers are opaque references; no card data ever touches this
-- database.

BEGIN;

CREATE TABLE payments (
  id                          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  claim_id                    uuid NOT NULL REFERENCES claims(id),
  agreement_id                uuid NOT NULL REFERENCES agreements(id),
  amount                      numeric(14,2) NOT NULL CHECK (amount > 0),
  currency                    text NOT NULL DEFAULT 'usd',
  status                      text NOT NULL DEFAULT 'pending'
                              CHECK (status IN
                                ('pending', 'paid', 'failed', 'canceled', 'refunded')),
  stripe_checkout_session_id  text UNIQUE,
  stripe_payment_intent_id    text,
  checkout_url                text,
  created_by                  uuid REFERENCES users(id),
  created_at                  timestamptz NOT NULL DEFAULT now(),
  paid_at                     timestamptz
);

CREATE INDEX payments_claim_id_idx ON payments (claim_id);
CREATE INDEX payments_agreement_id_idx ON payments (agreement_id);

CREATE FUNCTION enforce_payment_rules() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
  ag RECORD;
  already numeric(14,2);
BEGIN
  SELECT a.claim_id, a.executed_at, a.void_reason,
         a.superseded_by_agreement_id, a.fee_amount
    INTO ag
    FROM agreements a
   WHERE a.id = NEW.agreement_id;

  IF ag IS NULL OR ag.claim_id <> NEW.claim_id THEN
    RAISE EXCEPTION 'payment must reference an agreement on the same claim'
      USING ERRCODE = 'check_violation';
  END IF;

  IF ag.executed_at IS NULL THEN
    RAISE EXCEPTION
      'payment refused: the fee agreement has not been executed'
      USING ERRCODE = 'check_violation';
  END IF;

  IF ag.void_reason IS NOT NULL OR ag.superseded_by_agreement_id IS NOT NULL THEN
    RAISE EXCEPTION
      'payment refused: the fee agreement is void or superseded'
      USING ERRCODE = 'check_violation';
  END IF;

  IF ag.fee_amount IS NOT NULL THEN
    SELECT COALESCE(sum(p.amount), 0)
      INTO already
      FROM payments p
     WHERE p.agreement_id = NEW.agreement_id
       AND p.id <> NEW.id
       AND p.status NOT IN ('failed', 'canceled', 'refunded');
    IF already + NEW.amount > ag.fee_amount THEN
      RAISE EXCEPTION
        'payment refused: % would exceed the agreed fee of % (already invoiced %)',
        NEW.amount, ag.fee_amount, already
        USING ERRCODE = 'check_violation';
    END IF;
  END IF;

  RETURN NEW;
END;
$$;

CREATE TRIGGER payments_rules
  BEFORE INSERT OR UPDATE ON payments
  FOR EACH ROW EXECUTE FUNCTION enforce_payment_rules();

GRANT SELECT, INSERT, UPDATE ON payments TO harborline_app;

COMMIT;
