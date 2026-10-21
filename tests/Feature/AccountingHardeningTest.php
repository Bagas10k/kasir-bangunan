<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\PosCheckoutService;
use App\Services\StockOpnameService;
use App\Services\ExpenseService;
use App\Services\RitaseService;
use App\Services\InventoryRestockService;
use App\Services\AccountingReportService;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Database\QueryException;

class AccountingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function createSyntheticProduct(string $sku, string $name, float $cost, float $price, float $stock): int
    {
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

    public function test_pos_rejects_invalid_payment_method(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-01', 'Synthetic Test Item 1', 50000.0, 70000.0, 10.0);
        $service = new PosCheckoutService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment method: crypto');

        $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => 1.0]],
            paymentMethod: 'crypto',
            paidAmount: 70000.0
        );
    }

    public function test_pos_rejects_negative_paid_amount(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-02', 'Synthetic Test Item 2', 50000.0, 70000.0, 10.0);
        $service = new PosCheckoutService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid amount must be non-negative');

        $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => 1.0]],
            paymentMethod: 'cash',
            paidAmount: -500.0
        );
    }

    public function test_pos_transfer_payment_debits_bank_account_not_ar(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-03', 'Synthetic Item 3', 50000.0, 80000.0, 10.0);
        $service = new PosCheckoutService();

        $result = $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => 2.0]],
            paymentMethod: 'transfer',
            paidAmount: 160000.0
        );

        $bankLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->where('account_code', '1102')
            ->first();

        $this->assertNotNull($bankLine, 'Bank transfer must debit Kas Bank 1102');
        $this->assertEquals('160000.00', $bankLine->debit);

        $arLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->where('account_code', '1103')
            ->first();

        $this->assertNull($arLine, 'Bank transfer must NOT create Accounts Receivable 1103');
    }

    public function test_pos_credit_payment_with_partial_deposit_records_both_cash_and_ar(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-04', 'Synthetic Item 4', 50000.0, 100000.0, 10.0);
        $service = new PosCheckoutService();

        $result = $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => 1.0]],
            paymentMethod: 'credit',
            paidAmount: 30000.0,
            customerType: 'contractor'
        );

        $depositLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->where('account_code', '1101')
            ->first();

        $this->assertNotNull($depositLine, 'Partial deposit must debit Kas Toko 1101');
        $this->assertEquals('30000.00', $depositLine->debit);

        $arLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])
            ->where('account_code', '1103')
            ->first();

        $this->assertNotNull($arLine, 'Remaining balance must debit Piutang Usaha 1103');
        $this->assertEquals('70000.00', $arLine->debit);

        $debitTotal = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->sum('debit');
        $creditTotal = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->sum('credit');
        $this->assertEquals(number_format($debitTotal, 2, '.', ''), number_format($creditTotal, 2, '.', ''));
    }

    public function test_opname_zero_variance_does_not_insert_orphan_journal(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-OP-0', 'Synthetic Item Opname Zero', 50000.0, 80000.0, 10.0);
        $service = new StockOpnameService();

        $result = $service->recordOpname(
            items: [['product_id' => $productId, 'physical_qty' => 10.0]],
            notes: 'Exact count check'
        );

        $this->assertNull($result['journal_entry_id'], 'Zero variance opname must not create an orphan journal entry');

        $orphanJournals = DB::table('journal_entries')
            ->where('reference_type', 'stock_opname')
            ->where('reference_id', $result['opname_id'])
            ->count();

        $this->assertEquals(0, $orphanJournals, 'No journal entry record should exist for zero variance opname');
    }

    public function test_pos_handles_duplicate_product_in_cart_consistently(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-05', 'Synthetic Item 5', 50000.0, 80000.0, 10.0);
        $service = new PosCheckoutService();

        $result = $service->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => 3.5],
                ['product_id' => $productId, 'quantity' => 2.5],
            ],
            paymentMethod: 'cash',
            paidAmount: 500000.0
        );

        $productAfter = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('4.0000', $productAfter->stock_quantity);
        $this->assertEquals('480000.00', $result['total_amount']);
    }

    public function test_pos_refuses_when_consolidated_duplicate_items_exceed_stock(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-06', 'Synthetic Item 6', 50000.0, 80000.0, 5.0);
        $service = new PosCheckoutService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $service->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => 3.0],
                ['product_id' => $productId, 'quantity' => 3.0],
            ],
            paymentMethod: 'cash',
            paidAmount: 500000.0
        );
    }

    public function test_stock_opname_uses_lock_and_adjusts_atomically(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-07', 'Synthetic Item 7', 50000.0, 80000.0, 10.0);
        $service = new StockOpnameService();

        $result = $service->recordOpname(
            items: [['product_id' => $productId, 'physical_qty' => 8.5]],
            notes: 'Physical audit test'
        );

        $productAfter = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('8.5000', $productAfter->stock_quantity);

        $movement = DB::table('stock_movements')->where('reference_id', $result['opname_id'])->first();
        $this->assertEquals('-1.5000', $movement->quantity_delta);
        $this->assertEquals('8.5000', $movement->balance_after);
    }

    public function test_stock_opname_rejects_negative_physical_qty(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-08', 'Synthetic Item 8', 50000.0, 80000.0, 10.0);
        $service = new StockOpnameService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Physical quantity cannot be negative');

        $service->recordOpname(items: [['product_id' => $productId, 'physical_qty' => -1.0]]);
    }

    public function test_expense_service_validations(): void
    {
        $service = new ExpenseService();

        $this->expectException(InvalidArgumentException::class);
        $service->recordExpense(category: 'Operasional', amount: -5000.0);
    }

    public function test_ritase_service_validations(): void
    {
        $service = new RitaseService();

        $this->expectException(InvalidArgumentException::class);
        $service->recordRitase(
            vehicleId: 1,
            driverName: 'Driver Synthetic',
            destination: 'Lokasi B',
            cargoSummary: 'Material',
            fuelCost: -1000.0
        );
    }

    public function test_nonnegative_stock_db_constraint_trigger(): void
    {
        $productId = $this->createSyntheticProduct('ITM-TEST-09', 'Synthetic Item 9', 50000.0, 80000.0, 5.0);

        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $productId)->update(['stock_quantity' => -1.0]);
    }

    public function test_nonnegative_journal_legs_db_constraint_trigger(): void
    {
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'manual',
            'reference_id' => 99999,
            'description' => 'Test trigger',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $journalId,
            'account_code' => '1101',
            'account_name' => 'Kas',
            'debit' => -100.0,
            'credit' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_journal_entry_reference_uniqueness_constraint(): void
    {
        DB::table('journal_entries')->insert([
            'reference_type' => 'sale',
            'reference_id' => 777,
            'description' => 'Sale Entry 1',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('journal_entries')->insert([
            'reference_type' => 'sale',
            'reference_id' => 777,
            'description' => 'Duplicate Sale Entry',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_inventory_restock_service_creates_verified_opening_equity_journal(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'ITM-RESTOCK-01',
            'name' => 'Synthetic Cement 50kg',
            'unit' => 'zak',
            'cost_price' => '50000.00',
            'selling_price' => '70000.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $restockService = new InventoryRestockService();
        $restockResult = $restockService->recordStockIn(
            productId: $productId,
            quantity: 20.0,
            unitCost: 50000.0,
            sourceType: 'opening_equity',
            notes: 'Opening inventory balance'
        );

        $this->assertEquals('20.0000', $restockResult['balance_after']);
        $this->assertEquals('1000000.00', $restockResult['total_cost']);

        $invLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $restockResult['journal_entry_id'])
            ->where('account_code', '1301')
            ->first();
        $this->assertEquals('1000000.00', $invLine->debit);

        $equityLine = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $restockResult['journal_entry_id'])
            ->where('account_code', '3101')
            ->first();
        $this->assertEquals('1000000.00', $equityLine->credit);

        $posService = new PosCheckoutService();
        $posResult = $posService->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => 5.0]],
            paymentMethod: 'cash',
            paidAmount: 400000.0
        );

        $reportService = new AccountingReportService();
        $bs = $reportService->generateBalanceSheet();

        $this->assertTrue($bs['is_balanced'], 'Balance sheet must balance');
        $this->assertEquals('1100000.00', $bs['total_assets']);
        $this->assertEquals('1100000.00', $bs['total_liabilities_and_equity']);
    }
}
