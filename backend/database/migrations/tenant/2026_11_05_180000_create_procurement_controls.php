<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_suppliers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('party_id')->unique()->constrained('parties')->restrictOnDelete();
            $table->string('supplier_number', 40)->unique();
            $table->string('status', 24)->default('pending');
            $table->string('tax_identifier', 100)->nullable();
            $table->jsonb('verification')->default('{}');
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'supplier_number']);
        });

        Schema::create('procurement_supplier_bank_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->string('bank_name', 120);
            $table->string('account_name', 160);
            $table->text('account_number_encrypted');
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 24)->default('pending_approval');
            $table->jsonb('change_history')->default('[]');
            $table->ulid('submitted_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('procurement_budgets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 180);
            $table->string('currency_code', 3)->default('USD');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'period_start', 'period_end']);
        });

        Schema::create('procurement_budget_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('procurement_budgets')->restrictOnDelete();
            $table->string('account_code', 32);
            $table->jsonb('dimensions')->default('{}');
            $table->bigInteger('approved_minor');
            $table->bigInteger('transferred_in_minor')->default(0);
            $table->bigInteger('transferred_out_minor')->default(0);
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('actual_minor')->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'account_code', 'dimensions']);
            $table->index(['budget_id', 'account_code']);
        });

        Schema::create('procurement_commitments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_line_id')->constrained('procurement_budget_lines')->restrictOnDelete();
            $table->string('source_type', 48);
            $table->string('source_id', 64);
            $table->string('status', 24)->default('reserved');
            $table->bigInteger('reserved_minor');
            $table->bigInteger('converted_minor')->default(0);
            $table->string('idempotency_key', 191)->unique();
            $table->text('reason')->nullable();
            $table->ulid('created_by');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['budget_line_id', 'status']);
        });

        Schema::create('procurement_budget_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('procurement_budgets')->restrictOnDelete();
            $table->foreignUlid('from_line_id')->nullable()->constrained('procurement_budget_lines')->restrictOnDelete();
            $table->foreignUlid('to_line_id')->constrained('procurement_budget_lines')->restrictOnDelete();
            $table->string('change_type', 24);
            $table->bigInteger('amount_minor');
            $table->text('reason');
            $table->string('idempotency_key', 191)->unique();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_budget_changes');
        Schema::dropIfExists('procurement_commitments');
        Schema::dropIfExists('procurement_budget_lines');
        Schema::dropIfExists('procurement_budgets');
        Schema::dropIfExists('procurement_supplier_bank_accounts');
        Schema::dropIfExists('procurement_suppliers');
    }
};
