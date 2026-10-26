<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosSalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_guest_cannot_access_sales_dashboard(): void
    {
        $response = $this->get(route('pos.dashboard.sales'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_gets_7d_default_when_period_empty_or_invalid(): void
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => 'tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->getJson(route('pos.dashboard.sales', ['period' => 'invalid_period']));
        $response->assertOk();
        $response->assertJsonStructure([
            'period',
            'label',
            'revenue',
            'transaction_count',
            'buckets' => [
                '*' => ['label', 'total'],
            ],
        ]);
        $this->assertSame('7d', $response->json('period'));
        $this->assertCount(7, $response->json('buckets'));
        $this->assertIsNumeric($response->json('revenue'));
        $this->assertIsInt($response->json('transaction_count'));
    }

    public function test_today_period_buckets_and_aggregation(): void
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => 'tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-TODAY-01',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '150000.00',
            'cogs_amount' => '100000.00',
            'paid_amount' => '150000.00',
            'change_amount' => '0.00',
            'created_at' => now()->startOfDay()->addHours(2),
            'updated_at' => now()->startOfDay()->addHours(2),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-YEST-01',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '200000.00',
            'cogs_amount' => '120000.00',
            'paid_amount' => '200000.00',
            'change_amount' => '0.00',
            'created_at' => now()->subDay()->startOfDay()->addHours(5),
            'updated_at' => now()->subDay()->startOfDay()->addHours(5),
        ]);

        $response = $this->actingAs($user)->getJson(route('pos.dashboard.sales', ['period' => 'today']));
        $response->assertOk();
        $this->assertSame('today', $response->json('period'));
        $this->assertEquals(150000.0, (float) $response->json('revenue'));
        $this->assertSame(1, $response->json('transaction_count'));
        $this->assertNotEmpty($response->json('buckets'));
    }

    public function test_7d_period_correctly_aggregates_sales(): void
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => 'tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-001',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '50000.00',
            'cogs_amount' => '30000.00',
            'paid_amount' => '50000.00',
            'change_amount' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-002',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '75000.00',
            'cogs_amount' => '50000.00',
            'paid_amount' => '75000.00',
            'change_amount' => '0.00',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-003',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'total_amount' => '100000.00',
            'cogs_amount' => '60000.00',
            'paid_amount' => '100000.00',
            'change_amount' => '0.00',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)->getJson(route('pos.dashboard.sales', ['period' => '7d']));
        $response->assertOk();
        $this->assertSame('7d', $response->json('period'));
        $this->assertEquals(125000.0, (float) $response->json('revenue'));
        $this->assertSame(2, $response->json('transaction_count'));
        $this->assertCount(7, $response->json('buckets'));

        $bucketSum = array_sum(array_column($response->json('buckets'), 'total'));
        $this->assertEquals(125000.0, (float) $bucketSum);
    }

    public function test_30d_period_returns_30_buckets(): void
    {
        $user = User::create([
            'name' => 'Tester',
            'email' => 'tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->getJson(route('pos.dashboard.sales', ['period' => '30d']));
        $response->assertOk();
        $this->assertSame('30d', $response->json('period'));
        $this->assertCount(30, $response->json('buckets'));
    }
}
