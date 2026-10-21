<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalFormsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Synthetic Cashier User',
            'email' => 'cashier.test@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_guest_is_redirected_to_login_for_operational_routes(): void
    {
        $this->get('/products')->assertRedirect('/login');
        $this->post('/products', [])->assertRedirect('/login');

        $this->get('/vehicles')->assertRedirect('/login');
        $this->post('/vehicles', [])->assertRedirect('/login');

        $this->get('/inventory/receipts')->assertRedirect('/login');
        $this->post('/inventory/receipts', [])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_products_and_create_with_zero_initial_stock(): void
    {
        $response = $this->actingAs($this->user)->get('/products');
        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Produk Baru');
        $response->assertSee('stok awal 0.00');

        $storeResponse = $this->actingAs($this->user)->post('/products', [
            'sku' => 'MAT-TEST-001',
            'name' => 'Synthetic Material A',
            'unit' => 'sak',
            'cost_price' => '65000.00',
            'selling_price' => '72000.00',
        ]);

        $storeResponse->assertRedirect(route('products.index'));
        $storeResponse->assertSessionHas('success');

        $product = DB::table('products')->where('sku', 'MAT-TEST-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Synthetic Material A', $product->name);
        $this->assertEquals(0.0000, (float) $product->stock_quantity);
    }

    public function test_product_store_validation_fails_on_duplicate_sku_and_negative_prices(): void
    {
        DB::table('products')->insert([
            'sku' => 'EXISTING-SKU',
            'name' => 'Existing Material',
            'unit' => 'sak',
            'cost_price' => '50000.00',
            'selling_price' => '60000.00',
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dupResponse = $this->actingAs($this->user)->post('/products', [
            'sku' => 'EXISTING-SKU',
            'name' => 'Another Material',
            'unit' => 'sak',
            'cost_price' => '50000.00',
            'selling_price' => '60000.00',
        ]);
        $dupResponse->assertSessionHasErrors(['sku']);

        $negResponse = $this->actingAs($this->user)->post('/products', [
            'sku' => 'NEW-SKU',
            'name' => 'Negative Test',
            'unit' => 'sak',
            'cost_price' => '-1000',
            'selling_price' => '50000.00',
        ]);
        $negResponse->assertSessionHasErrors(['cost_price']);
    }

    public function test_authenticated_user_can_view_vehicles_and_register_neutral_plate(): void
    {
        $response = $this->actingAs($this->user)->get('/vehicles');
        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Armada Kendaraan');

        $storeResponse = $this->actingAs($this->user)->post('/vehicles', [
            'license_plate' => 'TEST-UNIT-01',
            'vehicle_type' => 'truck',
            'status' => 'active',
        ]);

        $storeResponse->assertRedirect(route('vehicles.index'));
        $storeResponse->assertSessionHas('success');

        $vehicle = DB::table('vehicles')->where('license_plate', 'TEST-UNIT-01')->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals('truck', $vehicle->vehicle_type);
        $this->assertEquals('active', $vehicle->status);
    }

    public function test_vehicle_store_validation_rejects_duplicate_license_plate(): void
    {
        DB::table('vehicles')->insert([
            'license_plate' => 'TEST-UNIT-DUP',
            'vehicle_type' => 'pickup',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dupResponse = $this->actingAs($this->user)->post('/vehicles', [
            'license_plate' => 'TEST-UNIT-DUP',
            'vehicle_type' => 'pickup',
            'status' => 'active',
        ]);
        $dupResponse->assertSessionHasErrors(['license_plate']);
    }

    public function test_authenticated_user_can_view_inventory_receipts_form(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-PASIR-01',
            'name' => 'Synthetic Material B',
            'unit' => 'm3',
            'cost_price' => '250000.00',
            'selling_price' => '320000.00',
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/inventory/receipts');
        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang');
        $response->assertSee('MAT-PASIR-01');
    }

    public function test_authenticated_user_can_submit_opening_stock_receipt(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-BESI-10',
            'name' => 'Synthetic Material C',
            'unit' => 'btg',
            'cost_price' => '0.00',
            'selling_price' => '85000.00',
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'opening',
            'credit_account' => '3101',
            'product_id' => $productId,
            'quantity' => '100',
            'unit_cost' => '70000.00',
            'supplier_name' => 'Synthetic Supplier A',
            'receipt_date' => '2026-10-21',
            'notes' => 'Saldo awal stok periode baru',
        ]);

        $response->assertRedirect(route('inventory.receipts'));
        $response->assertSessionHas('success');

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(100.0000, (float) $product->stock_quantity);
        $this->assertEquals(70000.00, (float) $product->cost_price);

        $receipt = DB::table('inventory_receipts')->where('credit_account', '3101')->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('7000000.00', $receipt->total_amount);

        // Check balanced journal entry lines (Debit 1301 = 7000000, Credit 3101 = 7000000)
        $journal = DB::table('journal_entries')->where('id', $receipt->journal_entry_id)->first();
        $this->assertNotNull($journal);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $journal->id)->get();
        $debitLine = $lines->firstWhere('account_code', '1301');
        $creditLine = $lines->firstWhere('account_code', '3101');

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertEquals('7000000.00', $debitLine->debit);
        $this->assertEquals('7000000.00', $creditLine->credit);
    }

    public function test_authenticated_user_can_submit_purchase_receipt_with_weighted_cost_and_credit_account(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-BATU-KALI',
            'name' => 'Synthetic Material D',
            'unit' => 'm3',
            'cost_price' => '200000.00',
            'selling_price' => '260000.00',
            'stock_quantity' => 10.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Existing: 10 m3 @ 200,000 = 2,000,000
        // Incoming: 10 m3 @ 220,000 = 2,200,000
        // Total: 20 m3, Total Value = 4,200,000 -> New average cost = 210,000
        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'purchase',
            'credit_account' => '2101',
            'product_id' => $productId,
            'quantity' => '10',
            'unit_cost' => '220000.00',
            'supplier_name' => 'Synthetic Supplier B',
            'receipt_date' => '2026-10-21',
            'notes' => 'Kulakan tambahan material',
        ]);

        $response->assertRedirect(route('inventory.receipts'));
        $response->assertSessionHas('success');

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(20.0000, (float) $product->stock_quantity);
        $this->assertEquals(210000.00, (float) $product->cost_price);
    }

    public function test_inventory_receipt_validation_rejects_invalid_inputs(): void
    {
        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'invalid_type',
            'credit_account' => '9999',
            'product_id' => 999999,
            'quantity' => 0,
            'unit_cost' => -500,
        ]);

        $response->assertSessionHasErrors(['receipt_type', 'credit_account', 'product_id', 'quantity', 'unit_cost']);
    }
}
