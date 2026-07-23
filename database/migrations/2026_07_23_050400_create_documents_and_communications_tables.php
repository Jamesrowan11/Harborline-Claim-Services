<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('general'); // identity | agreement | court | property | correspondence | general
            $table->string('title');
            $table->string('original_filename');
            $table->string('disk')->default('private');
            $table->string('path'); // private disk only — never a public storage link
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('virus_scan_status')->default('pending'); // pending | clean | infected | skipped
            $table->boolean('client_visible')->default(false);
            $table->string('uploaded_by_type')->nullable(); // App\Models\User (staff or client)
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // e.g. "Government-issued photo ID (redacted copy acceptable)"
            $table->text('instructions')->nullable();
            $table->string('status')->default('requested'); // requested | received | approved | waived
            $table->timestamp('due_at')->nullable();
            $table->foreignId('fulfilled_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->default('letter'); // letter | email | sms | internal | agreement
            $table->string('subject')->nullable();
            $table->longText('body'); // supports {{merge_fields}}
            $table->unsignedInteger('version')->default(1);
            $table->string('approval_status')->default('draft'); // draft | pending_review | approved | retired
            $table->boolean('requires_attorney_review')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel'); // email | sms | letter | call | portal_message
            $table->string('direction')->default('outbound'); // outbound | inbound
            $table->string('recipient')->nullable();
            $table->string('sender')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_rendered')->nullable(); // final rendered copy, always stored
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('status')->default('draft'); // draft | queued | sent | delivered | bounced | returned_mail | failed | received
            $table->string('delivery_method')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable(); // call outcome, bounce code, tracking ids
            $table->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->morphs('consentable'); // lead or claimant
            $table->string('consent_type'); // contact | privacy_policy | electronic_communications | sms
            $table->boolean('granted');
            $table->string('source'); // web_form | portal | phone | paper
            $table->string('text_version')->nullable(); // which consent text version was shown
            $table->string('ip_address')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('channel'); // email | sms | phone | mail | all
            $table->string('value')->index(); // email address or phone number
            $table->string('reason')->nullable();
            $table->morphs('optoutable');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('title');
            $table->longText('body_rendered')->nullable();
            // [ATTORNEY REVIEW REQUIRED] Fee model, assignment language, and POA terms
            // must be drafted/approved by licensed counsel before any agreement is sent.
            $table->string('status')->default('draft'); // draft | pending_attorney_review | approved | sent | signed | declined | void
            $table->decimal('fee_percent', 5, 2)->nullable();
            $table->decimal('fee_flat', 12, 2)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_ip')->nullable();
            $table->text('signature_consent_text')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('portal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(); // sender (staff or client user)
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'portal_messages', 'agreements', 'opt_outs', 'consent_records', 'communications',
            'document_template_versions', 'document_templates', 'document_requests', 'documents',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
