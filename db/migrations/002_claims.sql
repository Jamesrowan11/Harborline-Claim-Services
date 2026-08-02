-- 002_claims.sql
-- Harborline Claims Workstation — claims, claimants, documents, communications.
--
-- Compliance rules enforced here, at the database, regardless of application
-- behavior:
--   * A surplus cannot be marked verified without recording both the
--     verification date and the source (CHECK on claims).
--   * SMS consent cannot be recorded without a consent date (CHECK on
--     claimants).
--
-- ssn_encrypted / dob_encrypted hold ciphertext produced by the application
-- encryption layer. They must never contain plaintext.

BEGIN;

-- ---------------------------------------------------------------------------
-- claims — one row per surplus fund file
-- ---------------------------------------------------------------------------
CREATE TABLE claims (
  id                      uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  ref                     text NOT NULL UNIQUE
                          CHECK (ref ~ '^HCS-[0-9]{4}-[0-9]{4}$'),
  county_id               uuid NOT NULL REFERENCES counties(id),
  parcel_number           text,
  property_address        text,
  sale_type               text,
  sale_date               date,
  sale_amount             numeric(14,2) CHECK (sale_amount >= 0),
  debt_satisfied          numeric(14,2) CHECK (debt_satisfied >= 0),
  surplus_amount          numeric(14,2) CHECK (surplus_amount >= 0),
  surplus_verified        boolean NOT NULL DEFAULT false,
  surplus_verified_date   date,
  surplus_verified_source text,
  filing_deadline         date,
  stage                   text NOT NULL DEFAULT 'intake',
  assigned_to             uuid REFERENCES users(id),
  created_at              timestamptz NOT NULL DEFAULT now(),
  closed_at               timestamptz,
  outcome                 text,

  -- A verified surplus must carry the date it was verified and the source
  -- (who at the funds holder confirmed it). Unverified rows may not carry
  -- either, so a stale date can never make an unverified figure look
  -- confirmed.
  CONSTRAINT surplus_verification_complete CHECK (
    (surplus_verified
      AND surplus_verified_date IS NOT NULL
      AND surplus_verified_source IS NOT NULL
      AND btrim(surplus_verified_source) <> '')
    OR
    (NOT surplus_verified
      AND surplus_verified_date IS NULL
      AND surplus_verified_source IS NULL)
  )
);

CREATE INDEX claims_filing_deadline_idx ON claims (filing_deadline);
CREATE INDEX claims_assigned_to_idx     ON claims (assigned_to);

-- ---------------------------------------------------------------------------
-- claimants — people who may have a claim. PII columns hold ciphertext.
-- ---------------------------------------------------------------------------
CREATE TABLE claimants (
  id                        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  legal_name                text NOT NULL,
  relationship_to_owner     text,
  dob_encrypted             text,
  ssn_encrypted             text,
  mailing_address           text,
  phone                     text,
  email                     text,
  preferred_contact_method  text
                            CHECK (preferred_contact_method IN
                                   ('mail', 'phone', 'sms', 'email')),
  sms_consent               boolean NOT NULL DEFAULT false,
  sms_consent_date          date,
  email_consent             boolean NOT NULL DEFAULT false,
  do_not_contact            boolean NOT NULL DEFAULT false,
  language                  text NOT NULL DEFAULT 'en',

  -- Consent without a record of when it was given is not consent.
  CONSTRAINT sms_consent_dated CHECK (
    NOT sms_consent OR sms_consent_date IS NOT NULL
  )
);

-- ---------------------------------------------------------------------------
-- claim_claimants — a claim can have multiple heirs
-- ---------------------------------------------------------------------------
CREATE TABLE claim_claimants (
  claim_id       uuid NOT NULL REFERENCES claims(id),
  claimant_id    uuid NOT NULL REFERENCES claimants(id),
  share_percent  numeric(5,2)
                 CHECK (share_percent > 0 AND share_percent <= 100),
  is_primary     boolean NOT NULL DEFAULT false,
  PRIMARY KEY (claim_id, claimant_id)
);

-- At most one primary claimant per claim.
CREATE UNIQUE INDEX claim_claimants_one_primary_idx
  ON claim_claimants (claim_id) WHERE is_primary;

-- ---------------------------------------------------------------------------
-- documents — uploaded files (content lives in object storage, not the DB)
-- ---------------------------------------------------------------------------
CREATE TABLE documents (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  claim_id           uuid NOT NULL REFERENCES claims(id),
  doc_type           text NOT NULL,
  filename           text NOT NULL,
  storage_key        text NOT NULL UNIQUE,
  sha256             text NOT NULL CHECK (sha256 ~ '^[0-9a-f]{64}$'),
  uploaded_by        uuid REFERENCES users(id),
  uploaded_at        timestamptz NOT NULL DEFAULT now(),
  received_from      text,
  virus_scan_status  text NOT NULL DEFAULT 'pending'
                     CHECK (virus_scan_status IN
                            ('pending', 'clean', 'infected', 'failed'))
);

CREATE INDEX documents_claim_id_idx ON documents (claim_id);

-- ---------------------------------------------------------------------------
-- required_documents — per-claim checklist, driven by county + claim type
-- ---------------------------------------------------------------------------
CREATE TABLE required_documents (
  id                         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  claim_id                   uuid NOT NULL REFERENCES claims(id),
  doc_type                   text NOT NULL,
  required                   boolean NOT NULL DEFAULT true,
  satisfied_by_document_id   uuid REFERENCES documents(id),
  requested_date             date,
  last_followup_date         date,
  UNIQUE (claim_id, doc_type)
);

-- ---------------------------------------------------------------------------
-- communications — every contact attempt, inbound and outbound
-- ---------------------------------------------------------------------------
CREATE TABLE communications (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  claim_id       uuid NOT NULL REFERENCES claims(id),
  claimant_id    uuid REFERENCES claimants(id),
  direction      text NOT NULL CHECK (direction IN ('inbound', 'outbound')),
  channel        text NOT NULL
                 CHECK (channel IN ('mail', 'phone', 'sms', 'email', 'in_person')),
  occurred_at    timestamptz NOT NULL DEFAULT now(),
  staff_user_id  uuid REFERENCES users(id),
  summary        text,
  outcome        text
);

CREATE INDEX communications_claim_id_idx ON communications (claim_id);

GRANT SELECT, INSERT, UPDATE ON claims, claimants, claim_claimants,
                               documents, required_documents, communications
  TO harborline_app;

COMMIT;
