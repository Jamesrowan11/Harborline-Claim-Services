import bcrypt from "bcryptjs";
import { pool, query } from "./db.js";
import { encryptPII } from "./crypto.js";

/**
 * Synthetic demo data (non-negotiable rule 6).
 *
 * Everything here is obviously fake and labeled as such: names carry a
 * ZZTEST prefix, emails use .example domains, SSNs are 000-00-000x (a range
 * the SSA never issues), and the client shows a SYNTHETIC DEMO DATA badge
 * whenever ZZTEST claimants are present. Never load real claimant data with
 * this script.
 *
 * TOTP secrets and passwords below are demo fixtures for local development
 * only.
 */

const DEMO_USERS = [
  { email: "admin@zztest.example", role: "admin", secret: "JBSWY3DPEHPK3PXP" },
  { email: "manager@zztest.example", role: "case_manager", secret: "JBSWY3DPEHPK3PXQ" },
  { email: "researcher@zztest.example", role: "researcher", secret: "JBSWY3DPEHPK3PXR" },
  { email: "readonly@zztest.example", role: "read_only", secret: "JBSWY3DPEHPK3PXS" },
] as const;

const demoPassword = (role: string) => `demo-${role}-password-123`;

function daysFromNow(n: number): string {
  const d = new Date();
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}

async function main(): Promise<void> {
  const existing = await query(
    "SELECT 1 FROM counties WHERE county_name LIKE 'ZZTEST%' LIMIT 1"
  );
  if (existing.rowCount) {
    console.log("seed: ZZTEST data already present; nothing to do");
    await pool.end();
    return;
  }

  const users: Record<string, string> = {};
  for (const u of DEMO_USERS) {
    const r = await query<{ id: string }>(
      `INSERT INTO users (email, password_hash, totp_secret, role)
       VALUES ($1, $2, $3, $4)
       ON CONFLICT (email) DO UPDATE SET role = EXCLUDED.role
       RETURNING id`,
      [u.email, await bcrypt.hash(demoPassword(u.role), 10), u.secret, u.role]
    );
    users[u.role] = r.rows[0].id;
  }

  const county = async (
    state: string,
    name: string,
    cap: number | null,
    holder: string,
    blackout: number,
    licensing: boolean,
    rule: string
  ) => {
    const r = await query<{ id: string }>(
      `INSERT INTO counties (state, county_name, funds_holder_name, holder_contact,
                             fee_cap_percent, filing_deadline_rule,
                             contact_blackout_days, licensing_required,
                             last_verified_date)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8, CURRENT_DATE - 20)
       RETURNING id`,
      [state, name, holder, `records@${state.toLowerCase()}.zztest.example`, cap, rule, blackout, licensing]
    );
    return r.rows[0].id;
  };

  const baltimore = await county("MD", "ZZTEST Baltimore", 10, "ZZTEST Circuit Court Clerk", 30, false, "180 days from ratification of sale");
  const anneArundel = await county("MD", "ZZTEST Anne Arundel", 10, "ZZTEST County Treasurer", 30, false, "180 days from ratification of sale");
  const fairfax = await county("VA", "ZZTEST Fairfax", 15, "ZZTEST Commissioner of Accounts", 45, true, "2 years from sale confirmation");
  const cook = await county("IL", "ZZTEST Cook", null, "ZZTEST County Treasurer", 60, true, "UNRESEARCHED — do not proceed");
  const maricopa = await county("AZ", "ZZTEST Maricopa", 25, "ZZTEST County Treasurer", 30, false, "2 years from sale date");
  const harris = await county("TX", "ZZTEST Harris", 10, "ZZTEST District Clerk", 30, true, "2 years from sale date");
  const extraCounties = [
    await county("WA", "ZZTEST King", 5, "ZZTEST County Treasurer", 30, false, "3 years from sale date"),
    await county("FL", "ZZTEST Broward", 12, "ZZTEST Clerk of Court", 45, true, "120 days from surplus notice"),
    await county("FL", "ZZTEST Duval", 12, "ZZTEST Clerk of Court", 45, true, "120 days from surplus notice"),
    await county("GA", "ZZTEST Fulton", null, "ZZTEST Tax Commissioner", 60, true, "UNRESEARCHED — do not proceed"),
    await county("OH", "ZZTEST Franklin", 10, "ZZTEST County Auditor", 30, false, "3 years from confirmation"),
    await county("NC", "ZZTEST Wake", 15, "ZZTEST Clerk of Superior Court", 30, false, "1 year from final accounting"),
  ];

  const claimant = async (
    name: string,
    rel: string,
    ssn: string | null,
    dob: string | null,
    opts: Partial<{
      sms: boolean;
      smsDate: string;
      emailConsent: boolean;
      dnc: boolean;
      phone: string;
      email: string;
      preferred: string;
    }> = {}
  ) => {
    const r = await query<{ id: string }>(
      `INSERT INTO claimants (legal_name, relationship_to_owner, ssn_encrypted,
                              dob_encrypted, mailing_address, phone, email,
                              preferred_contact_method, sms_consent,
                              sms_consent_date, email_consent, do_not_contact)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12)
       RETURNING id`,
      [
        name,
        rel,
        ssn ? encryptPII(ssn) : null,
        dob ? encryptPII(dob) : null,
        "100 ZZTEST Synthetic Way, Testville, ZZ 00000",
        opts.phone ?? "+1-555-0100",
        opts.email ?? null,
        opts.preferred ?? "mail",
        opts.sms ?? false,
        opts.smsDate ?? null,
        opts.emailConsent ?? false,
        opts.dnc ?? false,
      ]
    );
    return r.rows[0].id;
  };

  // SSNs use 000-00-000x — never issued by the SSA, unmistakably synthetic.
  const alvarez = await claimant("ZZTEST Maria Alvarez (Estate)", "surviving spouse", "000-00-0001", "1961-01-01", {
    sms: true, smsDate: daysFromNow(-40), emailConsent: true, email: "alvarez@zztest.example", preferred: "sms",
  });
  const okafor = await claimant("ZZTEST Chidi Okafor", "heir", "000-00-0002", "1974-02-02", {
    sms: false, emailConsent: true, email: "okafor@zztest.example", preferred: "email",
  });
  const bergstrom = await claimant("ZZTEST Astrid Bergström (Trust)", "trustee", null, null, { preferred: "phone" });
  const nguyen = await claimant("ZZTEST Linh Nguyen", "heir", "000-00-0003", "1988-03-03", { dnc: true });
  const whitfield = await claimant("ZZTEST Harold Whitfield", "former owner", "000-00-0004", "1955-04-04", {
    sms: true, smsDate: daysFromNow(-10), preferred: "phone",
  });
  const ionescu = await claimant("ZZTEST Elena Ionescu", "heir", "000-00-0005", "1979-05-05", {
    emailConsent: true, email: "ionescu@zztest.example", preferred: "email",
  });

  let refSeq = 100;
  const claim = async (
    countyId: string,
    opts: Partial<{
      surplus: number;
      verified: boolean;
      deadlineDays: number;
      stage: string;
      owner: string;
      saleAmount: number;
      debt: number;
    }> = {}
  ) => {
    const verified = opts.verified ?? false;
    const r = await query<{ id: string; ref: string }>(
      `INSERT INTO claims (ref, county_id, parcel_number, property_address,
                           sale_type, sale_date, sale_amount, debt_satisfied,
                           surplus_amount, surplus_verified,
                           surplus_verified_date, surplus_verified_source,
                           filing_deadline, stage, assigned_to)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15)
       RETURNING id, ref`,
      [
        `HCS-2026-${String(refSeq++).padStart(4, "0")}`,
        countyId,
        `ZZ-${refSeq}-000`,
        "100 ZZTEST Synthetic Way, Testville, ZZ 00000",
        "tax_sale",
        daysFromNow(-120),
        opts.saleAmount ?? 250000,
        opts.debt ?? 180000,
        opts.surplus ?? 50000,
        verified,
        verified ? daysFromNow(-15) : null,
        verified ? "ZZTEST funds holder written confirmation" : null,
        opts.deadlineDays === undefined ? null : daysFromNow(opts.deadlineDays),
        opts.stage ?? "intake",
        users[opts.owner ?? "case_manager"],
      ]
    );
    return r.rows[0];
  };

  const link = (claimId: string, claimantId: string, share: number, primary: boolean) =>
    query(
      `INSERT INTO claim_claimants (claim_id, claimant_id, share_percent, is_primary)
       VALUES ($1,$2,$3,$4)`,
      [claimId, claimantId, share, primary]
    );

  const c1 = await claim(baltimore, { surplus: 61240, verified: true, deadlineDays: 12, stage: "docs pending" });
  const c2 = await claim(anneArundel, { surplus: 18905.5, verified: true, deadlineDays: 28, stage: "agreement out" });
  const c3 = await claim(fairfax, { surplus: 44000, deadlineDays: 39, stage: "verification" });
  const c4 = await claim(cook, { surplus: 9300, deadlineDays: 55, stage: "chain of title", owner: "researcher" });
  const c5 = await claim(maricopa, { surplus: 102377.25, verified: true, deadlineDays: 88, stage: "intake" });
  const c6 = await claim(harris, { surplus: 27600, deadlineDays: 115, stage: "intake", owner: "admin" });

  await link(c1.id, alvarez.toString(), 100, true);
  await link(c2.id, okafor.toString(), 100, true);
  await link(c3.id, bergstrom.toString(), 100, true);
  await link(c4.id, nguyen.toString(), 100, true);
  await link(c5.id, whitfield.toString(), 60, true);
  await link(c5.id, ionescu.toString(), 40, false);
  await link(c6.id, ionescu.toString(), 100, true);

  const requiredDoc = (claimId: string, docType: string, satisfied = false, docId: string | null = null) =>
    query(
      `INSERT INTO required_documents (claim_id, doc_type, required,
                                       satisfied_by_document_id, requested_date)
       VALUES ($1,$2,true,$3,$4)`,
      [claimId, docType, docId, satisfied ? null : daysFromNow(-14)]
    );

  const doc = async (claimId: string, docType: string, filename: string) => {
    const sha = [...Array(64)].map(() => "0123456789abcdef"[Math.floor(Math.random() * 16)]).join("");
    const r = await query<{ id: string }>(
      `INSERT INTO documents (claim_id, doc_type, filename, storage_key, sha256,
                              uploaded_by, received_from, virus_scan_status)
       VALUES ($1,$2,$3,$4,$5,$6,'ZZTEST synthetic upload','clean')
       RETURNING id`,
      [claimId, docType, filename, `zztest/${claimId}/${filename}`, sha, users.case_manager]
    );
    return r.rows[0].id;
  };

  const d1 = await doc(c1.id, "death_certificate", "zztest-death-cert.pdf");
  await requiredDoc(c1.id, "death_certificate", true, d1);
  await requiredDoc(c1.id, "heirship_affidavit");
  await requiredDoc(c1.id, "government_id");
  await requiredDoc(c2.id, "government_id");
  await requiredDoc(c3.id, "trust_instrument");
  const d2 = await doc(c3.id, "deed", "zztest-deed.pdf");
  await requiredDoc(c3.id, "deed", true, d2);
  await doc(c5.id, "chain_of_title", "zztest-title-abstract.pdf");

  await query(
    `INSERT INTO communications (claim_id, claimant_id, direction, channel,
                                 occurred_at, staff_user_id, summary, outcome)
     VALUES ($1,$2,'outbound','sms', now() - interval '3 days', $3,
             'ZZTEST synthetic outreach summary', 'reached'),
            ($1,$2,'inbound','phone', now() - interval '2 days', $3,
             'ZZTEST synthetic callback summary', 'docs promised')`,
    [c1.id, alvarez, users.case_manager]
  );

  // One live, compliant agreement: 9% in a 10%-cap county, executed after
  // verification. Anything less compliant is refused by the triggers.
  const agR = await query<{ id: string }>(
    `INSERT INTO agreements (claim_id, fee_percent, fee_amount, executed_at)
     VALUES ($1, 9.00, 5511.60, now() - interval '5 days')
     RETURNING id`,
    [c1.id]
  );
  // A settled demo invoice against that agreement (no Stripe ids — this
  // predates the integration and demonstrates the paid state).
  await query(
    `INSERT INTO payments (claim_id, agreement_id, amount, status, paid_at, created_by)
     VALUES ($1, $2, 5511.60, 'paid', now() - interval '2 days', $3)`,
    [c1.id, agR.rows[0].id, users.case_manager]
  );

  // ---------------------------------------------------------------------
  // Bulk synthetic claims so the queue fills a 1080p viewport. Everything
  // remains unmistakably fake.
  // ---------------------------------------------------------------------
  const surnames = [
    "Okonkwo", "Petrov", "Tanaka", "Silva", "Novak", "Haddad",
    "Larsen", "Quintero", "Mbeki", "Kowalski", "Dubois", "Ferreira",
  ];
  const claimantPool: string[] = [];
  for (let i = 0; i < surnames.length; i++) {
    claimantPool.push(
      await claimant(
        `ZZTEST ${surnames[i]} Estate`,
        i % 3 === 0 ? "heir" : i % 3 === 1 ? "surviving spouse" : "former owner",
        `000-00-0${String(10 + i)}`,
        `19${60 + i}-01-01`,
        i % 4 === 0
          ? { sms: true, smsDate: daysFromNow(-20 - i), preferred: "sms" }
          : i % 4 === 1
            ? { emailConsent: true, email: `${surnames[i].toLowerCase()}@zztest.example`, preferred: "email" }
            : {}
      )
    );
  }
  const allCounties = [baltimore, anneArundel, fairfax, cook, maricopa, harris, ...extraCounties];
  const stages = ["intake", "research", "chain of title", "verification", "docs pending", "agreement out", "filing prep"];
  const owners = ["case_manager", "case_manager", "researcher", "admin"];
  const deadlines = [5, 8, 11, 15, 19, 23, 27, 31, 36, 41, 46, 52, 58, 64, 71, 78, 86, 94, 103, 112, 121, 131, 141, 150];
  for (let i = 0; i < 24; i++) {
    const verified = i % 3 !== 0;
    const c = await claim(allCounties[i % allCounties.length], {
      surplus: 4000 + i * 3517.25,
      verified,
      deadlineDays: deadlines[i],
      stage: stages[i % stages.length],
      owner: owners[i % owners.length],
      saleAmount: 120000 + i * 9000,
      debt: 90000 + i * 5000,
    });
    await link(c.id, claimantPool[i % claimantPool.length], 100, true);
  }

  console.log("seed: complete — SYNTHETIC DEMO DATA ONLY");
  console.log("");
  console.log("demo logins (TOTP secrets are demo fixtures, base32):");
  for (const u of DEMO_USERS) {
    console.log(
      `  ${u.email}  password=${demoPassword(u.role)}  totp_secret=${u.secret}`
    );
  }
  await pool.end();
}

main().catch((err) => {
  console.error("seed failed:", (err as Error).message);
  process.exit(1);
});
