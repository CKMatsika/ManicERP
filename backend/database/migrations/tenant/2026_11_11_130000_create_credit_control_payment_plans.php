<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('plan_number', 48)->unique();
            $table->string('customer_account_id', 40)->index();
            $table->string('user_id', 40)->nullable()->index();
            $table->unsignedBigInteger('total_arrears_minor');
            $table->unsignedBigInteger('interest_waived_minor')->default(0);
            $table->unsignedBigInteger('net_payable_minor');
            $table->unsignedBigInteger('down_payment_minor')->default(0);
            $table->unsignedInteger('installment_count')->default(6);
            $table->unsignedBigInteger('monthly_installment_minor');
            $table->string('currency_code', 3)->default('USD');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('paid_installments')->default(0);
            $table->unsignedBigInteger('total_paid_minor')->default(0);
            $table->boolean('disconnection_immunity')->default(true);
            $table->string('resolution_ref', 60)->default('FC/89/2026');
            $table->string('status', 30)->default('active'); // active, completed, defaulted, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_plan_installments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('payment_plan_id', 40)->index();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_amount_minor')->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->string('status', 24)->default('pending'); // pending, paid, overdue
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};

