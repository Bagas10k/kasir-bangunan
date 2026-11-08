<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2RelationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Migration tests require isolated SQLite :memory:.');
        }
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_upgrade_preserves_labels_and_amounts_and_backfills_only_documented_links(): void
    {
        $migration = require database_path('migrations/2026_11_04_190820_add_v2_master_relations.php');
        $migration->down();
        $product = DB::table('products')->insertGetId([
            'sku' => 'SYN-UPGRADE', 'name' => 'Material Sintetis', 'unit' => 'batang',
            'category' => 'Besi', 'cost_price' => '123.4567', 'selling_price' => '200',
            'stock_quantity' => '2.5000', 'min_stock' => '1', 'is_active' => true,
        ]);
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP', 'name' => 'Supplier Sintetis']);
        $receipt = DB::table('inventory_receipts')->insertGetId([
            'receipt_number' => 'SYN-RCV', 'receipt_type' => 'purchase', 'credit_account' => '2101',
            'receipt_date' => '2026-11-01', 'supplier_name' => 'Nama historis', 'total_amount' => '10',
        ]);
        DB::table('inventory_receipt_items')->insert([
            'inventory_receipt_id' => $receipt, 'product_id' => $product, 'quantity' => '1', 'unit_cost' => '10', 'subtotal' => '10',
        ]);
        DB::table('v2_invoices')->insert([
            'direction' => 'payable', 'party_id' => $supplier, 'receipt_id' => $receipt, 'number' => 'SYN-INV', 'amount' => '10',
        ]);

        $migration->up();

        $unit = DB::table('product_options')->where('type', 'unit')->where('name', 'batang')->value('id');
        $category = DB::table('product_options')->where('type', 'category')->where('name', 'Besi')->value('id');
        $this->assertDatabaseHas('products', [
            'id' => $product, 'unit' => 'batang', 'category' => 'Besi', 'unit_id' => $unit,
            'category_id' => $category, 'cost_price' => '123.4567', 'stock_quantity' => '2.5',
        ]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $product, 'supplier_id' => $supplier, 'is_primary' => false]);
        $this->assertDatabaseHas('inventory_receipts', ['id' => $receipt, 'supplier_name' => 'Nama historis', 'total_amount' => '10']);
        $this->assertDatabaseCount('v2_drivers', 0);
    }

    public function test_database_rejects_two_primary_suppliers_for_one_product(): void
    {
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-PRIMARY', 'name' => 'Material Sintetis', 'unit' => 'sak', 'cost_price' => '0', 'selling_price' => '0']);
        $first = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-S1', 'name' => 'Supplier Satu']);
        $second = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-S2', 'name' => 'Supplier Dua']);
        DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $first, 'is_primary' => true]);

        $this->expectException(QueryException::class);
        DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $second, 'is_primary' => true]);
    }
}
