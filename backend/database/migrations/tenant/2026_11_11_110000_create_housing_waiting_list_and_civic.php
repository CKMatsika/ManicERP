<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housing_waiting_list_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('waiting_list_number', 48)->unique();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('customer_account_id', 40)->nullable()->index();
            $table->string('national_id', 40)->index();
            $table->string('full_name', 160);
            $table->string('phone_number', 40);
            $table->string('email', 120)->nullable();
            $table->string('category', 40)->default('high_density'); // high_density, medium_density, low_density, commercial, industrial
            $table->json('preferred_suburbs')->nullable();
            $table->string('marital_status', 30)->default('single');
            $table->unsignedInteger('number_of_dependents')->default(0);
            $table->string('employment_status', 50)->default('employed');
            $table->unsignedBigInteger('monthly_income_minor')->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedInteger('priority_points')->default(10);
            $table->string('status', 30)->default('active'); // pending_review, active, renewal_due, allocated, lapsed
            $table->date('application_date');
            $table->date('expiry_date');
            $table->timestamp('last_renewed_at')->nullable();
            $table->string('allocated_stand_number', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('civic_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 32)->index(); // news, event, resolution, newsletter
            $table->string('title', 200);
            $table->string('category', 60)->nullable(); // e.g. Budget Consultation, Water & Sanitation, Council Minutes
            $table->text('summary');
            $table->longText('content')->nullable();
            $table->string('author_role', 100)->nullable(); // e.g. Town Clerk, Chamber Secretary, Mayor
            $table->date('published_date');
            $table->date('event_date')->nullable(); // For events/consultations
            $table->string('venue', 160)->nullable();
            $table->string('download_url', 255)->nullable(); // For PDF newsletters / gazettes
            $table->string('resolution_number', 60)->nullable(); // e.g. FC/RES/2026/042
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_items');
        Schema::dropIfExists('housing_waiting_list_entries');
    }
};

