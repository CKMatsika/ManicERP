<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->string('title', 180);
            $table->text('justification');
            $table->foreignUlid('budget_line_id')->constrained('procurement_budget_lines')->restrictOnDelete();
            $table->foreignUlid('commitment_id')->nullable()->constrained('procurement_commitments')->restrictOnDelete();
            $table->bigInteger('estimated_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 24)->default('draft');
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('procurement_quotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('requisition_id')->constrained('procurement_requisitions')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->string('quote_reference', 100);
            $table->string('currency_code', 3)->default('USD');
            $table->bigInteger('quoted_minor');
            $table->date('valid_until')->nullable();
            $table->text('evaluation_evidence');
            $table->string('status', 24)->default('received');
            $table->ulid('submitted_by');
            $table->timestamps();
            $table->unique(['requisition_id', 'supplier_id', 'quote_reference']);
            $table->index(['requisition_id', 'status']);
        });

        Schema::create('procurement_awards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('requisition_id')->unique()->constrained('procurement_requisitions')->restrictOnDelete();
            $table->foreignUlid('quotation_id')->constrained('procurement_quotations')->restrictOnDelete();
            $table->string('status', 24)->default('pending_approval');
            $table->text('evaluation_summary');
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_purchase_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('requisition_id')->unique()->constrained('procurement_requisitions')->restrictOnDelete();
            $table->foreignUlid('award_id')->constrained('procurement_awards')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('procurement_suppliers')->restrictOnDelete();
            $table->string('currency_code', 3)->default('USD');
            $table->bigInteger('total_minor');
            $table->string('status', 24)->default('approved');
            $table->ulid('issued_by');
            $table->timestamp('issued_at');
            $table->text('cancellation_reason')->nullable();
            $table->ulid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_purchase_order_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->string('description', 240);
            $table->string('account_code', 32);
            $table->jsonb('dimensions')->default('{}');
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('invoiced_quantity', 14, 3)->default(0);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->timestamps();
            $table->index(['purchase_order_id', 'id']);
        });

        Schema::create('procurement_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->date('received_on');
            $table->string('status', 24)->default('accepted');
            $table->text('acceptance_note')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->ulid('accepted_by');
            $table->timestamps();
        });

        Schema::create('procurement_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('receipt_id')->constrained('procurement_receipts')->restrictOnDelete();
            $table->foreignUlid('purchase_order_line_id')->constrained('procurement_purchase_order_lines')->restrictOnDelete();
            $table->decimal('accepted_quantity', 14, 3);
            $table->text('finding')->nullable();
            $table->timestamps();
            $table->unique(['receipt_id', 'purchase_order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_receipt_lines');
        Schema::dropIfExists('procurement_receipts');
        Schema::dropIfExists('procurement_purchase_order_lines');
        Schema::dropIfExists('procurement_purchase_orders');
        Schema::dropIfExists('procurement_awards');
        Schema::dropIfExists('procurement_quotations');
        Schema::dropIfExists('procurement_requisitions');
    }
};
