<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_parcels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('parcel_number', 64)->unique();
            $table->string('description', 240)->nullable();
            $table->string('ward', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('coordinate_source', 80)->nullable();
            $table->string('coordinate_accuracy', 40)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_parcels');
    }
};
