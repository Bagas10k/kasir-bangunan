<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 4)->change();
        });

        Schema::table('inventory_receipt_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 19, 4)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 4)->change();
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 4)->change();
        });

        // Verify and restore SQLite triggers if dropped during table alteration
        if ($driver === 'sqlite') {
            $triggerUpdate = DB::select("SELECT name FROM sqlite_master WHERE type='trigger' AND name='trg_products_stock_nonnegative_update'");
            if (empty($triggerUpdate)) {
                DB::unprepared("
                    CREATE TRIGGER trg_products_stock_nonnegative_update
                    BEFORE UPDATE OF stock_quantity ON products
                    FOR EACH ROW
                    WHEN NEW.stock_quantity < 0
                    BEGIN
                        SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                    END;
                ");
            }

            $triggerInsert = DB::select("SELECT name FROM sqlite_master WHERE type='trigger' AND name='trg_products_stock_nonnegative_insert'");
            if (empty($triggerInsert)) {
                DB::unprepared("
                    CREATE TRIGGER trg_products_stock_nonnegative_insert
                    BEFORE INSERT ON products
                    FOR EACH ROW
                    WHEN NEW.stock_quantity < 0
                    BEGIN
                        SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                    END;
                ");
            }
        }
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 2)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 2)->change();
        });

        Schema::table('inventory_receipt_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 19, 2)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 19, 2)->change();
        });
    }
};
