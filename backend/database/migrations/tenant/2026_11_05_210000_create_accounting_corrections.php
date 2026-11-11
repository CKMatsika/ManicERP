<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_corrections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->string('status', 24)->default('pending_approval');
            $table->text('reason');
            $table->ulid('original_journal_entry_id')->nullable();
            $table->ulid('correction_journal_entry_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('accounting_correction_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('correction_id')->constrained('accounting_corrections')->cascadeOnDelete();
            $table->string('account_code', 32);
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->ulid('party_id')->nullable();
            $table->jsonb('dimensions')->default('{}');
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_correction_lines');
        Schema::dropIfExists('accounting_corrections');
    }
};
