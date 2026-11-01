<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema operasional V2 (tabel v2_*). Aditif: tidak mengubah/menghapus tabel legacy.
 * Uang DECIMAL(19,2), qty/biaya satuan DECIMAL(19,4). Status/pilihan memakai enum
 * agar CHECK ikut terbentuk di MySQL/MariaDB maupun SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->enum('role', ['admin', 'cashier']);
            $table->timestamps();
        });

        Schema::create('v2_parties', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['customer', 'supplier', 'employee']);
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('v2_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Unik selama shift terbuka; NULL setelah tutup (NULL ganda sah di MySQL & SQLite).
            $table->unsignedBigInteger('open_user_id')->nullable()->unique();
            $table->foreign('open_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->enum('source', ['admin', 'carry', 'both']);
            $table->decimal('opening_cash', 19, 2)->default(0);
            $table->decimal('expected_cash', 19, 2)->nullable();
            $table->decimal('counted_cash', 19, 2)->nullable();
            $table->decimal('difference', 19, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'review'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('carried_from_id')->nullable()->unique();
            $table->foreign('carried_from_id')->references('id')->on('v2_shifts')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('v2_sales', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('v2_shifts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('v2_parties')->restrictOnDelete();
            $table->decimal('subtotal', 19, 2)->default(0);
            $table->decimal('line_discount', 19, 2)->default(0);
            $table->decimal('invoice_discount', 19, 2)->default(0);
            $table->decimal('delivery_fee', 19, 2)->default(0);
            $table->decimal('total', 19, 2)->default(0);
            $table->decimal('cogs', 19, 2)->default(0);
            $table->decimal('tendered', 19, 2)->default(0);
            $table->decimal('change_amount', 19, 2)->default(0);
            $table->string('request_hash', 64);
            $table->enum('payment_method', ['cash', 'transfer', 'credit']);
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('fulfillment', ['pickup', 'delivery']);
            $table->string('idempotency_key')->unique();
            $table->timestamps();
            $table->index(['shift_id', 'payment_method']);
        });

        Schema::create('v2_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('v2_sales')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name'); // snapshot nama saat jual
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 2);
            $table->decimal('cost_price', 19, 4)->default(0);
            $table->decimal('discount', 19, 2)->default(0);
            $table->decimal('subtotal', 19, 2);
            $table->timestamps();
            $table->index('sale_id');
        });

        Schema::create('v2_invoices', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['receivable', 'payable']);
            $table->foreignId('party_id')->nullable()->constrained('v2_parties')->restrictOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable()->unique();
            $table->foreign('sale_id')->references('id')->on('v2_sales')->restrictOnDelete();
            $table->unsignedBigInteger('receipt_id')->nullable()->unique();
            $table->foreign('receipt_id')->references('id')->on('inventory_receipts')->restrictOnDelete();
            $table->string('number')->unique();
            $table->decimal('amount', 19, 2);
            $table->decimal('paid', 19, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'paid'])->default('open');
            $table->timestamps();
            $table->index(['direction', 'status']);
        });

        Schema::create('v2_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('v2_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('v2_shifts')->restrictOnDelete();
            $table->decimal('amount', 19, 2);
            $table->enum('method', ['cash', 'transfer']);
            $table->enum('status', ['proposed', 'pending', 'posted', 'rejected'])->default('proposed');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('v2_shift_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('v2_shifts')->restrictOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->enum('kind', ['sale', 'collection', 'supplier', 'transfer_in', 'transfer_out', 'adjustment']);
            $table->decimal('amount', 19, 2);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['reference_type', 'reference_id']);
            $table->index('shift_id');
        });

        Schema::create('v2_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('v2_sales')->restrictOnDelete();
            $table->string('number')->unique();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('destination');
            $table->enum('status', ['prepared', 'sent', 'received'])->default('prepared');
            $table->timestamps();
        });

        Schema::create('v2_trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->foreign('delivery_id')->references('id')->on('v2_deliveries')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('driver_name');
            $table->date('date');
            $table->string('destination');
            $table->decimal('fuel_cost', 19, 2)->default(0);
            $table->decimal('allowance', 19, 2)->default(0);
            $table->decimal('total_cost', 19, 2)->default(0);
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'date']);
        });

        // MariaDB CHECK and SQLite triggers enforce the same numeric invariants.
        foreach ([
            ['v2_shifts', 'chk_v2_shifts_opening_cash', 'opening_cash >= 0'],
            ['v2_shifts', 'chk_v2_shifts_counts', '(expected_cash IS NULL OR expected_cash >= 0) AND (counted_cash IS NULL OR counted_cash >= 0)'],
            ['v2_sales', 'chk_v2_sales_amounts', 'subtotal >= 0 AND line_discount >= 0 AND invoice_discount >= 0 AND delivery_fee >= 0 AND total >= 0 AND cogs >= 0'],
            ['v2_sale_items', 'chk_v2_sale_items_amounts', 'quantity > 0 AND unit_price >= 0 AND cost_price >= 0 AND discount >= 0 AND subtotal >= 0'],
            ['v2_invoices', 'chk_v2_invoices_amounts', 'amount >= 0 AND paid >= 0 AND paid <= amount'],
            ['v2_payments', 'chk_v2_payments_amount', 'amount > 0'],
            ['v2_shift_movements', 'chk_v2_shift_movements_amount', 'amount > 0'],
            ['v2_trips', 'chk_v2_trips_amounts', 'fuel_cost >= 0 AND allowance >= 0 AND total_cost >= 0'],
        ] as [$table, $name, $expression]) {
            $this->check($table, $name, $expression);
        }
    }

    public function down(): void
    {
        // Hanya tabel V2; legacy tidak disentuh.
        foreach ([
            'v2_trips', 'v2_deliveries', 'v2_shift_movements', 'v2_payments',
            'v2_invoices', 'v2_sale_items', 'v2_sales', 'v2_shifts',
            'v2_parties', 'v2_user_roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function check(string $table, string $name, string $expression): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $condition = preg_replace_callback('/\b(opening_cash|expected_cash|counted_cash|subtotal|line_discount|invoice_discount|delivery_fee|total|cogs|quantity|unit_price|cost_price|discount|amount|paid|fuel_cost|allowance|total_cost)\b/', fn ($match) => 'NEW.'.$match[0], $expression);
            foreach (['INSERT', 'UPDATE'] as $event) {
                $trigger = $name.'_'.strtolower($event);
                DB::unprepared("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, '{$name}'); END;");
            }
            return;
        }

        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));
    }
};
