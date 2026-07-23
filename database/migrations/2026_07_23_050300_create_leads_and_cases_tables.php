<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number')->unique();
            $table->string('status')->default('new')->index(); // new | screening | duplicate | converted | closed
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('former_owner_name')->nullable();
            $table->string('relationship_to_owner')->nullable();
            $table->boolean('former_owner_living')->nullable();
            $table->string('property_address')->nullable();
            $table->string('property_county')->nullable()->index();
            $table->string('property_state', 2)->nullable();
            $table->string('approximate_sale_date')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('preferred_contact_method')->default('email');
            $table->string('court_case_number')->nullable();
            $table->string('parcel_number')->nullable();
            $table->string('tax_account_number')->nullable();
            $table->text('description')->nullable();
            $table->boolean('consent_to_contact')->default(false);
            $table->boolean('privacy_policy_agreed')->default(false);
            $table->boolean('electronic_consent')->default(false);
            $table->boolean('sms_consent')->default(false); // optional, separate, never required
            $table->string('source')->default('website');
            $table->string('submitted_ip')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('duplicate_of_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('case_id')->nullable(); // set on conversion
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('court_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funds_holder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_type')->default('surplus_recovery');
            $table->string('county')->nullable()->index();
            $table->string('county_code', 8)->nullable();
            $table->string('state', 2)->nullable()->index();
            $table->decimal('estimated_surplus', 14, 2)->nullable();
            $table->decimal('verified_surplus', 14, 2)->nullable();
            $table->string('verification_level')->default('none'); // none | preliminary | source_confirmed | holder_confirmed
            $table->string('risk_level')->default('normal'); // low | normal | elevated | high
            $table->string('legal_complexity')->default('standard'); // standard | probate | heirship | trust | business | bankruptcy | disputed  [ATTORNEY REVIEW markers]
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('case_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('attorney_id')->nullable()->constrained('attorneys')->nullOnDelete();
            $table->boolean('compliance_hold')->default(false);
            $table->boolean('legal_hold')->default(false);
            $table->boolean('automation_paused')->default(false);
            $table->boolean('outreach_approved')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('case_claimant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimant_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('primary'); // primary | co_claimant | heir | representative
            $table->decimal('share_percent', 5, 2)->nullable(); // [ATTORNEY REVIEW REQUIRED: multiple claimants]
            $table->unique(['case_id', 'claimant_id']);
            $table->timestamps();
        });

        Schema::create('surplus_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funds_holder_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_amount', 14, 2)->nullable();
            $table->decimal('verified_amount', 14, 2)->nullable();
            $table->string('status')->default('possible'); // possible | verified | already_claimed | none_found
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_type'); // court_docket | land_records | tax_sale_list | auditor_report | newspaper_notice | other_public_record
            $table->string('title');
            $table->string('reference')->nullable(); // URL or citation
            $table->date('record_date')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamp('stale_after')->nullable();
            $table->string('status')->default('current'); // current | stale | superseded
            $table->foreignId('retrieved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages');
            $table->foreignId('to_stage_id')->constrained('pipeline_stages');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('case_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open')->index(); // open | in_progress | done | cancelled
            $table->string('priority')->default('normal'); // low | normal | high | urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('client_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->index();
            $table->boolean('is_critical')->default(false);
            $table->string('source')->nullable(); // where the deadline came from — researched, never invented
            $table->timestamp('met_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_staff_only')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'notes', 'deadlines', 'case_tasks', 'stage_transitions', 'source_records',
            'surplus_records', 'case_claimant', 'cases', 'leads',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
