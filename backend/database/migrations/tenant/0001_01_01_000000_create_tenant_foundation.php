<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('chain_index')->unique();
            $table->timestamp('occurred_at');
            $table->ulid('actor_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 64)->nullable();
            $table->string('action', 120);
            $table->string('module', 64)->nullable();
            $table->string('subject_type', 191)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('occurred_at');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('original_name');
            $table->string('path', 512);
            $table->string('disk', 64)->default('documents');
            $table->string('mime_type', 191);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('classification', 24)->default('internal');
            $table->string('scan_status', 24)->default('not_scanned');
            $table->string('status', 24)->default('available');
            $table->string('documentable_type', 191)->nullable();
            $table->string('documentable_id', 64)->nullable();
            $table->ulid('uploaded_by')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('sha256');
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('prefix', 32)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedSmallInteger('padding')->default(6);
            $table->string('period_key', 16)->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 120)->primary();
            $table->jsonb('value')->nullable();
            $table->string('group', 64)->default('general');
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulidMorphs('notifiable');
            $table->string('type');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('audit_events');
    }
};
