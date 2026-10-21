<?php

namespace Tests\Feature;

use App\Services\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingLargeAmountPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private AccountingReportService $reportService;

    /**
     * Guard RefreshDatabase to run on SQLite :memory: only.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Refusing to refresh database on non-sqlite or non-in-memory database: {$connection} ({$database})");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new AccountingReportService();

        // Seed standard Chart of Accounts
        DB::table('chart_of_accounts')->insert([
            ['account_code' => '1101', 'account_name' => 'Kas Toko', 'account_type' => 'asset', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '2101', 'account_name' => 'Utang Usaha', 'account_type' => 'liability', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '3101', 'account_name' => 'Modal Disetor', 'account_type' => 'equity', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '4101', 'account_name' => 'Pendapatan Penjualan Material', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '5101', 'account_name' => 'Beban Pokok Material', 'account_type' => 'expense', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Test real DB fixture path with large balanced journal entry in SQLite :memory:.
     */
    public function test_real_db_large_balanced_journal_and_pl_generation(): void
    {
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'opening',
            'reference_id' => 100,
            'description' => 'Large Capital Investment',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 999 billion amount (safe within SQLite IEEE float SUM fidelity)
        $capitalAmount = '999999999999.99';

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $journalId,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => $capitalAmount,
                'credit' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalId,
                'account_code' => '3101',
                'account_name' => 'Modal Disetor',
                'debit' => '0.00',
                'credit' => $capitalAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $bs = $this->reportService->generateBalanceSheet('2026-10-21');

        $this->assertEquals($capitalAmount, $bs['total_assets'], 'Assets must match exact large capital amount');
        $this->assertEquals($capitalAmount, $bs['total_equity'], 'Equity must match exact large capital amount');
        $this->assertTrue($bs['is_balanced'], 'Balance sheet must be balanced');
    }

    /**
     * Test real DB fixture path detects unbalanced journal lines.
     */
    public function test_real_db_detects_unbalanced_journal(): void
    {
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'general',
            'reference_id' => 101,
            'description' => 'Unbalanced Test Entry',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Different amounts by 0.01
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $journalId,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => '100000000000.50',
                'credit' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalId,
                'account_code' => '3101',
                'account_name' => 'Modal Disetor',
                'debit' => '0.00',
                'credit' => '100000000000.51',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $bs = $this->reportService->generateBalanceSheet('2026-10-21');
        $this->assertFalse($bs['is_balanced'], 'Unbalanced journal must report is_balanced = false');
    }

    /**
     * Test DECIMAL(19,2) boundary (9999999999999999.99) via fetch seam.
     * Guarding against PHP 8.5 float cents truncation and false positive is_balanced.
     * In PHP float: (float)'9999999999999999.99' * 100 -> 1000000000000000000
     * In BigDecimal: exact 9999999999999999.99.
     */
    public function test_max_decimal_precision_and_false_positive_prevention_via_seam(): void
    {
        $service = new class extends AccountingReportService {
            public ?Collection $mockBsLines = null;
            public ?Collection $mockPlLines = null;

            protected function fetchBalanceSheetLines(?string $asOfDate = null): Collection
            {
                return $this->mockBsLines ?? parent::fetchBalanceSheetLines($asOfDate);
            }

            protected function fetchProfitAndLossLines(?string $startDate = null, ?string $endDate = null): Collection
            {
                return $this->mockPlLines ?? parent::fetchProfitAndLossLines($startDate, $endDate);
            }
        };

        // Case A: Exact DECIMAL(19,2) 9999999999999999.99 preserves precision without rounding to 10000000000000000.00
        $service->mockBsLines = collect([
            (object)[
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'coa_type' => 'asset',
                'total_debit' => '9999999999999999.99',
                'total_credit' => '0.00',
            ],
            (object)[
                'account_code' => '3101',
                'account_name' => 'Modal Disetor',
                'coa_type' => 'equity',
                'total_debit' => '0.00',
                'total_credit' => '9999999999999999.99',
            ],
        ]);
        $service->mockPlLines = collect([]);

        $bs = $service->generateBalanceSheet('2026-10-21');
        $this->assertEquals('9999999999999999.99', $bs['total_assets'], 'Assets must preserve exact 9999999999999999.99');
        $this->assertEquals('9999999999999999.99', $bs['total_equity'], 'Equity must preserve exact 9999999999999999.99');
        $this->assertTrue($bs['is_balanced'], 'Balance sheet must be balanced');

        // Case B: Unbalanced journal (differing by 0.01) must NOT produce false positive is_balanced=true
        // Float cents would round 9999999999999999.99 and 10000000000000000.00 to the same 1000000000000000000 cents
        $service->mockBsLines = collect([
            (object)[
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'coa_type' => 'asset',
                'total_debit' => '9999999999999999.99',
                'total_credit' => '0.00',
            ],
            (object)[
                'account_code' => '3101',
                'account_name' => 'Modal Disetor',
                'coa_type' => 'equity',
                'total_debit' => '0.00',
                'total_credit' => '10000000000000000.00',
            ],
        ]);

        $bsUnbalanced = $service->generateBalanceSheet('2026-10-21');
        $this->assertFalse($bsUnbalanced['is_balanced'], 'Must not have false positive is_balanced when differing by 0.01');
    }
}
