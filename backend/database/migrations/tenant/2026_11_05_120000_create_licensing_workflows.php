<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement('CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <>\'void\' AND billing_run_id IS NOT NULL');
        Schema::table('bills', function (Blueprint $table) {
            $table->string('licence_application_key', 191)->nullable()->unique();
        });

        Schema::create('licence_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 48)->unique();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft');
            $table->string('configuration_authority')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('licence_type_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_type_id')->constrained('licence_types')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('configuration');
            $table->string('approval_status', 24)->default('demonstration');
            $table->ulid('created_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['licence_type_id', 'version']);
        });

        Schema::create('licences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->foreignUlid('licence_type_id')->constrained('licence_types')->restrictOnDelete();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->string('holder_name', 200);
            $table->string('validity_status', 24)->default('not_issued');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('reinstated_at')->nullable();
            $table->text('lifecycle_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_account_id', 'licence_type_id', 'validity_status']);
        });

        Schema::create('licence_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->foreignUlid('licence_id')->nullable()->constrained('licences')->restrictOnDelete();
            $table->foreignUlid('licence_type_id')->constrained('licence_types')->restrictOnDelete();
            $table->foreignUlid('licence_type_version_id')->constrained('licence_type_versions')->restrictOnDelete();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->ulid('submitted_by')->nullable();
            $table->string('attempt_type', 24)->default('application');
            $table->string('status', 32)->default('draft');
            $table->string('idempotency_key', 191);
            $table->jsonb('configuration_snapshot');
            $table->jsonb('form_data')->default('{}');
            $table->string('holder_name', 200);
            $table->string('fee_currency', 3)->default('USD');
            $table->bigInteger('fee_amount_minor')->default(0);
            $table->foreignUlid('fee_bill_id')->nullable()->constrained('bills')->restrictOnDelete();
            $table->timestamp('fee_cancelled_at')->nullable();
            $table->ulid('fee_cancelled_by')->nullable();
            $table->text('fee_cancellation_reason')->nullable();
            $table->foreignUlid('fee_credit_note_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->text('decision_reason')->nullable();
            $table->jsonb('conditions')->default('[]');
            $table->timestamps();
            $table->unique(['customer_account_id', 'idempotency_key']);
            $table->index(['status', 'created_at']);
            $table->index(['licence_id', 'attempt_type', 'status']);
        });

        Schema::create('licence_application_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('document_type', 80);
            $table->unsignedInteger('version')->default(1);
            $table->ulid('supersedes_id')->nullable();
            $table->ulid('uploaded_by')->nullable();
            $table->timestamps();
            $table->unique(['licence_application_id', 'document_type', 'version']);
        });

        Schema::create('licence_inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->ulid('assigned_to')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->string('status', 24)->default('assigned');
            $table->jsonb('findings')->default('[]');
            $table->text('result')->nullable();
            $table->ulid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('licence_certificates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_id')->constrained('licences')->restrictOnDelete();
            $table->foreignUlid('licence_application_id')->constrained('licence_applications')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('certificate_reference', 48)->unique();
            $table->string('verification_id', 64)->unique();
            $table->string('template_code', 80);
            $table->string('holder_name', 200);
            $table->string('licence_type_name', 160);
            $table->date('issued_on');
            $table->date('expires_on');
            $table->jsonb('conditions')->default('[]');
            $table->ulid('replaces_certificate_id')->nullable();
            $table->ulid('issued_by');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['licence_id', 'version']);
        });

        Schema::create('licence_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('licence_application_id')->nullable()->constrained('licence_applications')->restrictOnDelete();
            $table->foreignUlid('licence_id')->nullable()->constrained('licences')->restrictOnDelete();
            $table->string('dedupe_key', 191)->unique();
            $table->string('channel', 24)->default('unavailable');
            $table->string('event', 48);
            $table->string('status', 24)->default('queued');
            $table->jsonb('payload')->default('{}');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_notifications');
        Schema::dropIfExists('licence_certificates');
        Schema::dropIfExists('licence_inspections');
        Schema::dropIfExists('licence_application_documents');
        Schema::dropIfExists('licence_applications');
        Schema::dropIfExists('licences');
        Schema::dropIfExists('licence_type_versions');
        Schema::dropIfExists('licence_types');
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique(['licence_application_key']);
            $table->dropColumn('licence_application_key');
        });
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement('CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end) WHERE status <> \'void\'');
    }
};
