<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_supplier_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->foreignUlid('purchase_order_id')->nullable()->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->foreignUlid('purchase_order_line_id')->nullable()->constrained('procurement_purchase_order_lines')->restrictOnDelete();
            $table->string('expense_account_code', 32);
            $table->string('supplier_invoice_number', 100);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency_code', 3)->default('USD');
            $table->bigInteger('total_minor');
            $table->decimal('quantity', 14, 3)->nullable();
            $table->bigInteger('credited_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->string('status', 24)->default('draft');
            $table->text('match_exception_reason')->nullable();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();
            $table->unique(['supplier_id', 'supplier_invoice_number']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('procurement_invoice_credit_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('invoice_id')->constrained('procurement_supplier_invoices')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->text('reason');
            $table->string('status', 24)->default('pending_approval');
            $table->string('idempotency_key', 191)->unique();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('journal_entry_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_payment_proposals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('invoice_id')->constrained('procurement_supplier_invoices')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('status', 24)->default('draft');
            $table->string('idempotency_key', 191)->unique();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_supplier_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('proposal_id')->unique()->constrained('procurement_payment_proposals')->restrictOnDelete();
            $table->foreignUlid('invoice_id')->constrained('procurement_supplier_invoices')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 24)->default('settled');
            $table->string('settlement_adapter', 32)->default('sandbox');
            $table->string('settlement_reference', 191)->unique();
            $table->string('journal_entry_id', 64)->nullable();
            $table->ulid('settled_by');
            $table->timestamp('settled_at');
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_supplier_payments');
        Schema::dropIfExists('procurement_payment_proposals');
        Schema::dropIfExists('procurement_invoice_credit_notes');
        Schema::dropIfExists('procurement_supplier_invoices');
    }
};
