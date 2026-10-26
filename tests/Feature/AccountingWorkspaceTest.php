<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseService;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingWorkspaceTest extends TestCase
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
            'name' => 'Synthetic Accounting Operator',
            'email' => 'accounting_' . uniqid() . '@example.test',
            'password' => bcrypt('test-password'),
        ]);
    }

    public function test_opening_balance_is_editable_as_draft_then_posts_one_balanced_immutable_journal(): void
    {
        $service = app(OpeningBalanceService::class);

        $service->saveDraft('2026-10-01', '9000000', '4000000');
        $draft = $service->saveDraft('2026-10-01', '10000000', '5000000');

        $this->assertSame('draft', $draft->status);
        $this->assertEquals('10000000.00', $draft->cash_amount);
        $this->assertEquals('5000000.00', $draft->bank_amount);
        $this->assertDatabaseCount('journal_entries', 0);

        $posted = $service->post();
        $lines = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->orderBy('account_code')
            ->get();

        $this->assertSame('posted', $posted->status);
        $this->assertCount(3, $lines);
        $this->assertEquals(15000000.0, (float) $lines->sum('debit'));
        $this->assertEquals(15000000.0, (float) $lines->sum('credit'));
        $this->assertEquals('10000000.00', $lines->firstWhere('account_code', '1101')->debit);
        $this->assertEquals('5000000.00', $lines->firstWhere('account_code', '1102')->debit);
        $this->assertEquals('15000000.00', $lines->firstWhere('account_code', '3101')->credit);

        $this->expectException(InvalidArgumentException::class);
        $service->saveDraft('2026-10-02', '1', '1');
    }

    public function test_expense_above_cash_balance_is_rejected_without_partial_records(): void
    {
        app(OpeningBalanceService::class)->saveDraft('2026-10-01', '100000', '0');
        app(OpeningBalanceService::class)->post();

        try {
            app(ExpenseService::class)->recordExpense('Operasional Toko', '100000.01', 'Synthetic overspend', 'cash');
            $this->fail('Expected insufficient cash balance to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Saldo Kas', $e->getMessage());
        }

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(100000.0, (float) DB::table('journal_entry_lines')->where('account_code', '1101')->sum('debit'));
    }

    public function test_other_cash_outflows_are_rejected_atomically_when_balance_is_insufficient(): void
    {
        app(OpeningBalanceService::class)->saveDraft('2026-10-01', '100.00', '0.00');
        app(OpeningBalanceService::class)->post();

        $productId = DB::table('products')->insertGetId([
            'sku' => 'SYN-GUARD-01',
            'name' => 'Synthetic Guard Material',
            'unit' => 'unit',
            'cost_price' => '0.0000',
            'selling_price' => '200.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 9000 SYN',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(\App\Services\InventoryReceiptService::class)->receive(
                [['product_id' => $productId, 'quantity' => '1', 'unit_cost' => '100.01']],
                'purchase',
                '1101'
            );
            $this->fail('Expected cash purchase above balance to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Saldo Kas', $e->getMessage());
        }

        try {
            app(\App\Services\RitaseService::class)->recordRitase(
                $vehicleId,
                'Synthetic Driver',
                'Synthetic Zone',
                null,
                '100.01',
                '0'
            );
            $this->fail('Expected ritase above balance to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Saldo Kas', $e->getMessage());
        }

        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('ritases', 0);
        $this->assertEquals(0.0, (float) DB::table('products')->where('id', $productId)->value('stock_quantity'));
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_journal_is_a_separate_authenticated_page_and_feed_lists_account_movements(): void
    {
        $service = app(OpeningBalanceService::class);
        $service->saveDraft('2026-10-01', '10000000', '5000000');
        $posted = $service->post();
        $user = $this->user();

        $this->get('/journals')->assertRedirect('/login');

        $this->actingAs($user)->get(route('journals.index'))
            ->assertOk()
            ->assertSee('Jurnal Umum')
            ->assertSee('Saldo Pembukaan')
            ->assertSee('Kas Toko')
            ->assertDontSee('jrn');

        $this->actingAs($user)->getJson(route('journals.feed', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonPath('entries.0.id', $posted->journal_entry_id)
            ->assertJsonPath('entries.0.reference_type', 'opening_balance')
            ->assertJsonPath('entries.0.is_balanced', true)
            ->assertJsonCount(3, 'entries.0.lines');

        $this->actingAs($user)->getJson(route('journals.feed', ['after_id' => $posted->journal_entry_id]))
            ->assertOk()
            ->assertJsonCount(0, 'entries');
    }
}
