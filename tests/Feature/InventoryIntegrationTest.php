<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryReceiptService;
use App\Services\InventoryRestockService;
use App\Services\PosCheckoutService;
use App\Services\AccountingReportService;
use InvalidArgumentException;
use RuntimeException;

class InventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReceiptService $receiptService;
    private PosCheckoutService $posService;
    private AccountingReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiptService = new InventoryReceiptService();
        $this->posService = new PosCheckoutService();
        $this->reportService = new AccountingReportService();
    }

    private function createSyntheticProduct(
        string $sku,
        string $name,
        float $cost = 0.0,
        float $price = 100000.0,
        float $stock = 0.0
    ): int {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'unit' => 'zak',
            'cost_price' => number_format($cost, 2, '.', ''),
            'selling_price' => number_format($price, 2, '.', ''),
            'stock_quantity' => number_format($stock, 4, '.', ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_actual_checkout_after_receipt_updates_stock_hpp_pnl_neraca(): void
    {
        $productId = $this->createSyntheticProduct('SYN-MAT-01', 'Synthetic Material Alpha', 0.0, 100000.0, 0.0);

        // 1. Initial Opening Stock: 100 zak @ 60,000.00 via Modal Awal (3101) = 6,000,000.00
        $openingResult = $this->receiptService->receive(
            items: [
                ['product_id' => $productId, 'quantity' => 100.0000, 'unit_cost' => 60000.00],
            ],
            receiptType: 'opening',
            creditAccount: '3101',
            notes: 'Opening stock batch'
        );
        $this->assertEquals(6000000.00, $openingResult['total_amount']);

        $productAfterOpening = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('100.0000', $productAfterOpening->stock_quantity);
        $this->assertEquals('60000.00', $productAfterOpening->cost_price);

        // 2. Restock Purchase: 50 zak @ 75,000.00 via Hutang Usaha (2101) = 3,750,000.00
        // Moving Weighted Average = (100 * 60,000 + 50 * 75,000) / 150 = (6,000,000 + 3,750,000) / 150 = 65,000.00
        $restockResult = $this->receiptService->receive(
            items: [
                ['product_id' => $productId, 'quantity' => 50.0000, 'unit_cost' => 75000.00],
            ],
            receiptType: 'purchase',
            creditAccount: '2101',
            notes: 'Restock batch on credit'
        );
        $this->assertEquals(3750000.00, $restockResult['total_amount']);

        $productAfterRestock = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('150.0000', $productAfterRestock->stock_quantity);
        $this->assertEquals('65000.00', $productAfterRestock->cost_price);

        // 3. POS Checkout: sell 30 zak @ 100,000.00 Cash (paid 3,000,000.00)
        // Expected COGS (HPP) = 30 * 65,000.00 = 1,950,000.00
        // Revenue = 3,000,000.00
        // Gross Profit = 1,050,000.00
        $checkoutResult = $this->posService->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => 30.0000],
            ],
            paymentMethod: 'cash',
            paidAmount: 3000000.00
        );

        $this->assertEquals('3000000.00', $checkoutResult['total_amount']);
        $this->assertEquals('1950000.00', $checkoutResult['cogs_amount']);

        // Check remaining stock
        $productAfterSale = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('120.0000', $productAfterSale->stock_quantity);
        $this->assertEquals('65000.00', $productAfterSale->cost_price);

        // 4. Validate P&L (Laba Rugi)
        $pnl = $this->reportService->generateProfitAndLoss();
        $this->assertEquals('3000000.00', $pnl['total_revenue']);
        $this->assertEquals('1950000.00', $pnl['total_cogs']);
        $this->assertEquals('1050000.00', $pnl['gross_profit']);
        $this->assertEquals('1050000.00', $pnl['net_profit']);

        // 5. Validate Neraca (Balance Sheet)
        // Assets: Kas Toko (1101) = 3,000,000.00; Persediaan Material (1301) = 7,800,000.00 (120 * 65,000.00)
        // Total Assets = 10,800,000.00
        // Liabilities: Hutang Usaha (2101) = 3,750,000.00
        // Equity: Modal Awal (3101) = 6,000,000.00 + Net Profit = 1,050,000.00 => 7,050,000.00
        // Total Liabilities & Equity = 10,800,000.00
        $bs = $this->reportService->generateBalanceSheet();
        $this->assertTrue($bs['is_balanced'], 'Balance sheet must balance after receipt and checkout');
        $this->assertEquals('10800000.00', $bs['total_assets']);
        $this->assertEquals('10800000.00', $bs['total_liabilities_and_equity']);
    }

    public function test_malformed_inputs_trigger_complete_database_rollback(): void
    {
        $productId1 = $this->createSyntheticProduct('SYN-ROLLBACK-01', 'Product Rollback 1', 50000.0, 70000.0, 0.0);

        $initialReceiptsCount = DB::table('inventory_receipts')->count();
        $initialItemsCount = DB::table('inventory_receipt_items')->count();
        $initialMovementsCount = DB::table('stock_movements')->count();
        $initialJournalsCount = DB::table('journal_entries')->count();
        $initialLinesCount = DB::table('journal_entry_lines')->count();

        // Attempt receipt with valid item 1, but item 2 has non-existent product ID
        try {
            $this->receiptService->receive(
                items: [
                    ['product_id' => $productId1, 'quantity' => 10.0, 'unit_cost' => 50000.00],
                    ['product_id' => 999999, 'quantity' => 5.0, 'unit_cost' => 60000.00],
                ],
                receiptType: 'opening',
                creditAccount: '3101'
            );
            $this->fail('Expected RuntimeException for missing product was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Product ID 999999 not found', $e->getMessage());
        }

        // Verify full rollback: absolutely zero partial records inserted
        $this->assertEquals($initialReceiptsCount, DB::table('inventory_receipts')->count());
        $this->assertEquals($initialItemsCount, DB::table('inventory_receipt_items')->count());
        $this->assertEquals($initialMovementsCount, DB::table('stock_movements')->count());
        $this->assertEquals($initialJournalsCount, DB::table('journal_entries')->count());
        $this->assertEquals($initialLinesCount, DB::table('journal_entry_lines')->count());

        $product1 = DB::table('products')->where('id', $productId1)->first();
        $this->assertEquals('0.0000', $product1->stock_quantity);
        $this->assertEquals('50000.00', $product1->cost_price);
    }

    public function test_fractional_qty_4dp_and_money_2dp_precision_with_roundoff_reconciliation(): void
    {
        $productId = $this->createSyntheticProduct('SYN-PREC-01', 'Product Fractional Precision', 0.0, 20000.0, 0.0);

        // Receive with 4dp fractional quantity: 12.3456 @ 4567.89
        // Subtotal = round(12.3456 * 4567.89, 2) = 56393.34
        $result = $this->receiptService->receive(
            items: [
                ['product_id' => $productId, 'quantity' => 12.3456, 'unit_cost' => 4567.89],
            ],
            receiptType: 'purchase',
            creditAccount: '1101' // Cash purchase
        );

        $this->assertEquals(56393.34, $result['total_amount']);

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('12.3456', $product->stock_quantity);
        $this->assertEquals('4567.89', $product->cost_price);

        // Verify Journal Debit (1301) == Credit (1101) == 56393.34
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->get();
        $this->assertCount(2, $lines);

        $debitLine = $lines->where('account_code', '1301')->first();
        $creditLine = $lines->where('account_code', '1101')->first();

        $this->assertEquals('56393.34', $debitLine->debit);
        $this->assertEquals('0.00', $debitLine->credit);
        $this->assertEquals('0.00', $creditLine->debit);
        $this->assertEquals('56393.34', $creditLine->credit);
    }

    public function test_adapter_delegation_via_restock_service(): void
    {
        $productId = $this->createSyntheticProduct('SYN-ADAPTER-01', 'Adapter Test Product', 0.0, 50000.0, 0.0);

        $restockService = new InventoryRestockService();

        // 1. Opening via adapter
        $res1 = $restockService->recordStockIn(
            productId: $productId,
            quantity: 25.0000,
            unitCost: 30000.00,
            sourceType: 'opening_equity'
        );
        $this->assertEquals('25.0000', $res1['balance_after']);
        $this->assertEquals('750000.00', $res1['total_cost']);

        // 2. Restock via supplier payable
        // (25 * 30,000 + 25 * 40,000) / 50 = (750,000 + 1,000,000) / 50 = 35,000.00
        $res2 = $restockService->recordStockIn(
            productId: $productId,
            quantity: 25.0000,
            unitCost: 40000.00,
            sourceType: 'supplier_payable'
        );
        $this->assertEquals('50.0000', $res2['balance_after']);
        $this->assertEquals('1000000.00', $res2['total_cost']);

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('50.0000', $product->stock_quantity);
        $this->assertEquals('35000.00', $product->cost_price);
    }
}
