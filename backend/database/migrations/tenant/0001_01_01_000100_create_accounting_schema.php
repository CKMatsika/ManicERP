<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 16)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('code', 16)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->ulid('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['financial_year_id', 'sequence']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('from_code', 3);
            $table->string('to_code', 3);
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->string('source', 191)->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['from_code', 'to_code', 'effective_date']);
        });

        Schema::create('chart_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('version_no')->default(1);
            $table->string('status', 24)->default('draft');
            $table->string('source_type', 24)->default('demo');
            $table->string('source_authority')->nullable();
            $table->string('source_document')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('production_blocker')->default(true);
            $table->text('notes')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version_no']);
            $table->index('status');
        });

        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chart_version_id')->constrained('chart_versions')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('account_class', 24);
            $table->string('account_type', 24)->default('posting');
            $table->ulid('parent_id')->nullable();
            $table->string('normal_balance', 8)->default('debit');
            $table->boolean('is_control')->default(false);
            $table->boolean('is_bank')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->text('description')->nullable();
            $table->string('gfsm_code', 32)->nullable();
            $table->string('source_code', 32)->nullable();
            $table->timestamps();

            $table->unique(['chart_version_id', 'code']);
            $table->index('parent_id');
            $table->index('account_class');
        });

        Schema::create('gl_account_crosswalks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chart_version_id')->constrained('chart_versions')->cascadeOnDelete();
            $table->string('legacy_code', 32);
            $table->string('gl_account_code', 32);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['chart_version_id', 'legacy_code', 'effective_from']);
        });

        Schema::create('chart_import_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chart_version_id')->nullable()->constrained('chart_versions')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_sha256', 64);
            $table->string('format', 8);
            $table->string('status', 24)->default('uploaded');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->jsonb('preview')->nullable();
            $table->jsonb('mapping')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('uploaded_by')->nullable();
            $table->ulid('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chart_import_rows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('chart_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('code', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('account_class', 24)->nullable();
            $table->string('account_type', 24)->nullable();
            $table->string('parent_code', 32)->nullable();
            $table->string('normal_balance', 8)->nullable();
            $table->boolean('is_control')->default(false);
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->text('description')->nullable();
            $table->string('gfsm_code', 32)->nullable();
            $table->jsonb('errors')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->unique(['batch_id', 'row_number']);
            $table->index(['batch_id', 'status']);
        });

        Schema::create('dimensions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        Schema::create('dimension_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dimension_id')->constrained('dimensions')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->ulid('parent_id')->nullable();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['dimension_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entry_number', 32)->unique();
            $table->string('journal_type', 32);
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->date('entry_date');
            $table->date('posting_date')->nullable();
            $table->foreignUlid('period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->text('description');
            $table->string('status', 16)->default('draft');
            $table->bigInteger('total_debit_minor')->default(0);
            $table->bigInteger('total_credit_minor')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->ulid('posted_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('reversal_of_id')->nullable();
            $table->ulid('reversed_by_id')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['status', 'entry_date']);
            $table->index('period_id');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignUlid('gl_account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->jsonb('dimensions')->default('{}');
            $table->string('memo')->nullable();
            $table->ulid('party_id')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_no']);
            $table->index('gl_account_id');
        });

        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_non_negative CHECK (debit_minor >= 0 AND credit_minor >= 0)');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_single_sided CHECK (NOT (debit_minor > 0 AND credit_minor > 0))');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_has_value CHECK (debit_minor > 0 OR credit_minor > 0)');

        Schema::create('posting_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('event_code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('debit_account_code', 32);
            $table->string('credit_account_code', 32);
            $table->jsonb('required_dimensions')->default('[]');
            $table->jsonb('dimensions')->default('{}');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_rules');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('dimension_values');
        Schema::dropIfExists('dimensions');
        Schema::dropIfExists('chart_import_rows');
        Schema::dropIfExists('chart_import_batches');
        Schema::dropIfExists('gl_account_crosswalks');
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('chart_versions');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('financial_years');
    }
};
