<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Brick\Math\BigDecimal;

class ExpensesSummaryCardsTest extends TestCase
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

    public function test_expenses_summary_cards_aggregate_all_current_month_records_and_exclude_other_months(): void
    {
        $user = $this->user();

        $now = Carbon::now();
        $currentMonthDate1 = $now->copy()->startOfMonth()->toDateString();
        $currentMonthDate2 = $now->copy()->endOfMonth()->toDateString();
        $currentMonthDateMid = $now->copy()->startOfMonth()->addDays(5)->toDateString();

        $prevMonthDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $nextMonthDate = $now->copy()->addMonth()->startOfMonth()->toDateString();

        // 12 records in current month (more than the 10 rows displayed in latest table)
        // 7 cash transactions
        $cashAmounts = [
            '10000.50',
            '20000.25',
            '30000.00',
            '40000.75',
            '50000.00',
            '60000.50',
            '70000.00',
        ];

        // 5 transfer transactions
        $transferAmounts = [
            '100000.00',
            '150000.50',
            '200000.25',
            '250000.75',
            '300000.00',
        ];

        $expectedCash = BigDecimal::zero();
        foreach ($cashAmounts as $idx => $amt) {
            $expectedCash = $expectedCash->plus(BigDecimal::of($amt));
            DB::table('expenses')->insert([
                'expense_number' => 'EXP-CURR-CASH-' . ($idx + 1),
                'expense_date' => ($idx % 2 === 0) ? $currentMonthDate1 : $currentMonthDateMid,
                'category' => '6101 - Operasional Toko',
                'amount' => $amt,
                'payment_method' => 'cash',
                'description' => 'Current month cash expense ' . ($idx + 1),
                'journal_entry_id' => ($idx % 3 === 0) ? 999 : null, // Test journal_entry_id doesn't cause double counting
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $expectedTransfer = BigDecimal::zero();
        foreach ($transferAmounts as $idx => $amt) {
            $expectedTransfer = $expectedTransfer->plus(BigDecimal::of($amt));
            DB::table('expenses')->insert([
                'expense_number' => 'EXP-CURR-TRF-' . ($idx + 1),
                'expense_date' => ($idx % 2 === 0) ? $currentMonthDate2 : $currentMonthDateMid,
                'category' => '6102 - Listrik & Air',
                'amount' => $amt,
                'payment_method' => 'transfer',
                'description' => 'Current month transfer expense ' . ($idx + 1),
                'journal_entry_id' => 1000 + $idx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $expectedTotal = $expectedCash->plus($expectedTransfer);

        // Previous month expenses (must be ignored in summary)
        DB::table('expenses')->insert([
            'expense_number' => 'EXP-PREV-1',
            'expense_date' => $prevMonthDate,
            'category' => '6103 - Pemeliharaan & Perbaikan',
            'amount' => '9999999.00',
            'payment_method' => 'cash',
            'description' => 'Previous month expense',
            'journal_entry_id' => null,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        // Next month expenses (must be ignored in summary)
        DB::table('expenses')->insert([
            'expense_number' => 'EXP-NEXT-1',
            'expense_date' => $nextMonthDate,
            'category' => '6104 - Perlengkapan & ATK',
            'amount' => '8888888.00',
            'payment_method' => 'transfer',
            'description' => 'Next month expense',
            'journal_entry_id' => null,
            'created_at' => now()->addMonth(),
            'updated_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertStatus(200);

        // Verify summary data passed to view
        $response->assertViewHas('expenseSummary');
        $summary = $response->viewData('expenseSummary');

        $this->assertEquals(12, $summary['count']);
        $this->assertEquals((string) $expectedTotal, (string) $summary['total']);
        $this->assertEquals((string) $expectedCash, (string) $summary['cash']);
        $this->assertEquals((string) $expectedTransfer, (string) $summary['transfer']);

        // Format money helper matching the view style
        $formatMoney = function ($value) {
            $decimal = BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
            $negative = $decimal->isNegative();
            [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
            $formatted = preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', '.', $whole) . ',' . $fraction;
            return $negative ? '-' . $formatted : $formatted;
        };

        // Verify UI output
        $response->assertSee('Ringkasan Pengeluaran');
        $response->assertSee('12'); // Total count
        $response->assertSee('Rp ' . $formatMoney($expectedTotal)); // Total money
        $response->assertSee('Rp ' . $formatMoney($expectedCash)); // Cash
        $response->assertSee('Rp ' . $formatMoney($expectedTransfer)); // Transfer

        // Ensure previous month and next month amounts do NOT appear in the UI
        $response->assertDontSee('9.999.999,00');
        // Verified summary excludes previous and next month via mathematical equality asserts on summary['total'], summary['cash'], summary['transfer']
    }
}
