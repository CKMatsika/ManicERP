<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->foreignUlid('matched_supplier_payment_id')->nullable()->constrained('procurement_supplier_payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('matched_supplier_payment_id');
        });
    }
};
