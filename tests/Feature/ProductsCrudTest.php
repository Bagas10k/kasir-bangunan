<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Products CRUD Tester',
            'email' => 'products_crud_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function product(array $overrides = []): int
    {
        return DB::table('products')->insertGetId(array_merge([
            'sku' => 'SEM-001',
            'name' => 'Semen Gresik 50kg',
            'unit' => 'sak',
            'cost_price' => '62000.0000',
            'selling_price' => '72000.00',
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function saleFor(int $productId): void
    {
        $saleId = DB::table('sales')->insertGetId([
            'invoice_number' => 'INV-CRUD-' . uniqid(),
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '72000.00',
            'cogs_amount' => '62000.00',
            'paid_amount' => '72000.00',
            'change_amount' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sale_items')->insert([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => 1,
            'unit_price' => '72000.00',
            'subtotal' => '72000.00',
            'cost_price' => '62000.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function receiptFor(int $productId): void
    {
        $receiptId = DB::table('inventory_receipts')->insertGetId([
            'receipt_number' => 'RCV-CRUD-' . uniqid(),
            'receipt_type' => 'purchase',
            'credit_account' => '2101',
            'receipt_date' => now()->toDateString(),
            'supplier_name' => 'Synthetic Supplier',
            'total_amount' => '62000.00',
            'notes' => null,
            'journal_entry_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_receipt_items')->insert([
            'inventory_receipt_id' => $receiptId,
            'product_id' => $productId,
            'quantity' => 10,
            'unit_cost' => '6200.0000',
            'subtotal' => '62000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_update_or_delete_product(): void
    {
        $id = $this->product();

        $this->put(route('products.update', $id), [])->assertRedirect(route('login'));
        $this->delete(route('products.destroy', $id))->assertRedirect(route('login'));
    }

    public function test_update_edits_fields_and_preserves_precision_and_stock(): void
    {
        $id = $this->product();
        DB::table('products')->where('id', $id)->update(['stock_quantity' => 5.0000]);

        $response = $this->actingAs($this->user())->put(route('products.update', $id), [
            'sku' => 'SEM-002',
            'name' => 'Semen Tiga Roda 50kg',
            'unit' => 'zak',
            'cost_price' => '54321.1234',
            'selling_price' => '65500.50',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $id,
            'sku' => 'SEM-002',
            'name' => 'Semen Tiga Roda 50kg',
            'unit' => 'zak',
            'selling_price' => '65500.50',
        ]);
        $row = DB::table('products')->find($id);
        $this->assertEquals('54321.1234', $row->cost_price);
        $this->assertEquals(5.0, (float) $row->stock_quantity);
    }

    public function test_update_rejects_duplicate_sku(): void
    {
        $a = $this->product(['sku' => 'SKU-A']);
        $this->product(['sku' => 'SKU-B']);

        $response = $this->actingAs($this->user())->put(route('products.update', $a), [
            'sku' => 'SKU-B',
            'name' => 'Nama A',
            'unit' => 'pcs',
            'cost_price' => '10.0000',
            'selling_price' => '20.00',
        ]);

        $response->assertSessionHasErrors('sku');
        $this->assertEquals('SKU-A', DB::table('products')->find($a)->sku);
    }

    public function test_destroy_removes_product_without_history(): void
    {
        $id = $this->product();

        $response = $this->actingAs($this->user())->delete(route('products.destroy', $id));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_destroy_refuses_product_with_sale_history(): void
    {
        $id = $this->product();
        $this->saleFor($id);

        $response = $this->actingAs($this->user())->delete(route('products.destroy', $id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $id]);
    }

    public function test_destroy_refuses_product_with_receipt_history(): void
    {
        $id = $this->product();
        $this->receiptFor($id);

        $response = $this->actingAs($this->user())->delete(route('products.destroy', $id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $id]);
    }

    public function test_index_renders_action_column_with_lock_indicator(): void
    {
        $this->product(['sku' => 'CLEAN-1', 'name' => 'Produk Bersih']);
        $lockedId = $this->product(['sku' => 'LOCKED-1', 'name' => 'Produk Riwayat']);
        $this->saleFor($lockedId);

        $response = $this->actingAs($this->user())->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Aksi', false);
        $response->assertSee('btn-action-edit', false);
        $response->assertSee('Terkunci', false);
        $response->assertSee('Hapus', false);
        $response->assertSee('Produk Bersih', false);
        $response->assertSee('Produk Riwayat', false);
    }
}