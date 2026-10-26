<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosSalesHistoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_sales_history_json_endpoint_returns_paginated_data(): void
    {
        $user = User::create([
            'name' => 'Sales History Tester',
            'email' => 'sales_history_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        for ($i = 1; $i <= 12; $i++) {
            DB::table('sales')->insert([
                'invoice_number' => sprintf('INV-%06d', $i),
                'customer_type' => 'retail',
                'payment_method' => 'cash',
                'total_amount' => '65000.00',
                'cogs_amount' => '40000.00',
                'paid_amount' => '65000.00',
                'change_amount' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('pos.sales.history', ['per_page' => 10]));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'pagination' => ['current_page', 'last_page', 'from', 'to', 'total', 'next_page_url', 'prev_page_url'],
        ]);
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(12, $response->json('pagination.total'));
        $this->assertSame(1, $response->json('pagination.current_page'));
        $this->assertSame(2, $response->json('pagination.last_page'));
        $this->assertNotNull($response->json('pagination.next_page_url'));
        // Newest (default sort id desc) first.
        $this->assertSame('INV-000012', $response->json('data.0.invoice_number'));
    }
}