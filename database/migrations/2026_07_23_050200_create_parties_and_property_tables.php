<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People and organizations ------------------------------------------------

        Schema::create('claimants', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('individual'); // individual | heir | estate | trust | business
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('organization_name')->nullable();
            $table->json('prior_names')->nullable();
            $table->text('date_of_birth')->nullable();   // encrypted cast
            $table->text('ssn_last_four')->nullable();   // encrypted cast — NEVER collect full SSN via public forms
            $table->boolean('is_deceased')->default(false);
            $table->date('date_of_death')->nullable();
            $table->string('relationship_to_owner')->nullable();
            $table->string('identity_verification_status')->default('unverified'); // unverified | pending | verified  [ATTORNEY REVIEW: verification standard]
            $table->timestamp('identity_verified_at')->nullable();
            $table->text('notes_internal')->nullable();  // staff-only, never exposed to client portal
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('heirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_id')->constrained()->cascadeOnDelete();      // the heir
            $table->foreignId('decedent_claimant_id')->constrained('claimants')->cascadeOnDelete();
            $table->string('relationship'); // [ATTORNEY REVIEW REQUIRED: heirship determination]
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decedent_name');
            $table->string('probate_court')->nullable();
            $table->string('probate_case_number')->nullable();
            $table->string('personal_representative')->nullable();
            $table->string('status')->default('unknown'); // [ATTORNEY REVIEW REQUIRED: probate workflow]
            $table->timestamps();
        });

        Schema::create('trusts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trust_name');
            $table->string('trustee_name')->nullable();
            $table->date('trust_date')->nullable();
            $table->text('notes')->nullable(); // [ATTORNEY REVIEW REQUIRED: trust ownership]
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_name');
            $table->string('entity_type')->nullable();
            $table->string('state_of_formation', 2)->nullable();
            $table->string('registration_number')->nullable();
            $table->string('status')->nullable(); // [ATTORNEY REVIEW REQUIRED: business ownership claims]
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type')->default('general'); // general | attorney | government | trustee | vendor | referral_partner
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('role_title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attorneys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('firm')->nullable();
            $table->string('bar_number')->nullable();
            $table->string('bar_state', 2)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('practice_areas')->nullable();
            $table->boolean('accepts_referrals')->default(false);
            $table->timestamps();
        });

        Schema::create('government_offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('office_type'); // circuit_court | county_finance | tax_office | auditor | other
            $table->string('county')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('claim_procedure_notes')->nullable(); // researched procedure; NEVER invented
            $table->timestamps();
        });

        Schema::create('trustees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('firm')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('service_type')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('partner_type')->nullable(); // attorney | professional | other
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('agreement_notes')->nullable(); // [ATTORNEY REVIEW REQUIRED: referral fee arrangements]
            $table->timestamps();
        });

        // Polymorphic contact points ---------------------------------------------

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->string('label')->default('mailing');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('county')->nullable();
            $table->string('state', 2);
            $table->string('zip', 10);
            $table->boolean('is_current')->default(true);
            $table->boolean('returned_mail')->default(false);
            $table->timestamps();
        });

        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->morphs('phoneable');
            $table->string('number');
            $table->string('label')->default('mobile');
            $table->boolean('sms_capable')->default(false);
            $table->boolean('do_not_call')->default(false);
            $table->timestamps();
        });

        Schema::create('email_addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('emailable');
            $table->string('email');
            $table->string('label')->default('primary');
            $table->boolean('bounced')->default(false);
            $table->boolean('opted_out')->default(false);
            $table->timestamps();
        });

        // Property, sales, surplus ------------------------------------------------

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->index();
            $table->string('county_code', 8)->nullable();
            $table->string('state', 2)->index();
            $table->string('zip', 10)->nullable();
            $table->string('parcel_number')->nullable()->index();
            $table->string('tax_account_number')->nullable()->index();
            $table->string('property_type')->nullable();
            $table->json('former_owner_names')->nullable();
            $table->timestamps();
        });

        Schema::create('funds_holders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('holder_type'); // court | county | trustee | auditor | state_unclaimed | other
            $table->foreignId('government_office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trustee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('claim_procedure_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('auction_identifier')->nullable()->index();
            $table->string('auctioneer')->nullable();
            $table->date('auction_date')->nullable();
            $table->string('auction_type')->nullable(); // foreclosure | tax_sale | judicial | sheriff | trustee | other
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trustee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sale_type'); // foreclosure | tax_sale | judicial | sheriff | trustee | other
            $table->date('sale_date')->nullable();
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->decimal('opening_bid', 14, 2)->nullable();
            $table->string('ratification_status')->nullable();
            $table->timestamps();
        });

        Schema::create('court_cases', function (Blueprint $table) {
            $table->id();
            $table->string('court_name');
            $table->string('court_case_number')->index();
            $table->string('county')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('case_type')->nullable();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('docket_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'docket_entries', 'court_cases', 'sales', 'auctions', 'funds_holders',
            'properties', 'email_addresses', 'phone_numbers', 'addresses',
            'referral_partners', 'vendors', 'trustees', 'government_offices',
            'attorneys', 'contacts', 'businesses', 'trusts', 'estates', 'heirs', 'claimants',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
