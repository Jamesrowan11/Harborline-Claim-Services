/**
 * Full platform schema — direct port of the original relational design.
 * Works on PostgreSQL 16, MySQL 8, and SQLite (dev/test).
 */
export async function up(knex) {
  await knex.schema.createTable('users', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('email').notNullable().unique();
    t.string('password').notNullable();
    t.string('user_type').notNullable().defaultTo('staff'); // staff | client
    t.integer('claimant_id').nullable().index();
    t.string('role').nullable(); // Super Administrator … Client
    t.string('title').nullable();
    t.string('phone').nullable();
    t.text('mfa_secret').nullable(); // encrypted
    t.timestamp('mfa_enabled_at').nullable();
    t.text('mfa_recovery_codes').nullable(); // encrypted JSON array
    t.timestamp('last_login_at').nullable();
    t.string('last_login_ip').nullable();
    t.timestamp('deactivated_at').nullable();
    t.string('password_reset_token').nullable().index();
    t.timestamp('password_reset_expires_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('settings', (t) => {
    t.increments('id');
    t.string('key').notNullable().unique();
    t.text('value').nullable(); // JSON
    t.string('grouping').notNullable().defaultTo('general');
    t.integer('updated_by').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('pipeline_stages', (t) => {
    t.increments('id');
    t.string('key').notNullable().unique();
    t.string('name').notNullable();
    t.string('client_label').notNullable().defaultTo('Under Preliminary Review');
    t.integer('sort_order').notNullable().defaultTo(0);
    t.boolean('is_closed').notNullable().defaultTo(false);
    t.boolean('is_hold').notNullable().defaultTo(false);
    t.boolean('requires_compliance_review').notNullable().defaultTo(false);
    t.boolean('requires_attorney_review').notNullable().defaultTo(false);
    t.boolean('active').notNullable().defaultTo(true);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('case_number_sequences', (t) => {
    t.increments('id');
    t.integer('year').notNullable();
    t.string('state', 2).notNullable();
    t.string('county_code', 8).notNullable();
    t.bigInteger('last_number').notNullable().defaultTo(0);
    t.unique(['year', 'state', 'county_code']);
  });

  await knex.schema.createTable('teams', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.text('description').nullable();
    t.timestamps(true, true);
  });
  await knex.schema.createTable('team_user', (t) => {
    t.integer('team_id').notNullable();
    t.integer('user_id').notNullable();
    t.primary(['team_id', 'user_id']);
  });

  await knex.schema.createTable('retention_policies', (t) => {
    t.increments('id');
    t.string('record_type').notNullable().unique();
    t.integer('retain_months').nullable();
    t.string('action_after').notNullable().defaultTo('review'); // review | anonymize | delete
    t.boolean('active').notNullable().defaultTo(false);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('saved_views', (t) => {
    t.increments('id');
    t.integer('user_id').notNullable().index();
    t.string('resource').notNullable();
    t.string('name').notNullable();
    t.text('config').notNullable(); // JSON
    t.boolean('is_shared').notNullable().defaultTo(false);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('export_templates', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('report_key').notNullable();
    t.text('columns').notNullable(); // JSON
    t.text('options').nullable(); // JSON
    t.integer('created_by').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('legal_holds', (t) => {
    t.increments('id');
    t.string('holdable_type').notNullable();
    t.integer('holdable_id').notNullable();
    t.string('reason').notNullable();
    t.integer('created_by').nullable();
    t.timestamp('released_at').nullable();
    t.timestamps(true, true);
    t.index(['holdable_type', 'holdable_id']);
  });

  // Parties -----------------------------------------------------------------
  await knex.schema.createTable('claimants', (t) => {
    t.increments('id');
    t.string('type').notNullable().defaultTo('individual'); // individual | heir | estate | trust | business
    t.string('first_name').nullable();
    t.string('middle_name').nullable();
    t.string('last_name').nullable();
    t.string('organization_name').nullable();
    t.text('prior_names').nullable(); // JSON
    t.text('date_of_birth').nullable(); // encrypted
    t.text('ssn_last_four').nullable(); // encrypted — NEVER collected on public forms
    t.boolean('is_deceased').notNullable().defaultTo(false);
    t.date('date_of_death').nullable();
    t.string('relationship_to_owner').nullable();
    t.string('identity_verification_status').notNullable().defaultTo('unverified'); // [ATTORNEY REVIEW: standard]
    t.timestamp('identity_verified_at').nullable();
    t.text('notes_internal').nullable(); // staff-only
    t.timestamp('deleted_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('heirs', (t) => {
    t.increments('id');
    t.integer('claimant_id').notNullable();
    t.integer('decedent_claimant_id').notNullable();
    t.string('relationship').notNullable(); // [ATTORNEY REVIEW REQUIRED]
    t.text('notes').nullable();
    t.timestamps(true, true);
  });
  await knex.schema.createTable('estates', (t) => {
    t.increments('id');
    t.integer('claimant_id').nullable();
    t.string('decedent_name').notNullable();
    t.string('probate_court').nullable();
    t.string('probate_case_number').nullable();
    t.string('personal_representative').nullable();
    t.string('status').notNullable().defaultTo('unknown'); // [ATTORNEY REVIEW REQUIRED]
    t.timestamps(true, true);
  });
  await knex.schema.createTable('trusts', (t) => {
    t.increments('id');
    t.integer('claimant_id').nullable();
    t.string('trust_name').notNullable();
    t.string('trustee_name').nullable();
    t.date('trust_date').nullable();
    t.text('notes').nullable(); // [ATTORNEY REVIEW REQUIRED]
    t.timestamps(true, true);
  });
  await knex.schema.createTable('businesses', (t) => {
    t.increments('id');
    t.integer('claimant_id').nullable();
    t.string('business_name').notNullable();
    t.string('entity_type').nullable();
    t.string('state_of_formation', 2).nullable();
    t.string('registration_number').nullable();
    t.string('status').nullable(); // [ATTORNEY REVIEW REQUIRED]
    t.timestamps(true, true);
  });
  await knex.schema.createTable('contacts', (t) => {
    t.increments('id');
    t.string('contact_type').notNullable().defaultTo('general');
    t.string('name').notNullable();
    t.string('organization').nullable();
    t.string('role_title').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });
  await knex.schema.createTable('attorneys', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('firm').nullable();
    t.string('bar_number').nullable();
    t.string('bar_state', 2).nullable();
    t.string('email').nullable();
    t.string('phone').nullable();
    t.text('practice_areas').nullable(); // JSON
    t.boolean('accepts_referrals').notNullable().defaultTo(false);
    t.timestamps(true, true);
  });
  await knex.schema.createTable('government_offices', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('office_type').notNullable();
    t.string('county').nullable();
    t.string('state', 2).nullable();
    t.string('phone').nullable();
    t.string('email').nullable();
    t.string('website').nullable();
    t.text('claim_procedure_notes').nullable(); // researched, never invented
    t.timestamps(true, true);
  });
  await knex.schema.createTable('trustees', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('firm').nullable();
    t.string('email').nullable();
    t.string('phone').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });
  await knex.schema.createTable('vendors', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('service_type').nullable();
    t.string('email').nullable();
    t.string('phone').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });
  await knex.schema.createTable('referral_partners', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('organization').nullable();
    t.string('partner_type').nullable();
    t.string('email').nullable();
    t.string('phone').nullable();
    t.text('agreement_notes').nullable(); // [ATTORNEY REVIEW REQUIRED: referral fees]
    t.timestamps(true, true);
  });

  // Polymorphic contact points
  for (const [table, morph] of [['addresses', 'addressable'], ['phone_numbers', 'phoneable'], ['email_addresses', 'emailable']]) {
    await knex.schema.createTable(table, (t) => {
      t.increments('id');
      t.string(`${morph}_type`).notNullable();
      t.integer(`${morph}_id`).notNullable();
      if (table === 'addresses') {
        t.string('label').notNullable().defaultTo('mailing');
        t.string('line1').notNullable();
        t.string('line2').nullable();
        t.string('city').notNullable().defaultTo('');
        t.string('county').nullable();
        t.string('state', 2).notNullable().defaultTo('');
        t.string('zip', 10).notNullable().defaultTo('');
        t.boolean('is_current').notNullable().defaultTo(true);
        t.boolean('returned_mail').notNullable().defaultTo(false);
      } else if (table === 'phone_numbers') {
        t.string('number').notNullable();
        t.string('label').notNullable().defaultTo('mobile');
        t.boolean('sms_capable').notNullable().defaultTo(false);
        t.boolean('do_not_call').notNullable().defaultTo(false);
      } else {
        t.string('email').notNullable();
        t.string('label').notNullable().defaultTo('primary');
        t.boolean('bounced').notNullable().defaultTo(false);
        t.boolean('opted_out').notNullable().defaultTo(false);
      }
      t.timestamps(true, true);
      t.index([`${morph}_type`, `${morph}_id`]);
    });
  }

  // Property & records ------------------------------------------------------
  await knex.schema.createTable('properties', (t) => {
    t.increments('id');
    t.string('address_line1').notNullable();
    t.string('address_line2').nullable();
    t.string('city').nullable();
    t.string('county').notNullable().index();
    t.string('county_code', 8).nullable();
    t.string('state', 2).notNullable().index();
    t.string('zip', 10).nullable();
    t.string('parcel_number').nullable().index();
    t.string('tax_account_number').nullable().index();
    t.string('property_type').nullable();
    t.text('former_owner_names').nullable(); // JSON
    t.timestamps(true, true);
  });

  await knex.schema.createTable('funds_holders', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('holder_type').notNullable();
    t.integer('government_office_id').nullable();
    t.integer('trustee_id').nullable();
    t.string('phone').nullable();
    t.string('email').nullable();
    t.text('claim_procedure_notes').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('auctions', (t) => {
    t.increments('id');
    t.string('auction_identifier').nullable().index();
    t.string('auctioneer').nullable();
    t.date('auction_date').nullable();
    t.string('auction_type').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('sales', (t) => {
    t.increments('id');
    t.integer('property_id').notNullable().index();
    t.integer('auction_id').nullable();
    t.integer('trustee_id').nullable();
    t.string('sale_type').notNullable();
    t.date('sale_date').nullable();
    t.decimal('sale_price', 14, 2).nullable();
    t.decimal('opening_bid', 14, 2).nullable();
    t.string('ratification_status').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('court_cases', (t) => {
    t.increments('id');
    t.string('court_name').notNullable();
    t.string('court_case_number').notNullable().index();
    t.string('county').nullable();
    t.string('state', 2).nullable();
    t.string('case_type').nullable();
    t.integer('property_id').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('docket_entries', (t) => {
    t.increments('id');
    t.integer('court_case_id').notNullable().index();
    t.date('entry_date').nullable();
    t.string('title').notNullable();
    t.text('description').nullable();
    t.timestamps(true, true);
  });

  // Leads & cases -----------------------------------------------------------
  await knex.schema.createTable('leads', (t) => {
    t.increments('id');
    t.string('lead_number').notNullable().unique();
    t.string('status').notNullable().defaultTo('new').index();
    t.string('first_name').notNullable();
    t.string('middle_name').nullable();
    t.string('last_name').notNullable();
    t.string('former_owner_name').nullable();
    t.string('relationship_to_owner').nullable();
    t.boolean('former_owner_living').nullable();
    t.string('property_address').nullable();
    t.string('property_county').nullable().index();
    t.string('property_state', 2).nullable();
    t.string('approximate_sale_date').nullable();
    t.string('email').nullable().index();
    t.string('phone').nullable().index();
    t.string('preferred_contact_method').notNullable().defaultTo('email');
    t.string('court_case_number').nullable();
    t.string('parcel_number').nullable();
    t.string('tax_account_number').nullable();
    t.text('description').nullable();
    t.boolean('consent_to_contact').notNullable().defaultTo(false);
    t.boolean('privacy_policy_agreed').notNullable().defaultTo(false);
    t.boolean('electronic_consent').notNullable().defaultTo(false);
    t.boolean('sms_consent').notNullable().defaultTo(false); // optional, separate, never required
    t.string('source').notNullable().defaultTo('website');
    t.string('submitted_ip').nullable();
    t.integer('assigned_to').nullable();
    t.integer('duplicate_of_lead_id').nullable();
    t.integer('case_id').nullable();
    t.timestamp('deleted_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('cases', (t) => {
    t.increments('id');
    t.string('case_number').notNullable().unique();
    t.integer('lead_id').nullable();
    t.integer('pipeline_stage_id').notNullable().index();
    t.integer('property_id').nullable();
    t.integer('sale_id').nullable();
    t.integer('court_case_id').nullable();
    t.integer('funds_holder_id').nullable();
    t.string('case_type').notNullable().defaultTo('surplus_recovery');
    t.string('county').nullable().index();
    t.string('county_code', 8).nullable();
    t.string('state', 2).nullable().index();
    t.decimal('estimated_surplus', 14, 2).nullable();
    t.decimal('verified_surplus', 14, 2).nullable();
    t.string('verification_level').notNullable().defaultTo('none');
    t.string('risk_level').notNullable().defaultTo('normal');
    t.string('legal_complexity').notNullable().defaultTo('standard'); // probate/heirship/trust/business/bankruptcy/disputed → attorney review
    t.integer('assigned_to').nullable();
    t.integer('case_manager_id').nullable();
    t.integer('attorney_id').nullable();
    t.boolean('compliance_hold').notNullable().defaultTo(false);
    t.boolean('legal_hold').notNullable().defaultTo(false);
    t.boolean('automation_paused').notNullable().defaultTo(false);
    t.boolean('outreach_approved').notNullable().defaultTo(false);
    t.timestamp('last_activity_at').nullable();
    t.timestamp('closed_at').nullable();
    t.text('custom_fields').nullable(); // JSON
    t.timestamp('deleted_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('case_claimant', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('claimant_id').notNullable().index();
    t.string('role').notNullable().defaultTo('primary');
    t.decimal('share_percent', 5, 2).nullable(); // [ATTORNEY REVIEW REQUIRED: multiple claimants]
    t.unique(['case_id', 'claimant_id']);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('surplus_records', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('sale_id').nullable();
    t.integer('funds_holder_id').nullable();
    t.decimal('estimated_amount', 14, 2).nullable();
    t.decimal('verified_amount', 14, 2).nullable();
    t.string('status').notNullable().defaultTo('possible');
    t.integer('verified_by').nullable();
    t.timestamp('verified_at').nullable();
    t.timestamp('verification_expires_at').nullable();
    t.text('verification_notes').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('source_records', (t) => {
    t.increments('id');
    t.integer('case_id').nullable().index();
    t.string('source_type').notNullable();
    t.string('title').notNullable();
    t.string('reference').nullable();
    t.date('record_date').nullable();
    t.timestamp('retrieved_at').nullable();
    t.timestamp('stale_after').nullable();
    t.string('status').notNullable().defaultTo('current');
    t.integer('retrieved_by').nullable();
    t.text('summary').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('stage_transitions', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('from_stage_id').nullable();
    t.integer('to_stage_id').notNullable();
    t.integer('user_id').nullable();
    t.string('reason').nullable();
    t.timestamp('created_at').defaultTo(knex.fn.now());
  });

  await knex.schema.createTable('case_tasks', (t) => {
    t.increments('id');
    t.integer('case_id').nullable().index();
    t.integer('lead_id').nullable().index();
    t.string('title').notNullable();
    t.text('description').nullable();
    t.string('status').notNullable().defaultTo('open').index();
    t.string('priority').notNullable().defaultTo('normal');
    t.integer('assigned_to').nullable();
    t.integer('created_by').nullable();
    t.timestamp('due_at').nullable().index();
    t.timestamp('completed_at').nullable();
    t.boolean('client_visible').notNullable().defaultTo(false);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('deadlines', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.string('name').notNullable();
    t.text('description').nullable();
    t.timestamp('due_at').notNullable().index();
    t.boolean('is_critical').notNullable().defaultTo(false);
    t.string('source').nullable(); // researched, never invented
    t.timestamp('met_at').nullable();
    t.integer('created_by').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('notes', (t) => {
    t.increments('id');
    t.string('notable_type').notNullable();
    t.integer('notable_id').notNullable();
    t.integer('user_id').nullable();
    t.text('body').notNullable();
    t.boolean('is_staff_only').notNullable().defaultTo(true);
    t.timestamps(true, true);
    t.index(['notable_type', 'notable_id']);
  });

  // Documents & communications ---------------------------------------------
  await knex.schema.createTable('documents', (t) => {
    t.increments('id');
    t.integer('case_id').nullable().index();
    t.integer('lead_id').nullable();
    t.integer('claimant_id').nullable();
    t.string('category').notNullable().defaultTo('general');
    t.string('title').notNullable();
    t.string('original_filename').notNullable();
    t.string('path').notNullable(); // private storage only — never web-served
    t.string('mime_type').notNullable();
    t.bigInteger('size_bytes').notNullable();
    t.string('sha256', 64).nullable();
    t.string('status').notNullable().defaultTo('pending');
    t.string('virus_scan_status').notNullable().defaultTo('pending');
    t.boolean('client_visible').notNullable().defaultTo(false);
    t.integer('uploaded_by').nullable();
    t.integer('reviewed_by').nullable();
    t.timestamp('reviewed_at').nullable();
    t.text('review_notes').nullable();
    t.timestamp('deleted_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('document_requests', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('claimant_id').nullable();
    t.string('name').notNullable();
    t.text('instructions').nullable();
    t.string('status').notNullable().defaultTo('requested');
    t.timestamp('due_at').nullable();
    t.integer('fulfilled_document_id').nullable();
    t.integer('created_by').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('document_templates', (t) => {
    t.increments('id');
    t.string('key').notNullable().unique();
    t.string('name').notNullable();
    t.string('category').notNullable().defaultTo('letter');
    t.string('subject').nullable();
    t.text('body').notNullable();
    t.integer('version').notNullable().defaultTo(1);
    t.string('approval_status').notNullable().defaultTo('draft');
    t.boolean('requires_attorney_review').notNullable().defaultTo(false);
    t.integer('approved_by').nullable();
    t.timestamp('approved_at').nullable();
    t.boolean('active').notNullable().defaultTo(true);
    t.timestamps(true, true);
  });

  await knex.schema.createTable('document_template_versions', (t) => {
    t.increments('id');
    t.integer('document_template_id').notNullable().index();
    t.integer('version').notNullable();
    t.string('subject').nullable();
    t.text('body').notNullable();
    t.integer('created_by').nullable();
    t.timestamp('created_at').defaultTo(knex.fn.now());
  });

  await knex.schema.createTable('communications', (t) => {
    t.increments('id');
    t.integer('case_id').nullable().index();
    t.integer('lead_id').nullable();
    t.integer('claimant_id').nullable();
    t.string('channel').notNullable(); // email | sms | letter | call | portal_message
    t.string('direction').notNullable().defaultTo('outbound');
    t.string('recipient').nullable();
    t.string('sender').nullable();
    t.string('subject').nullable();
    t.text('body_rendered').nullable(); // final rendered copy, always stored
    t.integer('document_template_id').nullable();
    t.integer('template_version').nullable();
    t.string('status').notNullable().defaultTo('draft');
    t.string('delivery_method').nullable();
    t.integer('generated_by').nullable();
    t.integer('approved_by').nullable();
    t.timestamp('approved_at').nullable();
    t.timestamp('sent_at').nullable();
    t.text('meta').nullable(); // JSON
    t.timestamps(true, true);
  });

  await knex.schema.createTable('consent_records', (t) => {
    t.increments('id');
    t.string('consentable_type').notNullable();
    t.integer('consentable_id').notNullable();
    t.string('consent_type').notNullable();
    t.boolean('granted').notNullable();
    t.string('source').notNullable();
    t.string('text_version').nullable();
    t.string('ip_address').nullable();
    t.timestamp('occurred_at').notNullable();
    t.timestamps(true, true);
    t.index(['consentable_type', 'consentable_id']);
  });

  await knex.schema.createTable('opt_outs', (t) => {
    t.increments('id');
    t.string('channel').notNullable();
    t.string('value').notNullable().index();
    t.string('reason').nullable();
    t.string('optoutable_type').notNullable();
    t.integer('optoutable_id').notNullable();
    t.timestamp('occurred_at').notNullable();
    t.timestamps(true, true);
  });

  // [ATTORNEY REVIEW REQUIRED] Agreement contents (fee model, assignments,
  // POA) must be drafted and approved by licensed counsel before use.
  await knex.schema.createTable('agreements', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('claimant_id').notNullable();
    t.integer('document_template_id').nullable();
    t.integer('template_version').nullable();
    t.string('title').notNullable();
    t.text('body_rendered').nullable();
    t.string('status').notNullable().defaultTo('draft');
    t.decimal('fee_percent', 5, 2).nullable();
    t.decimal('fee_flat', 12, 2).nullable();
    t.timestamp('sent_at').nullable();
    t.timestamp('signed_at').nullable();
    t.string('signature_name').nullable();
    t.string('signature_ip').nullable();
    t.text('signature_consent_text').nullable();
    t.integer('approved_by').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('portal_messages', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('user_id').notNullable();
    t.text('body').notNullable();
    t.timestamp('read_at').nullable();
    t.timestamps(true, true);
  });

  // Financial ---------------------------------------------------------------
  await knex.schema.createTable('claims', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('funds_holder_id').nullable();
    t.string('status').notNullable().defaultTo('preparing');
    t.decimal('amount_claimed', 14, 2).nullable();
    t.decimal('amount_approved', 14, 2).nullable();
    t.timestamp('filed_at').nullable();
    t.string('filing_reference').nullable();
    t.timestamp('decision_at').nullable();
    t.text('notes').nullable(); // [ATTORNEY REVIEW REQUIRED: court filings]
    t.integer('filed_by').nullable();
    t.timestamps(true, true);
  });

  // [ATTORNEY REVIEW REQUIRED] Funds handling / client-money distribution.
  await knex.schema.createTable('payments', (t) => {
    t.increments('id');
    t.integer('case_id').notNullable().index();
    t.integer('claim_id').nullable();
    t.string('payment_type').notNullable();
    t.decimal('amount', 14, 2).notNullable();
    t.string('method').nullable();
    t.string('reference').nullable();
    t.date('occurred_on').notNullable();
    t.integer('recorded_by').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('expenses', (t) => {
    t.increments('id');
    t.integer('case_id').nullable();
    t.string('category').notNullable();
    t.decimal('amount', 12, 2).notNullable();
    t.date('incurred_on').notNullable();
    t.integer('vendor_id').nullable();
    t.integer('recorded_by').nullable();
    t.text('notes').nullable();
    t.timestamps(true, true);
  });

  // Automations & audit -----------------------------------------------------
  await knex.schema.createTable('automations', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.text('description').nullable();
    t.string('trigger_event').notNullable().index();
    t.text('conditions').nullable(); // JSON
    t.text('actions').notNullable(); // JSON
    t.string('mode').notNullable().defaultTo('draft'); // draft | test | approval | active
    t.boolean('is_sensitive').notNullable().defaultTo(false);
    t.integer('approved_by').nullable();
    t.timestamp('approved_at').nullable();
    t.integer('version').notNullable().defaultTo(1);
    t.integer('max_runs_per_hour').notNullable().defaultTo(100);
    t.integer('max_runs_per_case_per_day').notNullable().defaultTo(5);
    t.boolean('paused').notNullable().defaultTo(false);
    t.integer('created_by').nullable();
    t.timestamp('deleted_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('automation_versions', (t) => {
    t.increments('id');
    t.integer('automation_id').notNullable().index();
    t.integer('version').notNullable();
    t.text('definition').notNullable(); // JSON
    t.integer('created_by').nullable();
    t.timestamp('created_at').defaultTo(knex.fn.now());
  });

  await knex.schema.createTable('automation_runs', (t) => {
    t.increments('id');
    t.integer('automation_id').notNullable().index();
    t.integer('case_id').nullable().index();
    t.integer('lead_id').nullable();
    t.string('trigger_event').notNullable();
    t.string('status').notNullable();
    t.string('idempotency_key').notNullable().unique();
    t.text('message').nullable();
    t.text('context').nullable(); // JSON
    t.integer('attempt').notNullable().defaultTo(1);
    t.timestamp('started_at').nullable();
    t.timestamp('finished_at').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('webhook_endpoints', (t) => {
    t.increments('id');
    t.string('name').notNullable();
    t.string('url').notNullable();
    t.text('secret').nullable(); // encrypted
    t.boolean('active').notNullable().defaultTo(false);
    t.timestamps(true, true);
  });

  // Append-only audit trail — no update/delete path exists in the app.
  await knex.schema.createTable('audit_events', (t) => {
    t.increments('id');
    t.integer('user_id').nullable().index();
    t.string('event').notNullable().index();
    t.string('auditable_type').nullable();
    t.integer('auditable_id').nullable();
    t.integer('case_id').nullable().index();
    t.text('old_values').nullable(); // JSON, sensitive fields masked
    t.text('new_values').nullable(); // JSON, sensitive fields masked
    t.string('ip_address').nullable();
    t.string('user_agent').nullable();
    t.timestamp('created_at').defaultTo(knex.fn.now()).index();
  });

  await knex.schema.createTable('export_logs', (t) => {
    t.increments('id');
    t.integer('user_id').nullable();
    t.string('report_key').notNullable();
    t.string('format').notNullable();
    t.text('filters').nullable(); // JSON
    t.integer('row_count').nullable();
    t.timestamp('created_at').defaultTo(knex.fn.now());
  });

  // Simple DB-backed job queue for outbound deliveries ----------------------
  await knex.schema.createTable('jobs', (t) => {
    t.increments('id');
    t.string('job_type').notNullable();
    t.text('payload').notNullable(); // JSON
    t.integer('attempts').notNullable().defaultTo(0);
    t.string('status').notNullable().defaultTo('pending').index(); // pending | done | failed
    t.text('last_error').nullable();
    t.timestamp('run_after').nullable();
    t.timestamps(true, true);
  });

  await knex.schema.createTable('notifications', (t) => {
    t.increments('id');
    t.integer('user_id').notNullable().index();
    t.string('subject').notNullable();
    t.text('body').nullable();
    t.string('reference').nullable();
    t.timestamp('read_at').nullable();
    t.timestamps(true, true);
  });
}

export async function down(knex) {
  const tables = [
    'notifications', 'jobs', 'export_logs', 'audit_events', 'webhook_endpoints',
    'automation_runs', 'automation_versions', 'automations', 'expenses', 'payments',
    'claims', 'portal_messages', 'agreements', 'opt_outs', 'consent_records',
    'communications', 'document_template_versions', 'document_templates',
    'document_requests', 'documents', 'notes', 'deadlines', 'case_tasks',
    'stage_transitions', 'source_records', 'surplus_records', 'case_claimant',
    'cases', 'leads', 'docket_entries', 'court_cases', 'sales', 'auctions',
    'funds_holders', 'properties', 'email_addresses', 'phone_numbers', 'addresses',
    'referral_partners', 'vendors', 'trustees', 'government_offices', 'attorneys',
    'contacts', 'businesses', 'trusts', 'estates', 'heirs', 'claimants',
    'legal_holds', 'export_templates', 'saved_views', 'retention_policies',
    'team_user', 'teams', 'case_number_sequences', 'pipeline_stages', 'settings', 'users',
  ];
  for (const table of tables) await knex.schema.dropTableIfExists(table);
}
