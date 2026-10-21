<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductFourDecimalCostTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function createCashierUser(): User
    {
        return User::create([
            'name' => 'Cashier TDD',
            'email' => 'cashier_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_guard_rejects_non_memory_database(): void
    {
        $orig = config('database.default');
        try {
            config(['database.default' => 'non_memory_guard']);
            $this->expectException(\RuntimeException::class);
            $this->beforeRefreshingDatabase();
        } finally {
            config(['database.default' => $orig]);
        }
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/products', [
            'sku' => 'MAT-UNAUTH-001',
            'name' => 'Unauthorized Item',
            'unit' => 'pcs',
            'cost_price' => '10.0000',
            'selling_price' => '20.00',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_product_with_4_decimal_cost_price(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->post('/products', [
            'sku' => 'MAT-4DP-001',
            'name' => 'Baut Baja 4DP',
            'unit' => 'pcs',
            'cost_price' => '1234.5678',
            'selling_price' => '2500.00',
        ]);

        $response->assertRedirect(route('products.index'));

        $product = DB::table('products')->where('sku', 'MAT-4DP-001')->first();
        $this->assertNotNull($product);
        $this->assertSame('1234.5678', (string) $product->cost_price);
        $this->assertEquals(2500.00, (float) $product->selling_price);
        $this->assertEquals(0.0000, (float) $product->stock_quantity);
    }

    public function test_json_request_stores_exact_4_decimal_cost_without_float_distortion(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->postJson('/products', [
            'sku' => 'MAT-4DP-002',
            'name' => 'Paku Beton 4DP',
            'unit' => 'kg',
            'cost_price' => '8765.4321',
            'selling_price' => '12000.50',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $product = DB::table('products')->where('sku', 'MAT-4DP-002')->first();
        $this->assertNotNull($product);
        $this->assertSame('8765.4321', (string) $product->cost_price);
    }

    public function test_cost_price_pads_shorter_decimals_to_exact_four_decimal_places(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->postJson('/products', [
            'sku' => 'MAT-4DP-003',
            'name' => 'Cat Tembok',
            'unit' => 'kaleng',
            'cost_price' => '50000.5',
            'selling_price' => '65000.00',
        ]);

        $response->assertStatus(201);

        $product = DB::table('products')->where('sku', 'MAT-4DP-003')->first();
        $this->assertNotNull($product);
        $this->assertEquals(50000.5000, (float) $product->cost_price);
    }

    public function test_cost_price_rejects_overprecision_more_than_4_decimals(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->postJson('/products', [
            'sku' => 'MAT-OVER-001',
            'name' => 'Overprecision Item',
            'unit' => 'pcs',
            'cost_price' => '12.34567',
            'selling_price' => '20.00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cost_price']);
    }

    public function test_cost_price_rejects_negative_value(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->postJson('/products', [
            'sku' => 'MAT-NEG-001',
            'name' => 'Negative Item',
            'unit' => 'pcs',
            'cost_price' => '-15.0000',
            'selling_price' => '20.00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cost_price']);
    }

    public function test_selling_price_strictly_rejects_more_than_2_decimals(): void
    {
        $user = $this->createCashierUser();

        $response = $this->actingAs($user)->postJson('/products', [
            'sku' => 'MAT-SP-FAIL',
            'name' => 'Overprecision Selling Item',
            'unit' => 'pcs',
            'cost_price' => '10.5000',
            'selling_price' => '20.555',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['selling_price']);
    }
}
