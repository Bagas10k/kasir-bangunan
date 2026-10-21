<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('unit');
            $table->decimal('cost_price', 19, 2);
            $table->decimal('selling_price', 19, 2);
            $table->decimal('stock_quantity', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->nullable();
            $table->string('customer_type'); // 'retail', 'contractor'
            $table->string('payment_method'); // 'cash', 'transfer', 'credit'
            $table->decimal('total_amount', 19, 2);
            $table->decimal('cogs_amount', 19, 2);
            $table->decimal('paid_amount', 19, 2);
            $table->decimal('change_amount', 19, 2);
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 2);
            $table->decimal('subtotal', 19, 2);
            $table->decimal('cost_price', 19, 2);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('reference_type'); // 'sale', 'stock_opname', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity_delta', 19, 4);
            $table->decimal('balance_after', 19, 4);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique()->nullable();
            $table->date('opname_date');
            $table->string('status')->default('posted'); // 'draft', 'posted'
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('system_qty', 19, 4);
            $table->decimal('physical_qty', 19, 4);
            $table->decimal('difference_qty', 19, 4);
            $table->decimal('cost_price', 19, 2);
            $table->decimal('value_difference', 19, 2);
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->string('account_type'); // 'asset', 'liability', 'equity', 'revenue', 'expense'
            $table->string('normal_balance'); // 'debit', 'credit'
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // 'sale', 'stock_opname', 'expense', 'ritase'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description');
            $table->date('transaction_date');
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->string('account_code');
            $table->string('account_name');
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('credit', 19, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique()->nullable();
            $table->date('expense_date');
            $table->string('category');
            $table->decimal('amount', 19, 2);
            $table->string('payment_method')->default('cash');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate')->unique();
            $table->string('vehicle_type'); // 'truck', 'pickup'
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('ritases', function (Blueprint $table) {
            $table->id();
            $table->string('ritase_number')->unique()->nullable();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->string('driver_name');
            $table->date('ritase_date');
            $table->string('destination');
            $table->string('cargo_summary')->nullable();
            $table->decimal('fuel_cost', 19, 2)->default(0);
            $table->decimal('driver_allowance', 19, 2)->default(0);
            $table->decimal('total_cost', 19, 2)->default(0);
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ritases');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
    }
};
