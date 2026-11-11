<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table): void {
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->text('closure_reason')->nullable();
        });

        Schema::table('procurement_purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('cancelled_quantity', 14, 3)->default(0);
        });

        Schema::create('procurement_order_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->bigInteger('total_minor');
            $table->string('reason', 2000)->nullable();
            $table->date('effective_date');
            $table->ulid('amendment_id')->nullable();
            $table->ulid('created_by');
            $table->timestamp('created_at');
            $table->unique(['purchase_order_id', 'version_number']);
        });

        Schema::create('procurement_order_version_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_version_id')->constrained('procurement_order_versions')->restrictOnDelete();
            $table->ulid('purchase_order_line_id');
            $table->string('description', 240);
            $table->string('account_code', 32);
            $table->jsonb('dimensions')->default('{}');
            $table->decimal('ordered_quantity', 14, 3);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->unique(['order_version_id', 'purchase_order_line_id']);
        });

        Schema::create('procurement_order_amendments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference', 48)->unique();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->unsignedInteger('from_version');
            $table->unsignedInteger('to_version');
            $table->string('status', 24)->default('pending_approval');
            $table->text('reason');
            $table->date('effective_date');
            $table->bigInteger('before_total_minor');
            $table->bigInteger('after_total_minor');
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('procurement_order_amendment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('amendment_id')->constrained('procurement_order_amendments')->restrictOnDelete();
            $table->ulid('purchase_order_line_id');
            $table->decimal('before_quantity', 14, 3);
            $table->decimal('after_quantity', 14, 3);
            $table->bigInteger('before_unit_price_minor');
            $table->bigInteger('after_unit_price_minor');
            $table->bigInteger('before_total_minor');
            $table->bigInteger('after_total_minor');
            $table->unique(['amendment_id', 'purchase_order_line_id']);
        });

        Schema::create('procurement_order_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('status', 32);
            $table->text('reason')->nullable();
            $table->jsonb('details')->default('{}');
            $table->ulid('prepared_by')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['purchase_order_id', 'occurred_at']);
        });

        Schema::create('procurement_order_reopen_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('pending_approval');
            $table->ulid('prepared_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('new_commitment_id')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('procurement_order_commitments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_order_id')->constrained('procurement_purchase_orders')->restrictOnDelete();
            $table->foreignUlid('commitment_id')->constrained('procurement_commitments')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique('commitment_id');
            $table->index(['purchase_order_id', 'status']);
        });
        foreach (DB::table('procurement_purchase_orders as po')
            ->join('procurement_requisitions as r', 'r.id', '=', 'po.requisition_id')
            ->whereNotNull('r.commitment_id')->get(['po.id as purchase_order_id', 'r.commitment_id']) as $link) {
            DB::table('procurement_order_commitments')->insert([
                'id' => (string) Str::ulid(),
                'purchase_order_id' => $link->purchase_order_id,
                'commitment_id' => $link->commitment_id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('procurement_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('document_type', 40);
            $table->string('record_type', 40);
            $table->ulid('record_id');
            $table->string('classification', 24);
            $table->unsignedInteger('current_version')->default(0);
            $table->string('status', 24)->default('active');
            $table->ulid('created_by');
            $table->timestamp('retained_until')->nullable();
            $table->timestamps();
            $table->unique(['document_type', 'record_type', 'record_id']);
            $table->index(['record_type', 'record_id', 'status']);
            $table->index(['document_type', 'status']);
        });

        Schema::create('procurement_document_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_record_id')->constrained('procurement_documents')->restrictOnDelete();
            $table->foreignUlid('document_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('sha256', 64);
            $table->string('scan_status', 24)->default('not_scanned');
            $table->text('replacement_reason')->nullable();
            $table->ulid('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->unique(['document_record_id', 'version_number']);
            $table->unique('document_id');
        });

        $orders = DB::table('procurement_purchase_orders')->orderBy('id')->get();
        foreach ($orders as $order) {
            $versionId = (string) Str::ulid();
            DB::table('procurement_order_versions')->insert([
                'id' => $versionId,
                'purchase_order_id' => $order->id,
                'version_number' => 1,
                'total_minor' => $order->total_minor,
                'effective_date' => substr((string) $order->issued_at, 0, 10),
                'created_by' => $order->issued_by,
                'created_at' => $order->issued_at,
            ]);
            foreach (DB::table('procurement_purchase_order_lines')->where('purchase_order_id', $order->id)->get() as $line) {
                DB::table('procurement_order_version_lines')->insert([
                    'id' => (string) Str::ulid(),
                    'order_version_id' => $versionId,
                    'purchase_order_line_id' => $line->id,
                    'description' => $line->description,
                    'account_code' => $line->account_code,
                    'dimensions' => $line->dimensions,
                    'ordered_quantity' => $line->ordered_quantity,
                    'unit_price_minor' => $line->unit_price_minor,
                    'line_total_minor' => $line->line_total_minor,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_document_versions');
        Schema::dropIfExists('procurement_documents');
        Schema::dropIfExists('procurement_order_commitments');
        Schema::dropIfExists('procurement_order_reopen_requests');
        Schema::dropIfExists('procurement_order_events');
        Schema::dropIfExists('procurement_order_amendment_lines');
        Schema::dropIfExists('procurement_order_amendments');
        Schema::dropIfExists('procurement_order_version_lines');
        Schema::dropIfExists('procurement_order_versions');
        Schema::table('procurement_purchase_order_lines', fn (Blueprint $table) => $table->dropColumn('cancelled_quantity'));
        Schema::table('procurement_purchase_orders', fn (Blueprint $table) => $table->dropColumn(['current_version', 'closed_at', 'closed_by', 'closure_reason']));
    }
};
