<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('v2_receipt_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('supplier_id')->constrained('v2_parties');
            $table->date('receipt_date');
            $table->string('supplier_invoice_number', 64)->nullable();
            $table->string('notes', 255)->nullable();
            $table->json('items');
            $table->decimal('total_amount', 19, 2);
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->unique(['created_by', 'idempotency_key']);
            $table->foreignId('receipt_id')->nullable()->unique()->constrained('inventory_receipts');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v2_receipt_drafts');
    }
};
