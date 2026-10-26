<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\PosCheckoutService;
use App\Services\StockOpnameService;
use App\Services\ExpenseService;
use App\Services\RitaseService;
use App\Services\AccountingReportService;
use RuntimeException;

class KasirBangunanFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_kasir_bangunan_verified_workflow(): void
    {
        $this->startBooks();

        // 1. Seed neutral synthetic items (no fake personal identities)
        $productId = DB::table('products')->insertGetId([
            'sku' => 'ITM-NEUTRAL-01',
            'name' => 'Standard Item A 50kg',
            'unit' => 'zak',
            'cost_price' => '50000.00',
            'selling_price' => '70000.00',
            'stock_quantity' => 20.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 20.0000]);

        // 2. POS Checkout with atomic guard & integer cents
        $posService = new PosCheckoutService();
        $checkoutResult = $posService->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => 5.0]
            ],
            paymentMethod: 'cash',
            paidAmount: 400000.00,
            customerType: 'retail'
        );

        $this->assertEquals('350000.00', $checkoutResult['total_amount']);
        $this->assertEquals('250000.00', $checkoutResult['cogs_amount']);
        $this->assertEquals('50000.00', $checkoutResult['change_amount']);
        $this->assertStringStartsWith('INV-', $checkoutResult['invoice_number']);

        // Assert stock decrement
        $productAfter = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(15.0000, (float) $productAfter->stock_quantity);

        // Assert journal entry balanced
        $debitTotal = DB::table('journal_entry_lines')->where('journal_entry_id', $checkoutResult['journal_entry_id'])->sum('debit');
        $creditTotal = DB::table('journal_entry_lines')->where('journal_entry_id', $checkoutResult['journal_entry_id'])->sum('credit');
        $this->assertEquals(number_format($debitTotal, 2, '.', ''), number_format($creditTotal, 2, '.', ''));

        // 3. Atomic stock guard test: Refuse overselling beyond stock
        $refusalCaught = false;
        try {
            $posService->checkout(
                cartItems: [
                    ['product_id' => $productId, 'quantity' => 25.0] // Available is only 15
                ],
                paymentMethod: 'cash',
                paidAmount: 2000000.00
            );
        } catch (RuntimeException $e) {
            $refusalCaught = true;
        }

        $this->assertTrue($refusalCaught, 'Over-selling was not refused');
        // Check stock unchanged
        $productAfterRefusal = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals(15.0000, (float) $productAfterRefusal->stock_quantity);

        // 4. Receipt from DB via Controller HTTP Route (Authenticated synthetic user)
        $user = \App\Models\User::factory()->create();
        $responseReceipt = $this->actingAs($user)->get(route('pos.receipt', ['id' => $checkoutResult['sale_id']]));
        $responseReceipt->assertStatus(200);
        $responseReceipt->assertSee($checkoutResult['invoice_number']);

        // 5. Stock Opname with variance journal
        $opnameService = new StockOpnameService();
        $opnameResult = $opnameService->recordOpname(
            items: [
                ['product_id' => $productId, 'physical_qty' => 14.0] // 1 unit lost (-50,000 cost)
            ],
            notes: 'Physical monthly count'
        );

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => 14.0000]);
        $opDebit = DB::table('journal_entry_lines')->where('journal_entry_id', $opnameResult['journal_entry_id'])->sum('debit');
        $opCredit = DB::table('journal_entry_lines')->where('journal_entry_id', $opnameResult['journal_entry_id'])->sum('credit');
        $this->assertEquals(number_format($opDebit, 2, '.', ''), number_format($opCredit, 2, '.', ''));
        $this->assertEquals('50000.00', number_format($opDebit, 2, '.', ''));

        // 6. Expense recording with balanced journal
        $expenseService = new ExpenseService();
        $expResult = $expenseService->recordExpense(
            category: 'Listrik & Utilitas',
            amount: 25000.00,
            description: 'Biaya listrik operasional'
        );

        $expDebit = DB::table('journal_entry_lines')->where('journal_entry_id', $expResult['journal_entry_id'])->sum('debit');
        $expCredit = DB::table('journal_entry_lines')->where('journal_entry_id', $expResult['journal_entry_id'])->sum('credit');
        $this->assertEquals(number_format($expDebit, 2, '.', ''), number_format($expCredit, 2, '.', ''));
        $this->assertEquals('25000.00', number_format($expDebit, 2, '.', ''));

        // 7. Ritase recording with vehicle & balanced journal
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'TEST-UNIT-01',
            'vehicle_type' => 'pickup',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ritaseService = new RitaseService();
        $ritResult = $ritaseService->recordRitase(
            vehicleId: $vehicleId,
            driverName: 'Driver 01',
            destination: 'Proyek Sektor 4',
            cargoSummary: '5 Zak Material A',
            fuelCost: 30000.00,
            driverAllowance: 20000.00
        );

        $ritDebit = DB::table('journal_entry_lines')->where('journal_entry_id', $ritResult['journal_entry_id'])->sum('debit');
        $ritCredit = DB::table('journal_entry_lines')->where('journal_entry_id', $ritResult['journal_entry_id'])->sum('credit');
        $this->assertEquals(number_format($ritDebit, 2, '.', ''), number_format($ritCredit, 2, '.', ''));
        $this->assertEquals('50000.00', number_format($ritDebit, 2, '.', ''));

        // 8. Financial Reports: Profit & Loss and Balance Sheet
        $reportService = new AccountingReportService();
        $pl = $reportService->generateProfitAndLoss();

        // Revenue: 350,000.00
        // COGS: 250,000.00
        // Stock Loss (COGS 5201): 50,000.00
        // Gross Profit: 350,000 - 300,000 = 50,000.00
        // Operating Expense (6101 Listrik: 25,000 + 6201 Ritase: 50,000) = 75,000.00
        // Net Profit = 50,000 - 75,000 = -25,000.00 (loss)
        $this->assertEquals('350000.00', $pl['total_revenue']);
        $this->assertEquals('-25000.00', $pl['net_profit']);

        $bs = $reportService->generateBalanceSheet();
        // Check Neraca balanced invariant: Assets == Liabilities + Equity
        $this->assertTrue($bs['is_balanced'], 'Balance sheet is not balanced');
        $this->assertEquals($bs['total_assets'], $bs['total_liabilities_and_equity']);

        // Check Web Routes Status 200 with authenticated synthetic user
        $this->actingAs($user)->get(route('pos.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('opname.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('expenses.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('ritase.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('reports.profit_loss'))->assertStatus(200);
        $this->actingAs($user)->get(route('reports.balance_sheet'))->assertStatus(200);
    }
}
