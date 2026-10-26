<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyInputFormattingTest extends TestCase
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
            'name' => 'Currency Tester',
            'email' => 'currency_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_money_inputs_use_global_currency_formatter(): void
    {
        $user = $this->user();

        foreach ([
            'products.index' => ['cost_price', 'selling_price'],
            'inventory.receipts' => ['unit_cost'],
            'expenses.index' => ['amount'],
            'ritase.index' => ['fuel_cost', 'driver_allowance'],
        ] as $route => $names) {
            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();

            foreach ($names as $name) {
                $response->assertSee('name="' . $name . '"', false);
            }
            $response->assertSee('data-currency-input', false);
        }
    }

    public function test_non_money_quantity_inputs_are_not_currency_formatted(): void
    {
        $response = $this->actingAs($this->user())->get(route('inventory.receipts'));
        $response->assertOk();
        $response->assertSee('name="quantity"', false);
        $response->assertDontSee('name="quantity" data-currency-input', false);
    }

    public function test_layout_contains_display_and_submit_normalizers(): void
    {
        $response = $this->actingAs($this->user())->get(route('expenses.index'));
        $response->assertOk();
        $response->assertSee('function rawToDisplay', false);
        $response->assertSee('function displayToRaw', false);
        $response->assertSee('window.setCurrencyInput', false);
    }

    public function test_plate_input_uses_global_plate_formatter(): void
    {
        $response = $this->actingAs($this->user())->get(route('vehicles.index'));
        $response->assertOk();
        $response->assertSee('name="license_plate"', false);
        $response->assertSee('data-plate-input', false);
        $response->assertSee('function formatPlate', false);
    }
}