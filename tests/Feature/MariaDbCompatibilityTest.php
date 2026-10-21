<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Services\PosCheckoutService;
use App\Services\InventoryReceiptService;
use App\Services\AccountingReportService;

class MariaDbCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $this->markTestSkipped('MariaDB rehearsal test scoped for MariaDB only. Skipped on SQLite.');
        }

        // Fail-closed guard: refuse execution unless explicitly running against disposable test database
        $dbName = DB::connection()->getDatabaseName();
        if (!str_contains($dbName, 'test') && !str_contains($dbName, 'ephem')) {
            $this->fail("Safety guard: MariaDbCompatibilityTest refused to run against non-disposable database: {$dbName}");
        }
    }

    public function test_mariadb_connection_driver(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->assertContains($driver, ['mysql', 'mariadb']);

        $version = DB::selectOne("SELECT VERSION() as ver")->ver;
        $this->assertNotEmpty($version);
    }

    public function test_synthetic_purchase_sale_report_workflow_on_mariadb(): void
    {
        // 1. Initial product with neutral synthetic branding
        $productId = DB::table('products')->insertGetId([
            'sku' => 'SYN-MAT-001',
            'name' => 'Synthetic Material A 50kg',
            'unit' => 'sak',
            'cost_price' => 0.00,
            'selling_price' => 75000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'sku' => 'SYN-MAT-001',
        ]);

        // 2. Synthetic Purchase / Receipt (unit_cost, neutral supplier)
        $receiptService = new InventoryReceiptService();
        $receipt = $receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => 100,
                    'unit_cost' => 50000.00,
                ]
            ],
            receiptType: 'purchase',
            creditAccount: '2101',
            notes: 'Synthetic material purchase order rehearsal',
            supplierName: 'Synthetic Supplier Ephemeral',
            receiptDate: '2026-10-21'
        );

        $this->assertNotNull($receipt['receipt_id']);
        $productAfterReceipt = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(100.0000, (float)$productAfterReceipt->stock_quantity);
        $this->assertEquals(50000.00, (float)$productAfterReceipt->cost_price);

        // 3. Synthetic Sale / POS Checkout
        $posService = new PosCheckoutService();
        $checkoutResult = $posService->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => 20.0]
            ],
            paymentMethod: 'cash',
            paidAmount: 1500000.00,
            customerType: 'retail'
        );

        $this->assertEquals('1500000.00', $checkoutResult['total_amount']);
        $this->assertEquals('1000000.00', $checkoutResult['cogs_amount']);
        $this->assertEquals('0.00', $checkoutResult['change_amount']);
        $this->assertStringStartsWith('INV-', $checkoutResult['invoice_number']);

        $productAfterSale = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(80.0000, (float)$productAfterSale->stock_quantity);

        // 4. Report & Journal Balance Verification (generateProfitAndLoss, net_profit)
        $reportService = new AccountingReportService();
        $report = $reportService->generateProfitAndLoss('2026-10-01', '2026-10-31');
        $this->assertIsArray($report);
        $this->assertArrayHasKey('net_profit', $report);

        // Verify balanced journal entries in MariaDB
        $lines = DB::table('journal_entry_lines')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $this->assertGreaterThan(0, (float)$lines->total_debit);
        $this->assertEquals((float)$lines->total_debit, (float)$lines->total_credit);
    }
}
