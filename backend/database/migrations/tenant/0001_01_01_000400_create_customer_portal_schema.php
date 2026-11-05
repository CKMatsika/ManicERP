<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_account_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->string('relationship', 24)->default('owner');
            $table->string('status', 24)->default('pending');
            $table->string('verification_method', 32)->default('otp');
            $table->timestamp('requested_at');
            $table->timestamp('verified_at')->nullable();
            $table->ulid('verified_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->ulid('evidence_document_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'customer_account_id']);
            $table->index(['customer_account_id', 'status']);
        });

        Schema::create('account_link_verifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('portal_account_link_id')->constrained('portal_account_links')->cascadeOnDelete();
            $table->string('channel', 16)->default('email');
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('sent_to_masked', 96)->nullable();
            $table->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 24)->unique();
            $table->foreignUlid('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('category', 32);
            $table->string('subject');
            $table->text('description');
            $table->string('status', 24)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->ulid('assigned_to')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('customer_account_id');
        });

        Schema::create('customer_notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('channel', 16);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notification_preferences');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('account_link_verifications');
        Schema::dropIfExists('portal_account_links');
    }
};
