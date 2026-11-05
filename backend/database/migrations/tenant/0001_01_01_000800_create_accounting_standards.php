<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_standards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32);
            $table->string('title');
            $table->string('edition', 64)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('international_effective_from')->nullable();
            $table->string('jurisdiction', 32)->default('international');
            $table->string('applicability', 32)->default('assessment_required');
            $table->string('transition_method', 64)->nullable();
            $table->jsonb('policy_choices')->nullable();
            $table->string('source_document', 512)->nullable();
            $table->string('source_url', 512)->nullable();
            $table->string('verification_status', 32)->default('unverified');
            $table->ulid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->ulid('approver')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('affected_workflows')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['code', 'edition']);
            $table->index(['applicability', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_standards');
    }
};
