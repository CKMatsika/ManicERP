<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('run_number', 32)->unique();
            $table->string('service_type', 24);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft');
            $table->string('idempotency_key', 191)->unique();
            $table->jsonb('control_totals')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['service_type', 'status']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('bill_number', 32)->unique();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->foreignUlid('billing_run_id')->nullable()->constrained('billing_runs')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 24)->default('draft');
            $table->string('currency_code', 3)->default('USD');
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('balance_minor')->default(0);
            $table->jsonb('calculation_snapshot')->nullable();
            $table->ulid('journal_entry_id')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'status']);
            $table->index('issue_date');
        });

        Schema::create('bill_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('charge_type', 32);
            $table->string('description');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->bigInteger('unit_rate_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->ulid('tariff_version_id')->nullable();
            $table->ulid('meter_reading_id')->nullable();
            $table->jsonb('dimensions')->default('{}');
            $table->timestamps();
        });

        Schema::create('billing_run_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('billing_run_id')->constrained('billing_runs')->cascadeOnDelete();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $table->foreignUlid('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->bigInteger('amount_minor')->default(0);
            $table->decimal('consumption', 14, 3)->nullable();
            $table->string('status', 24)->default('included');
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->unique(['billing_run_id', 'customer_account_id']);
        });

        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_number', 32)->unique();
            $table->ulid('cashier_id');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 16)->default('open');
            $table->bigInteger('opening_float_minor')->default(0);
            $table->bigInteger('expected_cash_minor')->default(0);
            $table->bigInteger('counted_cash_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();
            $table->jsonb('tender_totals')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->string('channel', 16)->default('web');
            $table->string('gateway', 32)->default('sandbox');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 191)->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'status']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_intent_id')->constrained('payment_intents')->cascadeOnDelete();
            $table->string('provider_reference', 96)->nullable()->unique();
            $table->string('status', 24)->default('pending');
            $table->jsonb('request_meta')->nullable();
            $table->jsonb('response_meta')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('payment_number', 32)->unique();
            $table->foreignUlid('payment_intent_id')->nullable()->constrained('payment_intents')->nullOnDelete();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->string('method', 24);
            $table->string('status', 24)->default('pending');
            $table->timestamp('received_at');
            $table->timestamp('verified_at')->nullable();
            $table->ulid('verified_by')->nullable();
            $table->string('verification_source', 24)->nullable();
            $table->string('external_reference', 96)->nullable()->unique();
            $table->foreignUlid('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->ulid('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_account_id', 'status']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('receipt_number', 32)->unique();
            $table->foreignUlid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignUlid('customer_account_id')->constrained('customer_accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamp('issued_at');
            $table->ulid('issued_by')->nullable();
            $table->string('status', 16)->default('issued');
            $table->string('reversal_reason')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignUlid('bill_id')->constrained('bills')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamp('allocated_at');
            $table->ulid('allocated_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_id')->constrained('payments')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('status', 24)->default('requested');
            $table->text('reason');
            $table->ulid('requested_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 24)->unique();
            $table->string('name');
            $table->string('bank_name');
            $table->string('account_number_masked', 40);
            $table->string('gl_account_code', 32);
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->string('statement_number', 64);
            $table->date('statement_date');
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('closing_balance_minor')->default(0);
            $table->string('file_sha256', 64);
            $table->string('status', 24)->default('imported');
            $table->ulid('imported_by')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'statement_number']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('value_date');
            $table->bigInteger('amount_minor');
            $table->string('reference', 96)->nullable();
            $table->string('narrative')->nullable();
            $table->string('status', 16)->default('unmatched');
            $table->foreignUlid('matched_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->ulid('matched_by')->nullable();
            $table->timestamps();

            $table->unique(['bank_statement_id', 'line_number']);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUlid('bank_statement_id')->nullable()->constrained('bank_statements')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16)->default('draft');
            $table->bigInteger('statement_closing_minor')->default(0);
            $table->bigInteger('cashbook_balance_minor')->default(0);
            $table->bigInteger('variance_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->ulid('reconciled_by')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_balance_non_negative CHECK (total_minor >= 0 AND paid_minor >= 0 AND balance_minor >= 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('cashier_sessions');
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('billing_run_items');
        Schema::dropIfExists('billing_runs');
    }
};
