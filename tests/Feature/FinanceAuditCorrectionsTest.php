<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseService;
use App\Services\InventoryReceiptService;
use App\Services\RitaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use Tests\TestCase;

class FinanceAuditCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new \RuntimeException('FinanceAuditCorrectionsTest may only mutate isolated SQLite :memory:.');
        }
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Synthetic Finance Reviewer',
            'email' => 'finance-audit-' . uniqid() . '@example.test',
            'password' => bcrypt('synthetic-test-password'),
        ]);
    }

    public function test_posted_expense_update_is_rejected_without_changing_original_journal(): void
    {
        $this->startBooks();
        $result = app(ExpenseService::class)->recordExpense('Operasional Toko', '50000.00', 'Original');
        $beforeExpense = (array) DB::table('expenses')->where('id', $result['expense_id'])->first();
        $beforeLines = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $result['journal_entry_id'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        try {
            app(ExpenseService::class)->updateExpense($result['expense_id'], 'Listrik & Air', '75000.00', 'Changed', 'transfer');
            $this->fail('Posted expense update should be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('terkunci', strtolower($e->getMessage()));
        }

        $this->assertSame($beforeExpense, (array) DB::table('expenses')->where('id', $result['expense_id'])->first());
        $this->assertSame(
            $beforeLines,
            DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()
        );
    }

    public function test_posted_expense_delete_is_rejected_without_deleting_original_journal(): void
    {
        $this->startBooks();
        $result = app(ExpenseService::class)->recordExpense('Operasional Toko', '50000.00', 'Original');

        try {
            app(ExpenseService::class)->deleteExpense($result['expense_id']);
            $this->fail('Posted expense delete should be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('terkunci', strtolower($e->getMessage()));
        }

        $this->assertTrue(DB::table('expenses')->where('id', $result['expense_id'])->exists());
        $this->assertTrue(DB::table('journal_entries')->where('id', $result['journal_entry_id'])->exists());
        $this->assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->count());
    }

    public function test_ritase_http_path_preserves_exact_cents_beyond_ieee_754_boundary(): void
    {
        $this->startBooks('99999999999999999.99', '0.00');
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN 9000 QA',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user())->postJson(route('ritase.store'), [
            'vehicle_id' => $vehicleId,
            'driver_name' => 'Synthetic Driver',
            'destination' => 'Synthetic Zone',
            'fuel_cost' => '90071992547409.91',
            'driver_allowance' => '0.08',
        ])->assertOk();

        // The HTTP/service boundary must remain exact. SQLite NUMERIC affinity may
        // coerce very large DECIMAL values on readback; MariaDB DECIMAL(19,2) is
        // covered separately by schema/preflight checks.
        $response->assertJsonPath('total_cost', '90071992547409.99');
    }

    public function test_inventory_receipt_result_preserves_exact_decimal_strings(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'SYN-FIN-RCV',
            'name' => 'Synthetic Receipt Material',
            'unit' => 'unit',
            'cost_price' => '0.0000',
            'selling_price' => '1.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(InventoryReceiptService::class)->receive(
            items: [['product_id' => $productId, 'quantity' => '1.0000', 'unit_cost' => '90071992547409.9100']],
            receiptType: 'opening',
            creditAccount: '3101'
        );

        $this->assertSame('90071992547409.91', $result['total_amount']);
        $this->assertSame('1.0000', $result['items'][0]['quantity']);
        $this->assertSame('90071992547409.9100', $result['items'][0]['unit_cost']);
    }

    public function test_cash_balance_surfaces_preserve_decimal_19_2_boundary(): void
    {
        view()->share('errors', new ViewErrorBag());
        $maximum = '9999999999999999.99';

        $expenseHtml = view('expenses.index', [
            'expenses' => collect(),
            'expenseAccounts' => collect(),
            'cashBalance' => $maximum,
            'bankBalance' => '0.00',
        ])->render();
        $journalHtml = view('journals.index', [
            'entries' => collect(),
            'opening' => null,
            'cashOpening' => $maximum,
            'bankOpening' => '0.00',
            'cashBalance' => $maximum,
            'bankBalance' => '0.00',
            'sources' => collect(),
            'filters' => [],
        ])->render();

        foreach ([$expenseHtml, $journalHtml] as $html) {
            $this->assertStringContainsString('9.999.999.999.999.999,99', $html);
            $this->assertStringNotContainsString('10.000.000.000.000.000,00', $html);
        }
    }

    public function test_balance_sheet_view_does_not_add_retained_earnings_twice(): void
    {
        view()->share('errors', new ViewErrorBag());
        $html = view('reports.balance_sheet', [
            'report' => [
                'assets' => [['code' => '1101', 'name' => 'Kas Sintetis', 'amount' => '120.00']],
                'liabilities' => [],
                'equity' => [['code' => '3101', 'name' => 'Modal Sintetis', 'amount' => '100.00']],
                'total_assets' => '120.00',
                'total_liabilities' => '0.00',
                'total_equity' => '120.00',
                'retained_earnings' => '20.00',
                'total_liabilities_and_equity' => '120.00',
                'is_balanced' => true,
            ],
            'asOfDate' => '2026-10-24',
        ])->render();

        $this->assertStringContainsString('Total Ekuitas Termasuk Laba Berjalan', $html);
        $this->assertStringContainsString('120,00', $html);
        $this->assertStringNotContainsString('140,00', $html);
    }

    public function test_database_rejects_direct_changes_to_posted_journal_lines(): void
    {
        $this->startBooks('1000.00', '0.00');
        $lineId = DB::table('journal_entry_lines')->value('id');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('journal_entry_lines')->where('id', $lineId)->update(['debit' => '999.00']);
    }

    public function test_database_rejects_direct_deletion_of_posted_journal_header(): void
    {
        $this->startBooks('1000.00', '0.00');
        $journalId = app(ExpenseService::class)
            ->recordExpense('Operasional Toko', '1.00', 'Synthetic immutability probe')['journal_entry_id'];

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('journal_entries')->where('id', $journalId)->delete();
    }
}
