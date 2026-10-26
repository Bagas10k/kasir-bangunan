<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosCheckoutHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_http_checkout_returns_422_on_oversell_instead_of_500(): void
    {
        $user = User::create([
            'name' => 'Checkout Hardening',
            'email' => 'checkout_hardening_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $productId = DB::table('products')->insertGetId([
            'sku' => 'OVERSell-01',
            'name' => 'Synthetic Oversell Material',
            'unit' => 'sak',
            'cost_price' => '40000.0000',
            'selling_price' => '65000.00',
            'stock_quantity' => '5.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $productId, 'quantity' => '10']],
            'payment_method' => 'cash',
            'paid_amount' => '99999999.00',
            'customer_type' => 'retail',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);

        // Data integrity preserved: stock unchanged, no sale created.
        $this->assertEquals(5.0, (float) DB::table('products')->where('id', $productId)->value('stock_quantity'));
        $this->assertSame(0, DB::table('sales')->count());
    }
}