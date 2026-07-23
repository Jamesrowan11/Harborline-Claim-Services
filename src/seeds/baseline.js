/** Baseline data: pipeline stages, communication templates (draft), and the
 *  22 default automations (draft). Roles live in code (services/permissions.js). */

export const STAGES = [
  ['new_lead', 'New Lead', 'Information Received'],
  ['preliminary_screening', 'Preliminary Screening', 'Under Preliminary Review'],
  ['duplicate_review', 'Duplicate Review', 'Under Preliminary Review'],
  ['property_identified', 'Property Identified', 'Under Preliminary Review'],
  ['source_records_located', 'Source Records Located', 'Under Preliminary Review'],
  ['possible_surplus_identified', 'Possible Surplus Identified', 'Possible Funds Being Verified'],
  ['verification_pending', 'Verification Pending', 'Possible Funds Being Verified'],
  ['surplus_verified', 'Surplus Verified', 'Possible Funds Being Verified'],
  ['funds_holder_confirmed', 'Funds Holder Confirmed', 'Possible Funds Being Verified'],
  ['already_claimed', 'Already Claimed or Distributed', 'Closed'],
  ['claimant_identification', 'Claimant Identification', 'Eligibility Review'],
  ['claimant_located', 'Claimant Located', 'Eligibility Review'],
  ['outreach_compliance_review', 'Outreach Compliance Review', 'Eligibility Review', { requires_compliance_review: true }],
  ['outreach_approved', 'Outreach Approved', 'Eligibility Review'],
  ['contact_attempted', 'Contact Attempted', 'Eligibility Review'],
  ['interested_claimant', 'Interested Claimant', 'Eligibility Review'],
  ['identity_verification', 'Identity Verification', 'Eligibility Review', { requires_compliance_review: true }],
  ['agreement_pending', 'Agreement Pending', 'Documents Needed', { requires_attorney_review: true }],
  ['agreement_signed', 'Agreement Signed', 'Documents Needed'],
  ['documents_requested', 'Documents Requested', 'Documents Needed'],
  ['documents_collected', 'Documents Collected', 'Documents Needed'],
  ['compliance_review', 'Compliance Review', 'Professional Review', { requires_compliance_review: true }],
  ['attorney_review_required', 'Attorney Review Required', 'Professional Review', { requires_attorney_review: true }],
  ['attorney_review_completed', 'Attorney Review Completed', 'Professional Review'],
  ['claim_preparation', 'Claim Preparation', 'Claim Being Prepared'],
  ['claim_filed', 'Claim Filed', 'Claim Submitted'],
  ['awaiting_decision', 'Awaiting Court, County, Trustee, Auditor, or Funds Holder', 'Awaiting Decision'],
  ['additional_information_requested', 'Additional Information Requested', 'Additional Information Needed'],
  ['claim_approved', 'Claim Approved', 'Approved'],
  ['payment_pending', 'Payment Pending', 'Payment Being Processed'],
  ['funds_received', 'Funds Received', 'Payment Being Processed'],
  ['client_distribution_completed', 'Client Distribution Completed', 'Completed'],
  ['company_fee_recorded', 'Company Fee Recorded', 'Completed'],
  ['closed_successfully', 'Closed Successfully', 'Completed', { is_closed: true }],
  ['closed_no_surplus', 'Closed—No Surplus', 'Closed', { is_closed: true }],
  ['closed_previously_claimed', 'Closed—Funds Previously Claimed', 'Closed', { is_closed: true }],
  ['closed_unable_to_locate', 'Closed—Unable to Locate Claimant', 'Closed', { is_closed: true }],
  ['closed_claimant_declined', 'Closed—Claimant Declined', 'Closed', { is_closed: true }],
  ['closed_claim_denied', 'Closed—Claim Denied', 'Closed', { is_closed: true }],
  ['closed_legal_conflict', 'Closed—Legal Conflict', 'Closed', { is_closed: true }],
  ['closed_duplicate', 'Closed—Duplicate', 'Closed', { is_closed: true }],
  ['on_hold', 'On Hold', 'Under Preliminary Review', { is_hold: true }],
];

const FOOTER = '\n\n{{brand.name}}\n{{brand.phone}}  •  {{brand.email}}\n\n{{brand.disclaimer}}';

export const TEMPLATES = [
  ['inquiry_acknowledgment', 'Initial inquiry acknowledgment', 'email', false, 'We received your inquiry — {{case.number}}',
    'Dear {{claimant.first_name}},\n\nThank you for contacting {{brand.name}}. Your inquiry has been received and is under preliminary review. Your reference number is {{case.number}}.\n\nImportant: this acknowledgment does not mean funds exist or that recovery is guaranteed. We will research public records and contact you with honest findings.' + FOOTER],
  ['possible_funds_notice', 'Possible-funds notice', 'letter', true, 'Possible funds that may relate to you — reference {{case.number}}',
    'Dear {{claimant.name}},\n\nOur research of public records suggests you may have a possible claim to funds remaining after a property sale in {{case.county}}, {{case.state}}. The existence, amount, and ownership of any funds must still be independently verified, and recovery is not guaranteed.\n\nYou may verify this letter is genuinely from us at any time using reference {{case.number}} on our website.\n\nThere is no cost and no obligation to speak with us.' + FOOTER],
  ['verification_letter', 'Verification letter', 'letter', true, 'Update on verification — {{case.number}}',
    'Dear {{claimant.name}},\n\nWe are writing with an update on the verification of possible funds connected to reference {{case.number}}. Current status: {{case.status_label}}.' + FOOTER],
  ['follow_up_letter', 'Follow-up letter', 'letter', true, 'Following up — {{case.number}}',
    'Dear {{claimant.name}},\n\nWe recently reached out about possible funds that may relate to you (reference {{case.number}}). If you would like to talk — or would like us to stop contacting you — either request is welcome.' + FOOTER],
  ['document_request', 'Document request', 'email', false, 'Documents needed — {{case.number}}',
    'Dear {{claimant.first_name}},\n\nTo move your file forward we need one or more documents from you. Please upload them through your secure portal — never by regular email.\n\nSign in at any time to see exactly what is needed.' + FOOTER],
  ['unable_to_contact', 'Unable-to-contact letter', 'letter', true, 'We have been unable to reach you — {{case.number}}',
    'Dear {{claimant.name}},\n\nWe have tried to reach you about reference {{case.number}} without success. If you wish to proceed, please contact us. If we do not hear from you, we will close our file without further contact.' + FOOTER],
  ['attorney_referral', 'Attorney referral', 'letter', true, 'Referral to independent counsel — {{case.number}}',
    'Dear {{claimant.name}},\n\nYour matter involves legal steps that must be handled by a licensed attorney. With your consent, we will refer your file to independent counsel. You are always free to choose your own attorney instead.' + FOOTER],
  ['claim_status_update', 'Claim status update', 'email', false, 'Status update — {{case.number}}',
    'Dear {{claimant.first_name}},\n\nYour case status is now: {{case.status_label}}.\n\nSign in to your secure portal for details. We never include sensitive case information in email.' + FOOTER],
  ['additional_information_request', 'Additional-information request', 'email', false, 'A little more information is needed — {{case.number}}',
    'Dear {{claimant.first_name}},\n\nThe funds holder reviewing the claim has asked for additional information. Please sign in to your portal to see the request and respond.' + FOOTER],
  ['case_completion', 'Case completion letter', 'letter', true, 'Your case is complete — {{case.number}}',
    'Dear {{claimant.name}},\n\nWe are pleased to confirm that your case (reference {{case.number}}) is complete. A full summary of amounts received and distributed is available in your portal and enclosed with this letter.\n\nThank you for trusting us with your matter.' + FOOTER],
  ['case_closure', 'Case closure letter', 'letter', true, 'Closing our file — {{case.number}}',
    'Dear {{claimant.name}},\n\nWe are closing our file on reference {{case.number}}. This letter explains why, and what options—if any—remain available to you.' + FOOTER],
  ['internal_research_sheet', 'Internal research sheet', 'internal', false, 'Research sheet — {{case.number}}',
    'CASE: {{case.number}}\nCOUNTY: {{case.county}}, {{case.state}}\nMANAGER: {{case.manager}}\nDATE: {{today}}\n\nSALE DETAILS:\n\nSOURCE RECORDS REVIEWED:\n\nPOSSIBLE SURPLUS:\n\nFUNDS HOLDER:\n\nNEXT STEPS:'],
  ['attorney_case_summary', 'Attorney case summary', 'internal', false, 'Case summary for counsel — {{case.number}}',
    'CASE: {{case.number}} ({{case.county}}, {{case.state}})\nSTATUS: {{case.status_label}}\nPREPARED: {{today}}\n\nCLAIMANT: {{claimant.name}}\n\nFACTS:\n\nOPEN LEGAL QUESTIONS (for counsel):\n\nDOCUMENTS ATTACHED:'],
  ['client_payment_summary', 'Client payment summary', 'letter', true, 'Payment summary — {{case.number}}',
    'Dear {{claimant.name}},\n\nThis summary sets out, line by line, the funds received, the funds holder decision, any agreed fees, and the amount distributed to you for reference {{case.number}}.' + FOOTER],
];

export const AUTOMATIONS = [
  ['New lead acknowledgment', 'lead.created', [], [{ type: 'send_email', params: { template: 'inquiry_acknowledgment' } }], true],
  ['Duplicate-case check reminder', 'lead.created', [], [{ type: 'create_task', params: { title: 'Review possible duplicates flagged on this lead', due_in_days: 2 } }], false],
  ['Researcher assignment by county', 'lead.created', [{ field: 'state', operator: 'equals', value: 'MD' }],
    [{ type: 'add_note', params: { body: 'Assignment rules applied at intake; confirm researcher coverage for this county.' } }], false],
  ['Preliminary research checklist', 'case.created', [], [
    { type: 'create_task', params: { title: 'Pull sale record from public source', due_in_days: 5 } },
    { type: 'create_task', params: { title: 'Identify funds holder', due_in_days: 5 } },
    { type: 'create_task', params: { title: 'Estimate possible surplus from records', due_in_days: 7 } }], false],
  ['Surplus-verification task', 'case.stage_changed', [{ field: 'to_stage', operator: 'equals', value: 'possible_surplus_identified' }],
    [{ type: 'create_task', params: { title: 'Verify surplus with funds holder', due_in_days: 7, priority: 'high' } }], false],
  ['Source recheck every 14 days', 'schedule.tick', [], [{ type: 'reverify_source', params: {} }], false],
  ['Stale source reminder', 'source.stale', [],
    [{ type: 'create_task', params: { title: 'Source record went stale — re-verify before further action', due_in_days: 3, priority: 'high' } }], false],
  ['Contact-review request after verification', 'case.stage_changed', [{ field: 'to_stage', operator: 'equals', value: 'surplus_verified' }], [
    { type: 'change_stage', params: { stage: 'outreach_compliance_review' } },
    { type: 'send_internal_notification', params: { message: 'Case verified — outreach compliance review requested.' } }], false],
  ['Follow-up after unsuccessful contact', 'case.stage_changed', [{ field: 'to_stage', operator: 'equals', value: 'contact_attempted' }],
    [{ type: 'schedule_follow_up', params: { title: 'Second contact attempt', due_in_days: 7 } }], false],
  ['Missing-document reminder', 'schedule.tick', [{ field: 'missing_documents', operator: 'is_true' }],
    [{ type: 'send_email', params: { template: 'document_request' } }], true],
  ['Attorney-review escalation', 'case.field_changed', [{ field: 'legal_complexity', operator: 'not_in', value: ['standard'] }],
    [{ type: 'require_attorney_review', params: {} }], false],
  ['Upcoming deadline alert', 'deadline.approaching', [],
    [{ type: 'send_internal_notification', params: { message: 'A case deadline is approaching within 7 days.' } }], false],
  ['Overdue-task escalation', 'task.overdue', [],
    [{ type: 'escalate_to_manager', params: { message: 'A task on this case is overdue.' } }], false],
  ['Claim-filed status notification', 'claim.filed', [],
    [{ type: 'send_portal_notification', params: { message: 'Your claim has been submitted to the funds holder.' } }], true],
  ['Periodic docket recheck', 'schedule.tick', [{ field: 'stage', operator: 'equals', value: 'awaiting_decision' }],
    [{ type: 'create_task', params: { title: 'Re-check docket / funds holder status', due_in_days: 1 } }], false],
  ['Payment-reconciliation task', 'payment.entered', [],
    [{ type: 'create_task', params: { title: 'Reconcile payment against claim and agreement', due_in_days: 3 } }], false],
  ['Client completion message', 'case.stage_changed', [{ field: 'to_stage', operator: 'equals', value: 'closed_successfully' }],
    [{ type: 'send_email', params: { template: 'case_completion' } }], true],
  ['Closed-case archive process', 'case.stage_changed', [{ field: 'to_stage', operator: 'contains', value: 'closed' }], [
    { type: 'create_task', params: { title: 'Archive file: confirm documents, retention category, and final notes', due_in_days: 7 } },
    { type: 'stop_outreach', params: {} }], false],
  ['Daily employee work summary', 'schedule.tick', [],
    [{ type: 'send_internal_notification', params: { message: 'Daily summary: review your task list and case activity for today.' } }], false],
  ['Weekly manager pipeline report', 'schedule.tick', [],
    [{ type: 'send_internal_notification', params: { message: 'Weekly pipeline review: open the Reporting Center for current numbers.' } }], false],
  ['Monthly accounting workbook', 'schedule.tick', [],
    [{ type: 'create_task', params: { title: 'Generate monthly accounting reconciliation workbook from Print & Export Center', due_in_days: 2 } }], false],
  ['Monthly compliance audit report', 'schedule.tick', [],
    [{ type: 'create_task', params: { title: 'Generate monthly audit report and review consent/opt-out logs', due_in_days: 2 } }], false],
];

export async function seedBaseline(db) {
  for (const [i, [key, name, clientLabel, flags = {}]] of STAGES.entries()) {
    const row = {
      name, client_label: clientLabel, sort_order: (i + 1) * 10,
      is_closed: !!flags.is_closed, is_hold: !!flags.is_hold,
      requires_compliance_review: !!flags.requires_compliance_review,
      requires_attorney_review: !!flags.requires_attorney_review,
      active: true,
    };
    const existing = await db('pipeline_stages').where({ key }).first();
    if (existing) await db('pipeline_stages').where({ key }).update(row);
    else await db('pipeline_stages').insert({ key, ...row });
  }

  for (const [key, name, category, attorneyReview, subject, body] of TEMPLATES) {
    if (!(await db('document_templates').where({ key }).first())) {
      // Every template ships in DRAFT — nothing sends until approved.
      await db('document_templates').insert({
        key, name, category, subject, body,
        approval_status: 'draft', requires_attorney_review: attorneyReview, active: true,
      });
    }
  }

  for (const [name, trigger, conditions, actions, sensitive] of AUTOMATIONS) {
    if (!(await db('automations').where({ name }).first())) {
      // ALL default automations ship in draft mode; sensitive ones require
      // explicit administrator approval on top of activation.
      await db('automations').insert({
        name, description: 'Default template automation. Review conditions and actions, then activate deliberately.',
        trigger_event: trigger, conditions: JSON.stringify(conditions), actions: JSON.stringify(actions),
        mode: 'draft', is_sensitive: sensitive,
      });
    }
  }
}
