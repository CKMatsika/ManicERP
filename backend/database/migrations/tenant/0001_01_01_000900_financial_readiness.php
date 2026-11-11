<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_source_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('internal_title');
            $table->string('file_name');
            $table->string('storage_path', 512);
            $table->string('document_type', 32)->default('manual');
            $table->string('issuing_authority')->nullable();
            $table->string('edition', 64)->nullable();
            $table->string('document_date', 64)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('file_hash', 64)->unique();
            $table->timestamp('received_at');
            $table->string('examination_status', 24)->default('received');
            $table->string('currency_status', 32)->default('unverified');
            $table->jsonb('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_document_id')->constrained('accounting_source_documents')->restrictOnDelete();
            $table->string('requirement_code', 64)->unique();
            $table->string('title');
            $table->string('section_ref', 64)->nullable();
            $table->string('page_ref', 64)->nullable();
            $table->text('requirement_text');
            $table->string('category', 48);
            $table->string('jurisdiction', 32)->default('zimbabwe');
            $table->string('applicability_status', 32)->default('assessment_required');
            $table->text('applicability_rationale')->nullable();
            $table->boolean('requirement_extracted')->default(false);
            $table->boolean('applicability_verified')->default(false);
            $table->boolean('policy_approved')->default(false);
            $table->boolean('implementation_tested')->default(false);
            $table->string('implementation_status', 24)->default('not_implemented');
            $table->jsonb('affected_workflows')->nullable();
            $table->text('blocker')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'applicability_status']);
        });

        Schema::table('accounting_standards', function (Blueprint $table) {
            $table->foreignUlid('source_document_id')->nullable()->constrained('accounting_source_documents')->nullOnDelete();
            $table->string('source_section', 64)->nullable();
            $table->string('source_page', 64)->nullable();
            $table->text('zfrm_note')->nullable();
        });

        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 64)->unique();
            $table->jsonb('value')->nullable();
            $table->ulid('set_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('batch_number', 32)->unique();
            $table->string('type', 32);
            $table->string('status', 24)->default('validated');
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->date('cutover_date');
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->jsonb('totals')->nullable();
            $table->jsonb('control_totals')->nullable();
            $table->jsonb('reconciliation')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('uploaded_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->ulid('journal_entry_id')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->unique(['type', 'file_hash']);
        });

        Schema::create('migration_rows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('migration_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('row_type', 32)->nullable();
            $table->string('account_code', 32)->nullable();
            $table->string('account_number', 24)->nullable();
            $table->string('reference', 96)->nullable();
            $table->string('description')->nullable();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->date('due_date')->nullable();
            $table->jsonb('dimensions')->nullable();
            $table->jsonb('data')->nullable();
            $table->jsonb('errors')->nullable();
            $table->string('status', 16)->default('pending');
            $table->ulid('bill_id')->nullable();
            $table->ulid('credit_id')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'row_number']);
            $table->index(['batch_id', 'status']);
        });

        Schema::create('account_credits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('source', 32);
            $table->ulid('migration_batch_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->bigInteger('remaining_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->string('reference', 96)->nullable();
            $table->string('status', 16)->default('open');
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'status']);
            $table->unique(['customer_account_id', 'reference']);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('refund_number', 32)->nullable()->unique();
            $table->foreignUlid('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
            $table->string('type', 32)->default('unapplied');
            $table->bigInteger('approved_amount_minor')->nullable();
            $table->bigInteger('settled_amount_minor')->nullable();
            $table->ulid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('sandbox_reference', 96)->nullable();
            $table->ulid('journal_entry_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->text('customer_note')->nullable();
            $table->jsonb('metadata')->nullable();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->ulid('payment_id')->nullable()->change();
        });

        Schema::create('report_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 48)->unique();
            $table->string('name');
            $table->string('statement_type', 32);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('draft');
            $table->foreignUlid('source_document_id')->nullable()->constrained('accounting_source_documents')->nullOnDelete();
            $table->string('source_ref', 96)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('units', 24)->default('units');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('report_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $table->string('line_code', 64);
            $table->string('label');
            $table->string('section', 64)->nullable();
            $table->string('line_type', 16)->default('detail');
            $table->string('sign', 8)->default('debit');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_comparative')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['report_definition_id', 'line_code']);
        });

        Schema::create('report_line_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_line_id')->constrained('report_lines')->cascadeOnDelete();
            $table->string('gl_account_code', 32)->nullable();
            $table->string('account_class', 24)->nullable();
            $table->jsonb('dimension_filters')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('draft');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('source', 64)->default('manual');
            $table->timestamps();

            $table->index(['gl_account_code', 'effective_from']);
            $table->index(['report_line_id', 'status']);
        });

        Schema::create('revenue_policies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('charge_type', 32);
            $table->string('recognition_method', 32);
            $table->string('receivable_account_code', 32);
            $table->string('revenue_account_code', 32)->nullable();
            $table->string('deferred_account_code', 32)->nullable();
            $table->foreignUlid('source_document_id')->nullable()->constrained('accounting_source_documents')->nullOnDelete();
            $table->string('source_ref', 96)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['charge_type', 'version']);
            $table->index(['charge_type', 'status', 'effective_from']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->string('origin', 32)->default('billing');
            $table->ulid('migration_batch_id')->nullable();
            $table->ulid('revenue_policy_id')->nullable();
            $table->string('revenue_recognition_method', 32)->nullable();
        });

        // Opening-balance invoices may legitimately share an account and cutover
        // date. Keep the duplicate-billing guard for ordinary billing only.
        DB::statement('DROP INDEX IF EXISTS bills_active_period_unique');
        DB::statement("CREATE UNIQUE INDEX bills_active_period_unique ON bills (customer_account_id, period_start, period_end, service_type) WHERE status <> 'void' AND origin = 'billing'");

        Schema::table('policy_documents', function (Blueprint $table) {
            $table->foreignUlid('source_document_id')->nullable()->constrained('accounting_source_documents')->nullOnDelete();
            $table->string('section_ref', 64)->nullable();
            $table->string('page_ref', 64)->nullable();
            $table->string('document_kind', 32)->default('council_policy');
            $table->boolean('approved_policy')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('policy_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropColumn(['section_ref', 'page_ref', 'document_kind', 'approved_policy']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['origin', 'migration_batch_id', 'revenue_policy_id', 'revenue_recognition_method']);
        });

        Schema::dropIfExists('revenue_policies');
        Schema::dropIfExists('report_line_mappings');
        Schema::dropIfExists('report_lines');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('account_credits');
        Schema::dropIfExists('migration_rows');
        Schema::dropIfExists('migration_batches');
        Schema::dropIfExists('accounting_settings');

        Schema::table('accounting_standards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropColumn(['source_section', 'source_page', 'zfrm_note']);
        });

        Schema::dropIfExists('accounting_requirements');
        Schema::dropIfExists('accounting_source_documents');

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn([
                'refund_number', 'customer_account_id', 'type', 'approved_amount_minor',
                'settled_amount_minor', 'reviewed_by', 'reviewed_at', 'settled_at',
                'sandbox_reference', 'journal_entry_id', 'idempotency_key', 'customer_note', 'metadata',
            ]);
        });
    }
};
