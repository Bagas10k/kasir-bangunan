<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryReceiptService;
use App\Services\InventoryRestockService;
use Illuminate\Database\QueryException;

class InventoryPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReceiptService $receiptService;
    private InventoryRestockService $restockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiptService = new InventoryReceiptService();
        $this->restockService = new InventoryRestockService($this->receiptService);
    }

    public function test_fractional_quantities_and_stored_precision_4_decimals(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-PASIR-PREC',
            'name' => 'Pasir Cor Super',
            'unit' => 'm3',
            'cost_price' => '0.0000',
            'selling_price' => '350000.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Opening receipt with fractional quantity: 3.1416 m3 @ Rp 275,000.1234
        $result = $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => '3.1416',
                    'unit_cost' => '275000.1234',
                ],
            ],
            receiptType: 'opening',
            creditAccount: '3101'
        );

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('3.1416', $product->stock_quantity);
        $this->assertEquals('275000.1234', $product->cost_price);

        // Subtotal exact = 3.1416 * 275000.1234 = 863940.38767344 -> journal money 2dp: 863940.39
        $receiptItem = DB::table('inventory_receipt_items')->where('inventory_receipt_id', $result['receipt_id'])->first();
        $this->assertEquals('3.1416', $receiptItem->quantity);
        $this->assertEquals('275000.1234', $receiptItem->unit_cost);
        $this->assertEquals('863940.39', $receiptItem->subtotal);

        // Verify balanced journal entry
        $journalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->get();
        $debit = $journalLines->where('account_code', '1301')->first();
        $credit = $journalLines->where('account_code', '3101')->first();
        $this->assertEquals('863940.39', $debit->debit);
        $this->assertEquals('863940.39', $credit->credit);
    }

    public function test_repeating_division_in_moving_weighted_average(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-CAT-REP',
            'name' => 'Cat Tembok Tinting',
            'unit' => 'pail',
            'cost_price' => '0.0000',
            'selling_price' => '250000.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Opening: 1 unit @ 10,000.0000
        $this->receiptService->receive(
            items: [['product_id' => $productId, 'quantity' => '1.0000', 'unit_cost' => '10000.0000']],
            receiptType: 'opening',
            creditAccount: '3101'
        );

        // Purchase: 2 units @ 20,000.0000
        // Total valuation = (1 * 10,000) + (2 * 20,000) = 50,000
        // Total quantity = 3 units
        // Moving weighted average = 50,000 / 3 = 16666.666666... -> HalfUp to 4dp = 16666.6667
        $this->receiptService->receive(
            items: [['product_id' => $productId, 'quantity' => '2.0000', 'unit_cost' => '20000.0000']],
            receiptType: 'purchase',
            creditAccount: '2101'
        );

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('3.0000', $product->stock_quantity);
        $this->assertEquals('16666.6667', $product->cost_price);
    }

    public function test_two_cycle_receipts_with_inventory_restock_service(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-SEMEN-2CYC',
            'name' => 'Semen Gresik 40kg',
            'unit' => 'zak',
            'cost_price' => '0.0000',
            'selling_price' => '70000.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cycle 1: Opening equity injection 100 zak @ 60,000.0000 via restock service
        $c1 = $this->restockService->recordStockIn(
            productId: $productId,
            quantity: '100.0000',
            unitCost: '60000.0000',
            sourceType: 'opening_equity'
        );

        $this->assertEquals('6000000.00', $c1['total_cost']);
        $this->assertEquals('100.0000', $c1['balance_after']);

        $p1 = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('100.0000', $p1->stock_quantity);
        $this->assertEquals('60000.0000', $p1->cost_price);

        // Cycle 2: Purchase 50 zak @ 63,000.5000 on supplier payable
        // (100 * 60,000) + (50 * 63,000.50) = 6,000,000 + 3,150,025 = 9,150,025
        // Total Qty = 150
        // New Cost = 9,150,025 / 150 = 61000.166666... -> 61000.1667
        $c2 = $this->restockService->recordStockIn(
            productId: $productId,
            quantity: '50.0000',
            unitCost: '63000.5000',
            sourceType: 'supplier_payable'
        );

        $p2 = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('150.0000', $p2->stock_quantity);
        $this->assertEquals('61000.1667', $p2->cost_price);
    }

    public function test_sqlite_trigger_persistence_after_schema_migration(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-TRIGGER-TEST',
            'name' => 'Besi Trigger Test',
            'unit' => 'batang',
            'cost_price' => '10000.0000',
            'selling_price' => '15000.00',
            'stock_quantity' => '5.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (DB::getDriverName() === 'sqlite') {
            // Verify trigger blocks negative stock quantity update
            $this->expectException(QueryException::class);
            $this->expectExceptionMessage('CHECK constraint failed: stock_quantity cannot be negative');

            DB::table('products')->where('id', $productId)->update([
                'stock_quantity' => '-1.0000',
            ]);
        } else {
            $this->assertTrue(true);
        }
    }
}
