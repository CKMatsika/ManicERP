<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('revenue_policies', function (Blueprint $table): void {
            $table->jsonb('recognition_rule')->nullable();
        });

        Schema::table('licence_notifications', function (Blueprint $table): void {
            $table->ulid('recipient_user_id')->nullable()->index();
            $table->string('subject', 160)->nullable();
            $table->string('preview', 240)->nullable();
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('licence_charges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->foreignUlid('licence_type_version_id')->constrained('licence_type_versions')->restrictOnDelete();
            $table->foreignUlid('bill_id')->nullable()->constrained('bills')->restrictOnDelete();
            $table->foreignUlid('revenue_policy_id')->nullable()->constrained('revenue_policies')->restrictOnDelete();
            $table->string('stage', 24);
            $table->string('trigger_event', 48);
            $table->string('event_key', 191)->unique();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->boolean('payment_prerequisite')->default(true);
            $table->string('payment_required_before', 48)->nullable();
            $table->jsonb('refund_policy')->default('{}');
            $table->jsonb('accounting_treatment')->default('{}');
            $table->string('recognition_method', 32)->default('immediate');
            $table->string('status', 24)->default('outstanding');
            $table->ulid('waived_by')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->ulid('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->bigInteger('released_minor')->default(0);
            $table->timestamps();
            $table->unique(['licence_application_id', 'stage']);
            $table->index(['status', 'stage']);
        });

        Schema::create('licence_fee_releases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_charge_id')->constrained('licence_charges')->restrictOnDelete();
            $table->foreignUlid('revenue_policy_id')->constrained('revenue_policies')->restrictOnDelete();
            $table->foreignUlid('original_journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignUlid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('trigger_event', 48);
            $table->string('event_key', 191)->unique();
            $table->text('evidence');
            $table->ulid('released_by');
            $table->timestamp('released_at');
            $table->timestamps();
            $table->index(['licence_charge_id', 'released_at']);
        });

        Schema::create('licence_document_extractions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->foreignUlid('licence_application_document_id')->constrained('licence_application_documents')->restrictOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedInteger('document_version');
            $table->string('status', 24)->default('proposed');
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->jsonb('proposed_fields')->default('{}');
            $table->jsonb('field_provenance')->default('{}');
            $table->jsonb('reviewed_fields')->nullable();
            $table->ulid('created_by');
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['licence_application_id', 'created_at']);
            $table->index(['document_id', 'document_version']);
        });

        Schema::create('licence_assistant_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->string('draft_type', 32);
            $table->text('content');
            $table->jsonb('source_refs')->default('[]');
            $table->string('status', 24)->default('draft');
            $table->ulid('created_by');
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['licence_application_id', 'status']);
        });

        Schema::table('licence_applications', function (Blueprint $table): void {
            $table->jsonb('staff_draft_fields')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licence_applications', function (Blueprint $table): void {
            $table->dropColumn('staff_draft_fields');
        });
        Schema::dropIfExists('licence_assistant_drafts');
        Schema::dropIfExists('licence_document_extractions');
        Schema::dropIfExists('licence_fee_releases');
        Schema::dropIfExists('licence_charges');
        Schema::table('licence_notifications', function (Blueprint $table): void {
            $table->dropColumn(['recipient_user_id', 'subject', 'preview', 'read_at']);
        });
        Schema::table('revenue_policies', function (Blueprint $table): void {
            $table->dropColumn('recognition_rule');
        });
    }
};
