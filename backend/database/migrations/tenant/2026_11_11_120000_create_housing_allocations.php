<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('land_parcels', function (Blueprint $table): void {
            $table->string('suburb', 80)->nullable()->after('ward');
            $table->string('stand_category', 40)->default('high_density')->after('suburb');
            $table->decimal('area_sqm', 10, 2)->default(300.00)->after('stand_category');
            $table->string('servicing_status', 40)->default('serviced')->after('area_sqm'); // serviced, partially_serviced, unserviced
            $table->unsignedBigInteger('purchase_price_minor')->default(250000)->after('servicing_status'); // e.g. $2,500.00
            $table->unsignedBigInteger('deposit_required_minor')->default(50000)->after('purchase_price_minor'); // e.g. $500.00
            $table->string('currency_code', 3)->default('USD')->after('deposit_required_minor');
            $table->string('allocation_status', 30)->default('available')->after('currency_code'); // available, reserved, allocated
        });

        Schema::create('housing_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('allocation_reference', 48)->unique();
            $table->string('waiting_list_entry_id', 40)->index();
            $table->string('land_parcel_id', 40)->index();
            $table->string('customer_account_id', 40)->nullable()->index();
            $table->string('stand_number', 64);
            $table->string('suburb', 80);
            $table->string('category', 40);
            $table->decimal('area_sqm', 10, 2);
            $table->unsignedBigInteger('purchase_price_minor');
            $table->unsignedBigInteger('deposit_required_minor');
            $table->unsignedBigInteger('deposit_paid_minor')->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->json('development_covenants')->nullable();
            $table->string('allocated_by_user_id', 40)->nullable();
            $table->timestamp('allocated_at');
            $table->timestamp('offer_expires_at');
            $table->string('status', 32)->default('offer_issued'); // offer_issued, accepted, deposit_paid, rejected, agreement_signed, cancelled
            $table->timestamp('accepted_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housing_allocations');

        Schema::table('land_parcels', function (Blueprint $table): void {
            $table->dropColumn([
                'suburb',
                'stand_category',
                'area_sqm',
                'servicing_status',
                'purchase_price_minor',
                'deposit_required_minor',
                'currency_code',
                'allocation_status',
            ]);
        });
    }
};

