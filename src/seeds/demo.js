import bcrypt from 'bcryptjs';
import { nextCaseNumber } from '../services/caseNumber.js';

/**
 * FICTIONAL DEMONSTRATION DATA ONLY. Every person, property, sale, court
 * case, and amount is invented; names are deliberately implausible and all
 * records carry a "[DEMO]" marker. Never seed in production.
 */
export async function seedDemo(db) {
  const password = (p) => bcrypt.hash(p, 10);

  const user = async (email, name, role, extra = {}) => {
    const existing = await db('users').where({ email }).first();
    if (existing) return existing.id;
    const [{ id }] = await db('users').insert({
      email, name, role,
      user_type: role === 'Client' ? 'client' : 'staff',
      password: await password(`demo-${role.toLowerCase().replace(/\W+/g, '-')}-password-123`),
      ...extra,
    }, ['id']);
    return id;
  };

  const adminId = await user('admin@example.test', 'Demo Administrator', 'Super Administrator');
  const managerId = await user('manager@example.test', 'Casey Demomanager', 'Case Manager', { title: 'Case Manager' });
  const researcherId = await user('researcher@example.test', 'Riley Demoresearcher', 'Researcher', { title: 'Researcher' });

  let holder = await db('funds_holders').where({ name: '[DEMO] Circuit Court for Sample County' }).first();
  if (!holder) {
    const [{ id }] = await db('funds_holders').insert({
      name: '[DEMO] Circuit Court for Sample County', holder_type: 'court',
      claim_procedure_notes: 'Fictional demo record.',
    }, ['id']);
    holder = { id };
  }

  for (const [i, [first, last, status, county]] of [
    ['Dana', 'Demoperson', 'screening', 'Anne Arundel'],
    ['Morgan', 'Sampleheir', 'new', 'Howard'],
    ['Jordan', 'Testclaimant', 'new', 'Baltimore'],
  ].entries()) {
    const leadNumber = `L-2026-90000${i + 1}`;
    if (!(await db('leads').where({ lead_number: leadNumber }).first())) {
      await db('leads').insert({
        lead_number: leadNumber, status, first_name: first, last_name: last,
        former_owner_name: `${first} ${last}`, relationship_to_owner: 'I am the former owner',
        property_address: `[DEMO] ${100 + i} Fictional Harbor Lane`, property_county: county, property_state: 'MD',
        email: `${first.toLowerCase()}@example.test`, phone: '555-0100',
        consent_to_contact: true, privacy_policy_agreed: true, electronic_consent: true,
        source: 'demo_seed', assigned_to: researcherId,
      });
    }
  }

  const stageId = async (key) => (await db('pipeline_stages').where({ key }).first()).id;

  const demoCases = [
    ['possible_surplus_identified', 'Anne Arundel', 'AA', 42500, null],
    ['documents_requested', 'Howard', 'HO', 61200, 58900],
    ['claim_filed', 'Baltimore', 'BA', 19800, 19800],
    ['closed_successfully', 'Anne Arundel', 'AA', 33000, 33000],
  ];

  for (const [i, [stageKey, county, code, estimated, verified]] of demoCases.entries()) {
    const address = `[DEMO] ${200 + i} Imaginary Shore Drive`;
    if (await db('properties').where({ address_line1: address }).first()) continue;

    const [{ id: propertyId }] = await db('properties').insert({
      address_line1: address, city: 'Sampletown', county, county_code: code, state: 'MD',
      zip: `21${400 + i}`, parcel_number: `DEMO-PARCEL-${1000 + i}`,
      former_owner_names: JSON.stringify(['[DEMO] Pat Demoperson']),
    }, ['id']);

    const [{ id: saleId }] = await db('sales').insert({
      property_id: propertyId, sale_type: 'foreclosure',
      sale_date: new Date(Date.now() - (8 + i) * 30 * 86400_000), sale_price: 250000 + i * 10000,
    }, ['id']);

    const [{ id: courtCaseId }] = await db('court_cases').insert({
      court_name: `[DEMO] Circuit Court for ${county} County`, court_case_number: `DEMO-CV-2025-00${10 + i}`,
      county, state: 'MD', property_id: propertyId,
    }, ['id']);
    await db('docket_entries').insert({ court_case_id: courtCaseId, title: '[DEMO] Report of sale filed', entry_date: new Date(Date.now() - (7 + i) * 30 * 86400_000) });

    const [{ id: caseId }] = await db('cases').insert({
      case_number: await nextCaseNumber('MD', code),
      pipeline_stage_id: await stageId(stageKey),
      property_id: propertyId, sale_id: saleId, court_case_id: courtCaseId, funds_holder_id: holder.id,
      county, county_code: code, state: 'MD',
      estimated_surplus: estimated, verified_surplus: verified,
      verification_level: verified ? 'holder_confirmed' : 'preliminary',
      assigned_to: researcherId, case_manager_id: managerId,
      outreach_approved: verified !== null,
      last_activity_at: new Date(Date.now() - i * 3 * 86400_000),
      closed_at: stageKey === 'closed_successfully' ? new Date(Date.now() - 10 * 86400_000) : null,
    }, ['id']);

    const [{ id: claimantId }] = await db('claimants').insert({
      type: 'individual', first_name: `Pat${i}`, last_name: 'Demoperson',
      relationship_to_owner: 'I am the former owner',
    }, ['id']);
    await db('case_claimant').insert({ case_id: caseId, claimant_id: claimantId, role: 'primary' });
    await db('email_addresses').insert({ emailable_type: 'claimant', emailable_id: claimantId, email: `client${i}@example.test` });

    await db('surplus_records').insert({
      case_id: caseId, sale_id: saleId, funds_holder_id: holder.id,
      estimated_amount: estimated, verified_amount: verified,
      status: verified ? 'verified' : 'possible',
      verified_at: verified ? new Date(Date.now() - 60 * 86400_000) : null,
      verification_expires_at: verified ? new Date(Date.now() + (30 - i * 20) * 86400_000) : null,
      verified_by: verified ? researcherId : null,
    });

    await db('source_records').insert({
      case_id: caseId, source_type: 'auditor_report', title: '[DEMO] Auditor report for sale',
      reference: 'https://example.test/demo-record', record_date: new Date(Date.now() - 180 * 86400_000),
      retrieved_at: new Date(Date.now() - 30 * 86400_000), stale_after: new Date(Date.now() + 14 * 86400_000),
      retrieved_by: researcherId, summary: 'Fictional demonstration source record.',
    });

    await db('case_tasks').insert({
      case_id: caseId, title: `[DEMO] Review next step for stage: ${stageKey}`,
      assigned_to: managerId, due_at: new Date(Date.now() + 5 * 86400_000), created_by: adminId,
    });
    await db('deadlines').insert({
      case_id: caseId, name: '[DEMO] Follow-up date (not a legal deadline)',
      due_at: new Date(Date.now() + (20 + i * 5) * 86400_000), source: 'internal',
    });
    await db('communications').insert({
      case_id: caseId, channel: 'letter', direction: 'outbound', status: 'sent',
      subject: '[DEMO] Seeded outbound letter record', body_rendered: 'Fictional demonstration communication.',
      sent_at: new Date(Date.now() - 14 * 86400_000), generated_by: managerId,
    });

    if (['claim_filed', 'closed_successfully'].includes(stageKey)) {
      await db('claims').insert({
        case_id: caseId, funds_holder_id: holder.id,
        status: stageKey === 'closed_successfully' ? 'paid' : 'filed',
        amount_claimed: verified, amount_approved: stageKey === 'closed_successfully' ? verified : null,
        filed_at: new Date(Date.now() - 30 * 86400_000), filed_by: managerId,
      });
    }

    if (stageKey === 'documents_requested') {
      await db('document_requests').insert({
        case_id: caseId, claimant_id: claimantId,
        name: '[DEMO] Government-issued photo ID (copy)',
        instructions: 'Upload through the secure portal. Fictional demo request.',
        due_at: new Date(Date.now() + 14 * 86400_000), created_by: managerId,
      });
    }

    if (stageKey === 'closed_successfully') {
      for (const [type, amount, daysAgo] of [
        ['funds_received', 33000, 20], ['client_distribution', 24750, 12], ['company_fee', 8250, 12],
      ]) {
        await db('payments').insert({
          case_id: caseId, payment_type: type, amount, method: 'check',
          occurred_on: new Date(Date.now() - daysAgo * 86400_000), recorded_by: adminId,
        });
      }
      await db('agreements').insert({
        case_id: caseId, claimant_id: claimantId, title: '[DEMO] Assistance Agreement',
        body_rendered: 'FICTIONAL DEMONSTRATION AGREEMENT.\n\n[ATTORNEY REVIEW REQUIRED] Real agreement text must be drafted by licensed counsel.',
        status: 'signed', fee_percent: 25,
        sent_at: new Date(Date.now() - 90 * 86400_000), signed_at: new Date(Date.now() - 90 * 86400_000),
        signature_name: 'Pat3 Demoperson', approved_by: adminId,
      });
      const clientId = await user('client@example.test', 'Pat3 Demoperson', 'Client');
      await db('users').where({ id: clientId }).update({ claimant_id: claimantId });
    }
  }
}
