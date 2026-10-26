<?php

namespace Tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySummaryCardsTest extends TestCase
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
            'name' => 'Synthetic Test User',
            'email' => 'synthetic_' . uniqid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_inventory_receipts_summary_cards_aggregates_current_month_correctly(): void
    {
        $user = $this->user();
        $now = Carbon::create(2026, 10, 25, 12, 0, 0);
        Carbon::setTestNow($now);

        $prevMonthDate = Carbon::create(2026, 9, 15, 10, 0, 0)->toDateString();
        $currMonthDate1 = Carbon::create(2026, 10, 2, 9, 0, 0)->toDateString();
        $currMonthDate2 = Carbon::create(2026, 10, 15, 14, 0, 0)->toDateString();
        $currMonthDate3 = Carbon::create(2026, 10, 20, 11, 0, 0)->toDateString();

        // Previous month receipt (MUST be excluded)
        DB::table('inventory_receipts')->insert([
            'receipt_number' => 'RCP-PREV-001',
            'receipt_type' => 'purchase',
            'receipt_date' => $prevMonthDate,
            'total_amount' => '750000.00',
            'created_at' => $prevMonthDate,
            'updated_at' => $prevMonthDate,
        ]);

        // Current month: opening
        DB::table('inventory_receipts')->insert([
            'receipt_number' => 'RCP-CURR-001',
            'receipt_type' => 'opening',
            'receipt_date' => $currMonthDate1,
            'total_amount' => '1000000.00',
            'created_at' => $currMonthDate1,
            'updated_at' => $currMonthDate1,
        ]);

        // Current month: purchase
        DB::table('inventory_receipts')->insert([
            'receipt_number' => 'RCP-CURR-002',
            'receipt_type' => 'purchase',
            'receipt_date' => $currMonthDate2,
            'total_amount' => '250000.50',
            'created_at' => $currMonthDate2,
            'updated_at' => $currMonthDate2,
        ]);

        // Current month: restock
        DB::table('inventory_receipts')->insert([
            'receipt_number' => 'RCP-CURR-003',
            'receipt_type' => 'restock',
            'receipt_date' => $currMonthDate3,
            'total_amount' => '150000.25',
            'created_at' => $currMonthDate3,
            'updated_at' => $currMonthDate3,
        ]);

        $response = $this->actingAs($user)->get('/inventory/receipts');

        $response->assertStatus(200);
        $response->assertViewHas('receiptSummary');

        $summary = $response->viewData('receiptSummary');
        $this->assertEquals(3, $summary['total_receipts']);
        $this->assertEquals(2, $summary['purchase_count']);
        $this->assertEquals(1, $summary['opening_count']);
        $this->assertTrue($summary['total_amount']->isEqualTo(BigDecimal::of('1400000.75')));
        $this->assertNotEmpty($summary['period_label']);

        $content = $response->getContent();
        $this->assertStringContainsString($summary['period_label'], $content);
        $this->assertStringContainsString('1.400.000,75', $content);
    }

    public function test_stock_opname_summary_cards_aggregates_current_month_correctly(): void
    {
        $user = $this->user();
        $now = Carbon::create(2026, 10, 25, 12, 0, 0);
        Carbon::setTestNow($now);

        $prevMonthDate = Carbon::create(2026, 9, 20, 10, 0, 0)->toDateString();
        $currMonthDate1 = Carbon::create(2026, 10, 5, 9, 0, 0)->toDateString();
        $currMonthDate2 = Carbon::create(2026, 10, 18, 14, 0, 0)->toDateString();

        $prodId = DB::table('products')->insertGetId([
            'sku' => 'TEST-SKU-001',
            'name' => 'Synthetic Material Alpha',
            'unit' => 'sak',
            'cost_price' => 50000.00,
            'selling_price' => 65000.00,
            'stock_quantity' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Previous month posted opname (MUST be excluded)
        $prevOpnameId = DB::table('stock_opnames')->insertGetId([
            'opname_number' => 'OPN-PREV-001',
            'opname_date' => $prevMonthDate,
            'status' => 'posted',
            'created_at' => $prevMonthDate,
            'updated_at' => $prevMonthDate,
        ]);
        DB::table('stock_opname_items')->insert([
            'stock_opname_id' => $prevOpnameId,
            'product_id' => $prodId,
            'system_qty' => 10,
            'physical_qty' => 15,
            'difference_qty' => 5,
            'cost_price' => 50000.00,
            'value_difference' => '250000.00',
            'created_at' => $prevMonthDate,
            'updated_at' => $prevMonthDate,
        ]);

        // Current month draft opname (MUST be excluded)
        $draftOpnameId = DB::table('stock_opnames')->insertGetId([
            'opname_number' => 'OPN-DRAFT-001',
            'opname_date' => $currMonthDate1,
            'status' => 'draft',
            'created_at' => $currMonthDate1,
            'updated_at' => $currMonthDate1,
        ]);
        DB::table('stock_opname_items')->insert([
            'stock_opname_id' => $draftOpnameId,
            'product_id' => $prodId,
            'system_qty' => 10,
            'physical_qty' => 12,
            'difference_qty' => 2,
            'cost_price' => 50000.00,
            'value_difference' => '100000.00',
            'created_at' => $currMonthDate1,
            'updated_at' => $currMonthDate1,
        ]);

        // Current month posted opname 1: 2 items
        $currOpnameId1 = DB::table('stock_opnames')->insertGetId([
            'opname_number' => 'OPN-CURR-001',
            'opname_date' => $currMonthDate1,
            'status' => 'posted',
            'created_at' => $currMonthDate1,
            'updated_at' => $currMonthDate1,
        ]);
        DB::table('stock_opname_items')->insert([
            [
                'stock_opname_id' => $currOpnameId1,
                'product_id' => $prodId,
                'system_qty' => 10,
                'physical_qty' => 13,
                'difference_qty' => 3,
                'cost_price' => 50000.00,
                'value_difference' => '150000.50',
                'created_at' => $currMonthDate1,
                'updated_at' => $currMonthDate1,
            ],
            [
                'stock_opname_id' => $currOpnameId1,
                'product_id' => $prodId,
                'system_qty' => 10,
                'physical_qty' => 8,
                'difference_qty' => -2,
                'cost_price' => 50000.00,
                'value_difference' => '-100000.25',
                'created_at' => $currMonthDate1,
                'updated_at' => $currMonthDate1,
            ],
        ]);

        // Current month posted opname 2: 1 item
        $currOpnameId2 = DB::table('stock_opnames')->insertGetId([
            'opname_number' => 'OPN-CURR-002',
            'opname_date' => $currMonthDate2,
            'status' => 'posted',
            'created_at' => $currMonthDate2,
            'updated_at' => $currMonthDate2,
        ]);
        DB::table('stock_opname_items')->insert([
            'stock_opname_id' => $currOpnameId2,
            'product_id' => $prodId,
            'system_qty' => 10,
            'physical_qty' => 9,
            'difference_qty' => -1,
            'cost_price' => 50000.00,
            'value_difference' => '-50000.20',
            'created_at' => $currMonthDate2,
            'updated_at' => $currMonthDate2,
        ]);

        $response = $this->actingAs($user)->get('/stock-opname');

        $response->assertStatus(200);
        $response->assertViewHas('opnameSummary');

        $summary = $response->viewData('opnameSummary');
        $this->assertEquals(2, $summary['posted_count']);
        $this->assertEquals(3, $summary['items_counted']);
        $this->assertTrue($summary['stock_increase_value']->isEqualTo(BigDecimal::of('150000.50')));
        $this->assertTrue($summary['stock_decrease_value']->isEqualTo(BigDecimal::of('150000.45')));
        $this->assertNotEmpty($summary['period_label']);

        $content = $response->getContent();
        $this->assertStringContainsString($summary['period_label'], $content);
        $this->assertStringContainsString('150.000,50', $content);
        $this->assertStringContainsString('150.000,45', $content);
    }
}
