<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email', 191)->unique();
            $table->string('phone', 32)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 8)->default('en');
            $table->string('status', 24)->default('active');
            $table->boolean('is_platform_admin')->default(false);
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->jsonb('mfa_recovery_codes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('council_id')->constrained('councils')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('description')->nullable();
            $table->jsonb('permissions')->default('[]');
            $table->boolean('requires_mfa')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['council_id', 'code']);
        });

        Schema::create('council_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('council_id')->constrained('councils')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('member_type', 16)->default('staff');
            $table->string('status', 16)->default('active');
            $table->string('job_title')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->unique(['council_id', 'user_id', 'role_id']);
            $table->index(['user_id', 'status']);
            $table->index(['council_id', 'status']);
        });

        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('council_id')->nullable();
            $table->ulid('actor_user_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 191)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['council_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('council_id')->constrained('councils')->cascadeOnDelete();
            $table->ulid('user_id')->nullable();
            $table->string('feature', 64);
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('status', 24)->default('succeeded');
            $table->string('error_code', 64)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['council_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
        Schema::dropIfExists('platform_audit_events');
        Schema::dropIfExists('council_memberships');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
