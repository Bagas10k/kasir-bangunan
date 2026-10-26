<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSalesDashboardViewTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_pos_renders_one_combined_sales_card_with_timeline_controls(): void
    {
        $user = User::create([
            'name' => 'Dashboard View Tester',
            'email' => 'dashboard_view_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee('id="sales-dashboard-card"', false);
        $response->assertSee('id="sales-period-control"', false);
        $response->assertSee('data-period="today"', false);
        $response->assertSee('data-period="7d"', false);
        $response->assertSee('data-period="30d"', false);
        $response->assertSee('id="sales-revenue"', false);
        $response->assertSee('id="sales-chart"', false);
        $response->assertDontSee('Penjualan Hari Ini');
        $response->assertDontSee('Tren 7 Hari');
    }
}
