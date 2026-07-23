<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funds_holder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('preparing'); // preparing | filed | additional_info | approved | denied | paid
            $table->decimal('amount_claimed', 14, 2)->nullable();
            $table->decimal('amount_approved', 14, 2)->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->string('filing_reference')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->text('notes')->nullable(); // [ATTORNEY REVIEW REQUIRED: court filings]
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // [ATTORNEY REVIEW REQUIRED] Funds handling and client-money distribution rules
        // vary by jurisdiction; this table records transactions but implements no
        // trust-accounting opinion of its own.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type'); // funds_received | client_distribution | company_fee | refund
            $table->decimal('amount', 14, 2);
            $table->string('method')->nullable(); // check | ach | wire | other
            $table->string('reference')->nullable();
            $table->date('occurred_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event')->index(); // lead.created, case.stage_changed, document.uploaded…
            $table->json('conditions')->nullable();   // [{field, operator, value}]
            $table->json('actions');                  // [{type, params}]
            $table->string('mode')->default('draft'); // draft | test | approval | active
            $table->boolean('is_sensitive')->default(false); // legal/financial/SMS/client outreach — needs admin approval to activate
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('max_runs_per_hour')->default(100);
            $table->unsignedInteger('max_runs_per_case_per_day')->default(5);
            $table->boolean('paused')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('automation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger_event');
            $table->string('status'); // success | failed | skipped | test | pending_approval | blocked
            $table->string('idempotency_key')->unique();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('secret')->nullable(); // encrypted cast
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        // Append-only audit trail. No update/delete path exists in the application.
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('event')->index(); // created | updated | deleted | viewed | downloaded | exported | login | login_failed | permission_changed | consent_changed…
            $table->nullableMorphs('auditable');
            $table->foreignId('case_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_key');
            $table->string('format'); // csv | xlsx | pdf
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'export_logs', 'audit_events', 'webhook_endpoints', 'automation_runs',
            'automation_versions', 'automations', 'expenses', 'payments', 'claims',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
