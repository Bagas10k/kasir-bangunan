<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryReceiptService;
use InvalidArgumentException;

class InventoryReceiptTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReceiptService $receiptService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiptService = new InventoryReceiptService();
    }

    public function test_opening_stock_receipt_creates_balanced_journal_and_sets_initial_stock_and_cost(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-SMN-001',
            'name' => 'Material Sintetis A 50kg',
            'unit' => 'sak',
            'cost_price' => 0.00,
            'selling_price' => 75000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => 100,
                    'unit_cost' => 65000.00,
                ],
            ],
            receiptType: 'opening',
            creditAccount: '3101',
            notes: 'Opening inventory injection for store start'
        );

        $this->assertEquals(6500000.00, $result['total_amount']);
        $this->assertStringStartsWith('OPN-STK-', $result['receipt_number']);

        // Assert product stock and cost
        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(100.0000, (float) $product->stock_quantity);
        $this->assertEquals(65000.00, (float) $product->cost_price);

        // Assert stock movement
        $movement = DB::table('stock_movements')->where('product_id', $productId)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(100.0000, (float) $movement->quantity_delta);
        $this->assertEquals(100.0000, (float) $movement->balance_after);
        $this->assertEquals('inventory_receipt', $movement->reference_type);

        // Assert balanced journal entries: Debit 1301 Persediaan, Credit 3101 Modal Awal
        $journalLines = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->get();

        $this->assertCount(2, $journalLines);

        $debitLine = $journalLines->where('account_code', '1301')->first();
        $this->assertNotNull($debitLine);
        $this->assertEquals(6500000.00, (float) $debitLine->debit);
        $this->assertEquals(0.00, (float) $debitLine->credit);

        $creditLine = $journalLines->where('account_code', '3101')->first();
        $this->assertNotNull($creditLine);
        $this->assertEquals(0.00, (float) $creditLine->debit);
        $this->assertEquals(6500000.00, (float) $creditLine->credit);

        // Verify Balance Sheet (Neraca) equilibrium: Asset (1301) = Equity (3101)
        $totalDebit = (float) DB::table('journal_entry_lines')->sum('debit');
        $totalCredit = (float) DB::table('journal_entry_lines')->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(6500000.00, $totalDebit);
    }

    public function test_restock_receipt_with_supplier_credit_and_weighted_average_cost(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-PASIR-001',
            'name' => 'Material Sintetis B 1 Unit',
            'unit' => 'rit',
            'cost_price' => 0.00,
            'selling_price' => 90000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Inject initial opening stock: 100 units @ Rp 65,000 = Rp 6,500,000
        $this->receiptService->receive(
            items: [['product_id' => $productId, 'quantity' => 100, 'unit_cost' => 65000.00]],
            receiptType: 'opening',
            creditAccount: '3101'
        );

        // Restock: 50 units @ Rp 68,000 = Rp 3,400,000 on supplier credit (2101)
        // Weighted average cost: (6,500,000 + 3,400,000) / 150 = 9,900,000 / 150 = 66,000
        $result = $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => 50,
                    'unit_cost' => 68000.00,
                ],
            ],
            receiptType: 'purchase',
            creditAccount: '2101',
            supplierName: 'Entitas Sintetis A'
        );

        $this->assertEquals(3400000.00, $result['total_amount']);

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(150.0000, (float) $product->stock_quantity);
        $this->assertEquals(66000.00, (float) $product->cost_price);

        // Assert journal entry has credit to 2101 (Hutang Usaha)
        $creditLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->where('account_code', '2101')
            ->first();

        $this->assertNotNull($creditLine);
        $this->assertEquals(3400000.00, (float) $creditLine->credit);

        // Verify later sale HPP alignment: sale of 20 units accurately consumes weighted cost price
        $soldQty = 20;
        $expectedHpp = $soldQty * (float) $product->cost_price; // 20 * 66,000 = 1,320,000
        $this->assertEquals(1320000.00, $expectedHpp);

        // Record sale transaction & COGS deduction
        DB::table('products')->where('id', $productId)->decrement('stock_quantity', $soldQty);
        $productAfterSale = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(130.0000, (float) $productAfterSale->stock_quantity);

        // Record COGS journal: Debit HPP (5101), Credit Persediaan (1301)
        $saleJournalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 1,
            'description' => 'HPP Penjualan Material 20 rit',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $saleJournalId,
                'account_code' => '5101',
                'account_name' => 'Beban Pokok Penjualan',
                'debit' => $expectedHpp,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $saleJournalId,
                'account_code' => '1301',
                'account_name' => 'Persediaan Material',
                'debit' => 0.00,
                'credit' => $expectedHpp,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $remainingInventoryValue = (float) DB::table('journal_entry_lines')
            ->where('account_code', '1301')
            ->sum(DB::raw('debit - credit'));

        // Opening (6.5M) + Restock (3.4M) - HPP (1.32M) = 8.58M
        // Stock remaining: 130 * 66,000 = 8.58M
        $this->assertEquals(8580000.00, $remainingInventoryValue);
        $this->assertEquals(8580000.00, $productAfterSale->stock_quantity * $productAfterSale->cost_price);
    }

    public function test_rejects_negative_or_zero_quantity(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-BESI-001',
            'name' => 'Besi Beton 10mm',
            'unit' => 'batang',
            'cost_price' => 50000.00,
            'selling_price' => 60000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => 0,
                    'unit_cost' => 50000.00,
                ],
            ],
            receiptType: 'opening'
        );
    }

    public function test_rejects_negative_or_zero_unit_cost(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-CAT-001',
            'name' => 'Cat Tembok 5kg',
            'unit' => 'kaleng',
            'cost_price' => 70000.00,
            'selling_price' => 85000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => 10,
                    'unit_cost' => -100.00,
                ],
            ],
            receiptType: 'purchase'
        );
    }

    public function test_rejects_opening_stock_when_prior_stock_is_not_zero(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-OPEN-EXISTING',
            'name' => 'Material Prior Stock',
            'unit' => 'zak',
            'cost_price' => 50000.00,
            'selling_price' => 70000.00,
            'stock_quantity' => 15.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Opening stock receipt is only allowed when prior stock is zero');

        $this->receiptService->receive(
            items: [['product_id' => $productId, 'quantity' => 10, 'unit_cost' => 50000.00]],
            receiptType: 'opening',
            creditAccount: '3101'
        );
    }

    public function test_enforces_allowed_receipt_type_and_credit_account_matrix(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-MATRIX-01',
            'name' => 'Material Matrix Test',
            'unit' => 'zak',
            'cost_price' => 0.00,
            'selling_price' => 70000.00,
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Opening with invalid credit account (e.g. 2101 Hutang Usaha) must be rejected
        try {
            $this->receiptService->receive(
                items: [['product_id' => $productId, 'quantity' => 10, 'unit_cost' => 50000.00]],
                receiptType: 'opening',
                creditAccount: '2101'
            );
            $this->fail('Opening receipt with 2101 must fail');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid credit account [2101] for receipt type [opening]', $e->getMessage());
        }

        // Purchase with invalid credit account (e.g. 3101 Modal Awal) must be rejected
        try {
            $this->receiptService->receive(
                items: [['product_id' => $productId, 'quantity' => 10, 'unit_cost' => 50000.00]],
                receiptType: 'purchase',
                creditAccount: '3101'
            );
            $this->fail('Purchase receipt with 3101 must fail');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid credit account [3101] for receipt type [purchase]', $e->getMessage());
        }

        // Invalid receipt type
        try {
            $this->receiptService->receive(
                items: [['product_id' => $productId, 'quantity' => 10, 'unit_cost' => 50000.00]],
                receiptType: 'consignment'
            );
            $this->fail('Invalid receipt type must fail');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid receipt type: consignment', $e->getMessage());
        }
    }
}
