<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema V2 untuk Stok Opname, Retur Penjualan, dan Retur Pembelian.
 * Aditif sepenuhnya (tidak menyentuh tabel arsip lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_stock_opnames', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->date('opname_date');
            $table->enum('status', ['posted'])->default('posted');
            $table->decimal('loss_total', 19, 2)->default(0);
            $table->decimal('gain_total', 19, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->index('opname_date');
        });

        Schema::create('v2_stock_opname_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('v2_stock_opnames')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('system_qty', 19, 4);
            $table->decimal('physical_qty', 19, 4);
            $table->decimal('difference_qty', 19, 4);
            $table->decimal('cost_price', 19, 4);
            $table->decimal('value_difference', 19, 2);
            $table->timestamps();
            $table->unique(['stock_opname_id', 'product_id']);
        });

        Schema::create('v2_sale_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->constrained('v2_sales')->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('v2_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('v2_shifts')->restrictOnDelete();
            $table->date('return_date');
            $table->decimal('total_amount', 19, 2);
            $table->decimal('cogs_amount', 19, 2)->default(0);
            $table->decimal('ar_reduction', 19, 2)->default(0);
            $table->decimal('refund_amount', 19, 2)->default(0);
            $table->enum('refund_method', ['credit_adjustment', 'cash', 'main', 'transfer']);
            $table->string('bank_account_code', 20)->nullable();
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->index(['sale_id', 'return_date']);
        });

        Schema::create('v2_sale_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('v2_sale_returns')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained('v2_sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 2);
            $table->decimal('cost_price', 19, 4);
            $table->decimal('subtotal', 19, 2);
            $table->timestamps();
            $table->index('sale_item_id');
        });

        Schema::create('v2_purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('receipt_id')->constrained('inventory_receipts')->restrictOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('v2_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('v2_shifts')->restrictOnDelete();
            $table->date('return_date');
            $table->decimal('total_amount', 19, 2);
            $table->decimal('inventory_credit', 19, 2);
            $table->decimal('ap_reduction', 19, 2)->default(0);
            $table->decimal('refund_amount', 19, 2)->default(0);
            $table->enum('refund_method', ['payable_adjustment', 'cash', 'main', 'transfer']);
            $table->string('bank_account_code', 20)->nullable();
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->index(['receipt_id', 'return_date']);
        });

        Schema::create('v2_purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('v2_purchase_returns')->restrictOnDelete();
            $table->foreignId('receipt_item_id')->constrained('inventory_receipt_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('subtotal', 19, 2);
            $table->timestamps();
            $table->index('receipt_item_id');
        });

        foreach ([
            ['v2_stock_opnames', 'chk_v2_opnames_totals', 'loss_total >= 0 AND gain_total >= 0'],
            ['v2_stock_opname_items', 'chk_v2_opname_items_qty', 'system_qty >= 0 AND physical_qty >= 0 AND cost_price >= 0'],
            ['v2_sale_returns', 'chk_v2_sale_returns_amounts', 'total_amount > 0 AND cogs_amount >= 0 AND ar_reduction >= 0 AND refund_amount >= 0'],
            ['v2_sale_return_items', 'chk_v2_sale_return_items_qty', 'quantity > 0 AND unit_price >= 0 AND cost_price >= 0 AND subtotal >= 0'],
            ['v2_purchase_returns', 'chk_v2_purchase_returns_amounts', 'total_amount > 0 AND inventory_credit >= 0 AND ap_reduction >= 0 AND refund_amount >= 0'],
            ['v2_purchase_return_items', 'chk_v2_purchase_return_items_qty', 'quantity > 0 AND unit_cost >= 0 AND subtotal >= 0'],
        ] as [$table, $name, $expression]) {
            $this->check($table, $name, $expression);
        }
    }

    public function down(): void
    {
        foreach ([
            'v2_purchase_return_items',
            'v2_purchase_returns',
            'v2_sale_return_items',
            'v2_sale_returns',
            'v2_stock_opname_items',
            'v2_stock_opnames',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function check(string $table, string $name, string $expression): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $condition = preg_replace_callback(
                '/\b(loss_total|gain_total|system_qty|physical_qty|cost_price|total_amount|cogs_amount|ar_reduction|refund_amount|quantity|unit_price|subtotal|inventory_credit|ap_reduction|unit_cost)\b/',
                fn (array $match): string => 'NEW.'.$match[0],
                $expression
            );
            foreach (['INSERT', 'UPDATE'] as $event) {
                $trigger = $name.'_'.strtolower($event);
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, '{$name}'); END;");
            }

            return;
        }

        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }
};
