<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCostViewTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_products_index_renders_four_decimal_cost_and_currency_input(): void
    {
        $user = User::create([
            'name' => 'View Tester',
            'email' => 'view_tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        DB::table('products')->insert([
            'sku' => 'VIEW-4DP-01',
            'name' => 'Synthetic Material 4DP',
            'unit' => 'sak',
            'cost_price' => '54321.1234',
            'selling_price' => '65000.00',
            'stock_quantity' => '100.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('54321.1234', (string) DB::table('products')->where('sku', 'VIEW-4DP-01')->value('cost_price'));
        $this->assertSame('54321.1234', (string) DB::table('products')->orderBy('name')->get()->first()->cost_price);
        $response = $this->actingAs($user)->get(route('products.index'));

        $response->assertStatus(200);
        $this->assertSame('54321.1234', (string) DB::table('products')->where('sku', 'VIEW-4DP-01')->value('cost_price'));
        $response->assertSee('VIEW-4DP-01');
        $this->assertSame('54321.1234', (string) $response->viewData('products')->first()->cost_price);
        $this->assertSame('54321.1234', (string) \Brick\Math\BigDecimal::of((string) $response->viewData('products')->first()->cost_price)->toScale(4));
        $response->assertSee('name="cost_price"', false);
        $response->assertSee('data-currency-input', false);
        $response->assertSee('data-currency-scale="4"', false);
        // HPP renders formatted from the exact decimal string; the assertion is independent of responsive cell attributes.
        $response->assertSee('Rp 54.321,1234');
        $response->assertSee('Rp 65.000,00');
    }
}
