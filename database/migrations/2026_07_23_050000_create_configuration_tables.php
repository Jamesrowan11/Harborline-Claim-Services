<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administrator-editable settings (branding, formats, consent text, fees, retention…)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('client_label')->default('Under Preliminary Review');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_hold')->default(false);
            $table->boolean('requires_compliance_review')->default(false);
            $table->boolean('requires_attorney_review')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('case_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('state', 2);
            $table->string('county_code', 8);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['year', 'state', 'county_code']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_id', 'user_id']);
        });

        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('record_type')->unique(); // leads, closed_cases, documents, communications, audit_logs, exports, temp_files, identity_records, backups
            $table->unsignedInteger('retain_months')->nullable(); // null = keep forever
            $table->string('action_after')->default('review'); // review | anonymize | delete
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource'); // cases, leads, payments…
            $table->string('name');
            $table->json('config'); // filters, sort, columns, grouping, page size
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('export_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_key'); // master_cases, leads, payments…
            $table->json('columns');
            $table->json('options')->nullable(); // grouping, totals, sort, orientation, page size, header/footer, logo, watermark, confidentiality label
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->morphs('holdable');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('export_templates');
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('case_number_sequences');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('settings');
    }
};
