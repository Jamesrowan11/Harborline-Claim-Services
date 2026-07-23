<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('staff')->index(); // staff | client
            $table->foreignId('claimant_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('phone')->nullable();
            $table->text('mfa_secret')->nullable();          // encrypted cast
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();  // encrypted cast
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_type', 'claimant_id', 'title', 'phone', 'mfa_secret',
                'mfa_enabled_at', 'mfa_recovery_codes', 'last_login_at',
                'last_login_ip', 'deactivated_at',
            ]);
        });
    }
};
