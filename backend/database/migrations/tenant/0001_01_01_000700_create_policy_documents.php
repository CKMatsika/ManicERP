<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('version', 32)->default('1.0');
            $table->string('status', 16)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('source_url', 512)->nullable();
            $table->string('source_authority')->nullable();
            $table->longText('content');
            $table->string('checksum', 64);
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_documents');
    }
};
