<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique()->nullable();
            $table->string('receipt_type'); // 'opening', 'purchase', 'restock'
            $table->string('credit_account')->default('3101'); // '3101' Modal Awal, '2101' Hutang Usaha, '1101' Kas Toko
            $table->date('receipt_date');
            $table->string('supplier_name')->nullable();
            $table->decimal('total_amount', 19, 2);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_receipt_id')->constrained('inventory_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 2);
            $table->decimal('subtotal', 19, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_receipt_items');
        Schema::dropIfExists('inventory_receipts');
    }
};
