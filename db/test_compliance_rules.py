#!/usr/bin/env python3
"""Compliance rule tests for the Harborline Claims Workstation database.

These tests prove the database itself refuses operations that would break a
compliance guarantee, regardless of what any application code does. Run them
after every migration change:

    pip install psycopg2-binary
    python db/test_compliance_rules.py

Expected output: 18 passed, 0 failed.

Connection comes from DATABASE_URL (falling back to a local dev default).
All fixture data is obviously synthetic and is rolled back or removed; no
test writes anything resembling a real person.
"""

import os
import sys
import uuid

import psycopg2
import psycopg2.errors

DSN = os.environ.get(
    "DATABASE_URL", "postgresql://localhost:5432/harborline"
)

PASSED = []
FAILED = []


def test(name):
    def decorator(fn):
        fn._test_name = name
        TESTS.append(fn)
        return fn
    return decorator


TESTS = []


class Fixtures:
    """Synthetic reference rows shared by the tests, committed once.

    Everything is prefixed ZZTEST so it is unmistakably fake, and removed
    again in teardown.
    """

    def __init__(self, conn):
        cur = conn.cursor()
        cur.execute(
            """
            INSERT INTO counties (state, county_name, fee_cap_percent,
                                  last_verified_date)
            VALUES ('ZZ', 'ZZTEST Capped County', 10.00, CURRENT_DATE)
            RETURNING id
            """
        )
        self.capped_county = cur.fetchone()[0]
        cur.execute(
            """
            INSERT INTO counties (state, county_name, fee_cap_percent)
            VALUES ('ZZ', 'ZZTEST Unresearched County', NULL)
            RETURNING id
            """
        )
        self.unresearched_county = cur.fetchone()[0]
        conn.commit()

    def teardown(self, conn):
        cur = conn.cursor()
        cur.execute("DELETE FROM agreements WHERE claim_id IN "
                    "(SELECT id FROM claims WHERE county_id IN %s)",
                    ((self.capped_county, self.unresearched_county),))
        cur.execute("DELETE FROM claims WHERE county_id IN %s",
                    ((self.capped_county, self.unresearched_county),))
        cur.execute("DELETE FROM counties WHERE id IN %s",
                    ((self.capped_county, self.unresearched_county),))
        conn.commit()


def _new_ref():
    return "HCS-2099-%04d" % (uuid.uuid4().int % 10000)


def make_claim(cur, county_id, verified=False):
    """Insert a synthetic claim and return its id."""
    if verified:
        cur.execute(
            """
            INSERT INTO claims (ref, county_id, surplus_amount,
                                surplus_verified, surplus_verified_date,
                                surplus_verified_source)
            VALUES (%s, %s, 50000.00, true, CURRENT_DATE - 10,
                    'ZZTEST synthetic funds holder confirmation')
            RETURNING id
            """,
            (_new_ref(), county_id),
        )
    else:
        cur.execute(
            """
            INSERT INTO claims (ref, county_id, surplus_amount)
            VALUES (%s, %s, 50000.00)
            RETURNING id
            """,
            (_new_ref(), county_id),
        )
    return cur.fetchone()[0]


def expect_rejected(conn, fn, why):
    """Run fn(cursor); pass only if the database raises an error."""
    cur = conn.cursor()
    try:
        fn(cur)
    except (psycopg2.errors.CheckViolation,
            psycopg2.errors.UniqueViolation,
            psycopg2.errors.InsufficientPrivilege,
            psycopg2.errors.NotNullViolation,
            psycopg2.errors.RaiseException):
        conn.rollback()
        return
    conn.rollback()
    raise AssertionError("database accepted it, but it must refuse: " + why)


def expect_accepted(conn, fn, why):
    """Run fn(cursor); pass only if the database accepts it. Rolls back."""
    cur = conn.cursor()
    try:
        fn(cur)
    except psycopg2.Error as exc:
        conn.rollback()
        raise AssertionError(
            "database refused a legitimate operation (%s): %s"
            % (why, exc.diag.message_primary or exc)
        )
    conn.rollback()


# ---------------------------------------------------------------------------
# Fee caps
# ---------------------------------------------------------------------------

@test("fee above the county cap is rejected")
def t01(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 10.01)",
            (claim,),
        )
    expect_rejected(conn, go, "10.01% fee in a 10% cap county")


@test("fee at the county cap is accepted")
def t02(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 10.00)",
            (claim,),
        )
    expect_accepted(conn, go, "10% fee in a 10% cap county")


@test("any fee in a county with an unresearched (NULL) cap is rejected")
def t03(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.unresearched_county, verified=True)
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 0.01)",
            (claim,),
        )
    expect_rejected(conn, go, "unknown cap must not behave like unlimited")


# ---------------------------------------------------------------------------
# Execution order
# ---------------------------------------------------------------------------

@test("executing an agreement before the surplus is verified is rejected")
def t04(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=False)
        cur.execute(
            """
            INSERT INTO agreements (claim_id, fee_percent, executed_at)
            VALUES (%s, 5.00, now())
            """,
            (claim,),
        )
    expect_rejected(conn, go, "execution before verification")


@test("agreement executed_at earlier than the verification date is rejected")
def t05(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            """
            INSERT INTO agreements (claim_id, fee_percent, executed_at)
            VALUES (%s, 5.00, now() - interval '30 days')
            """,
            (claim,),
        )
    expect_rejected(conn, go,
                    "executed 30 days ago, verified 10 days ago")


@test("agreement executed after the verification date is accepted")
def t06(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            """
            INSERT INTO agreements (claim_id, fee_percent, executed_at)
            VALUES (%s, 5.00, now())
            """,
            (claim,),
        )
    expect_accepted(conn, go, "execution after verification")


# ---------------------------------------------------------------------------
# One live agreement per claim
# ---------------------------------------------------------------------------

@test("a second live agreement on the same claim is rejected")
def t07(conn, fx):
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 5.00)",
            (claim,),
        )
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 6.00)",
            (claim,),
        )
    expect_rejected(conn, go, "two live agreements on one claim")


@test("a new agreement is accepted once the old one is dead")
def t08(conn, fx):
    # The unique index is not deferrable by design, so void-then-insert is
    # the required order.
    def go(cur):
        claim = make_claim(cur, fx.capped_county, verified=True)
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 5.00) "
            "RETURNING id",
            (claim,),
        )
        first = cur.fetchone()[0]
        cur.execute(
            "UPDATE agreements SET void_reason = 'ZZTEST superseding' "
            "WHERE id = %s",
            (first,),
        )
        cur.execute(
            "INSERT INTO agreements (claim_id, fee_percent) VALUES (%s, 6.00)",
            (claim,),
        )
    expect_accepted(conn, go,
                    "replacing a dead agreement with a new live one")


# ---------------------------------------------------------------------------
# Surplus verification completeness
# ---------------------------------------------------------------------------

@test("marking a surplus verified without a verification date is rejected")
def t09(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO claims (ref, county_id, surplus_amount,
                                surplus_verified, surplus_verified_source)
            VALUES (%s, %s, 1000.00, true, 'ZZTEST holder confirmation')
            """,
            (_new_ref(), fx.capped_county),
        )
    expect_rejected(conn, go, "verified=true with no date")


@test("marking a surplus verified without a source is rejected")
def t10(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO claims (ref, county_id, surplus_amount,
                                surplus_verified, surplus_verified_date)
            VALUES (%s, %s, 1000.00, true, CURRENT_DATE)
            """,
            (_new_ref(), fx.capped_county),
        )
    expect_rejected(conn, go, "verified=true with no source")


@test("marking a surplus verified with date and source is accepted")
def t11(conn, fx):
    def go(cur):
        make_claim(cur, fx.capped_county, verified=True)
    expect_accepted(conn, go, "complete verification record")


# ---------------------------------------------------------------------------
# Consent
# ---------------------------------------------------------------------------

@test("recording SMS consent without a consent date is rejected")
def t12(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO claimants (legal_name, sms_consent)
            VALUES ('ZZTEST Synthetic Claimant', true)
            """
        )
    expect_rejected(conn, go, "sms_consent=true with no date")


@test("recording SMS consent with a consent date is accepted")
def t13(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO claimants (legal_name, sms_consent, sms_consent_date)
            VALUES ('ZZTEST Synthetic Claimant', true, CURRENT_DATE)
            """
        )
    expect_accepted(conn, go, "dated consent")


# ---------------------------------------------------------------------------
# Audit reasons
# ---------------------------------------------------------------------------

@test("PII reveal audit row with a reason under 8 characters is rejected")
def t14(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO audit_log (action, entity_type, entity_id, reason)
            VALUES ('pii_reveal', 'claimant', 'zztest', 'because')
            """
        )
    expect_rejected(conn, go, "7-character reason on a PII reveal")


@test("export audit row without a reason is rejected")
def t15(conn, fx):
    def go(cur):
        cur.execute(
            """
            INSERT INTO audit_log (action, entity_type, reason)
            VALUES ('export', 'claims', NULL)
            """
        )
    expect_rejected(conn, go, "export with no reason at all")


# ---------------------------------------------------------------------------
# Audit log is append-only
# ---------------------------------------------------------------------------

def _insert_audit_row(cur):
    cur.execute(
        """
        INSERT INTO audit_log (action, entity_type, entity_id)
        VALUES ('view', 'claim', 'zztest')
        RETURNING id
        """
    )
    return cur.fetchone()[0]


@test("UPDATE on an audit row is rejected")
def t16(conn, fx):
    def go(cur):
        row = _insert_audit_row(cur)
        cur.execute(
            "UPDATE audit_log SET action = 'edited' WHERE id = %s", (row,)
        )
    expect_rejected(conn, go, "update of an audit row")


@test("DELETE on an audit row is rejected")
def t17(conn, fx):
    def go(cur):
        row = _insert_audit_row(cur)
        cur.execute("DELETE FROM audit_log WHERE id = %s", (row,))
    expect_rejected(conn, go, "delete of an audit row")


@test("TRUNCATE of the audit log is rejected")
def t18(conn, fx):
    def go(cur):
        cur.execute("TRUNCATE audit_log")
    expect_rejected(conn, go, "truncate of the audit log")


# ---------------------------------------------------------------------------

def main():
    try:
        conn = psycopg2.connect(DSN)
    except psycopg2.Error as exc:
        print("cannot connect to %s: %s" % (DSN, exc))
        sys.exit(2)

    fx = Fixtures(conn)
    try:
        for fn in TESTS:
            name = fn._test_name
            try:
                fn(conn, fx)
            except AssertionError as exc:
                FAILED.append((name, str(exc)))
                print("FAIL  %s\n      %s" % (name, exc))
            except Exception as exc:  # noqa: BLE001 — report and continue
                conn.rollback()
                FAILED.append((name, str(exc)))
                print("ERROR %s\n      %s" % (name, exc))
            else:
                PASSED.append(name)
                print("ok    %s" % name)
    finally:
        fx.teardown(conn)
        conn.close()

    print()
    print("%d passed, %d failed" % (len(PASSED), len(FAILED)))
    sys.exit(0 if not FAILED else 1)


if __name__ == "__main__":
    main()
