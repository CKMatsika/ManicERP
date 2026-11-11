<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_supplier_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->foreignUlid('purchase_order_line_id')->constrained('procurement_purchase_order_lines')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->bigInteger('credit_amount_minor')->default(0);
            $table->string('status', 24)->default('pending_approval');
            $table->text('reason');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('journal_entry_id')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_supplier_returns');
    }
};
