<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->constrained('product_options')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_options')->restrictOnDelete();
        });
        Schema::create('v2_product_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('v2_parties')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_product_id')->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN product_id ELSE NULL END')->unique();
            $table->timestamps();
            $table->unique(['product_id', 'supplier_id']);
        });
        Schema::create('v2_drivers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->enum('kind', ['internal', 'external']);
            $table->foreignId('employee_id')->nullable()->unique()->constrained('v2_parties')->restrictOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (['v2_deliveries', 'v2_trips', 'v2_driver_advances'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('driver_id')->nullable()->constrained('v2_drivers')->restrictOnDelete();
            });
        }

        DB::table('products')->orderBy('id')->chunkById(200, function ($products): void {
            foreach ($products as $product) {
                $relations = [];
                foreach (['unit', 'category'] as $type) {
                    $name = (string) $product->{$type};
                    if (trim($name) === '' || mb_strlen($name) > 50) {
                        continue;
                    }
                    DB::table('product_options')->insertOrIgnore([
                        'type' => $type, 'name' => $name, 'is_active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $relations[$type.'_id'] = DB::table('product_options')->where('type', $type)->where('name', $name)->value('id');
                }
                if ($relations !== []) {
                    DB::table('products')->where('id', $product->id)->update($relations);
                }
            }
        });

        DB::table('v2_invoices as invoices')
            ->join('v2_parties as suppliers', 'suppliers.id', '=', 'invoices.party_id')
            ->join('inventory_receipt_items as items', 'items.inventory_receipt_id', '=', 'invoices.receipt_id')
            ->where('invoices.direction', 'payable')->where('suppliers.kind', 'supplier')
            ->select('items.product_id', 'invoices.party_id as supplier_id')->distinct()
            ->orderBy('items.product_id')->orderBy('invoices.party_id')->chunk(200, function ($links): void {
                foreach ($links as $link) {
                    DB::table('v2_product_suppliers')->insertOrIgnore([
                        'product_id' => $link->product_id, 'supplier_id' => $link->supplier_id,
                        'is_primary' => false, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_products_stock_nonnegative_update
                BEFORE UPDATE OF stock_quantity ON products
                FOR EACH ROW
                WHEN NEW.stock_quantity < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_products_stock_nonnegative_insert
                BEFORE INSERT ON products
                FOR EACH ROW
                WHEN NEW.stock_quantity < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                END;
            ");
        }
    }

    /**
     * Removes new links only; historical labels remain. Prefer forward fixes on populated databases.
     */
    public function down(): void
    {
        foreach (['v2_driver_advances', 'v2_trips', 'v2_deliveries'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('driver_id');
            });
        }
        Schema::dropIfExists('v2_drivers');
        Schema::dropIfExists('v2_product_suppliers');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::table('product_options', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
