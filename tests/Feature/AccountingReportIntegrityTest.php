<?php

namespace Tests\Feature;

use App\Services\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingReportIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private AccountingReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new AccountingReportService();
    }

    public function test_grouping_aggregates_canonically_by_account_code_ignoring_divergent_line_names(): void
    {
        // 1. Seed canonical chart of accounts
        DB::table('chart_of_accounts')->insert([
            'account_code' => '4101',
            'account_name' => 'Pendapatan Penjualan Material',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insert journal entry with divergent account names on the same account code
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 101,
            'description' => 'Penjualan Material Campuran',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $journalId,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => 400000.00,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalId,
                'account_code' => '4101',
                'account_name' => 'Penjualan Nota A (Semen)',
                'debit' => 0.00,
                'credit' => 150000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalId,
                'account_code' => '4101',
                'account_name' => 'Penjualan Nota B (Pasir)',
                'debit' => 0.00,
                'credit' => 250000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $pl = $this->reportService->generateProfitAndLoss();

        // Must aggregate into exactly 1 line for 4101, using canonical name from chart_of_accounts
        $matchingRevenues = array_values(array_filter($pl['revenues'], fn($r) => $r['code'] === '4101'));
        $this->assertCount(1, $matchingRevenues, 'Revenues must aggregate into a single entry per account code');
        $this->assertEquals('Pendapatan Penjualan Material', $matchingRevenues[0]['name'], 'Must resolve canonical account name from chart_of_accounts');
        $this->assertEquals('400000.00', $matchingRevenues[0]['amount'], 'Must aggregate total credits across divergent lines');
    }

    public function test_other_expense_8xxx_included_in_pl_and_reconciles_neraca(): void
    {
        // 1. Initial equity capital: Debit Kas 1,000,000 / Credit Modal 1,000,000
        $j1 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'capital',
            'reference_id' => 1,
            'description' => 'Modal Awal',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $j1,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => 1000000.00,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $j1,
                'account_code' => '3101',
                'account_name' => 'Modal Pemilik',
                'debit' => 0.00,
                'credit' => 1000000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 2. Other expense 8xxx: Debit Beban Administrasi Bank 50,000 / Credit Kas 50,000
        $j2 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'expense',
            'reference_id' => 2,
            'description' => 'Biaya Administrasi Bank Bulanan',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $j2,
                'account_code' => '8101',
                'account_name' => 'Beban Administrasi Bank',
                'debit' => 50000.00,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $j2,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => 0.00,
                'credit' => 50000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $pl = $this->reportService->generateProfitAndLoss();
        $this->assertEquals('-50000.00', $pl['net_profit'], 'Net profit must reflect 8xxx expenses');

        $bs = $this->reportService->generateBalanceSheet('2026-10-21');
        $this->assertTrue($bs['is_balanced'], 'Balance sheet must balance after 8xxx other expense');
        $this->assertEquals('950000.00', $bs['total_assets']);
        $this->assertEquals('-50000.00', $bs['retained_earnings']);
        $this->assertEquals('950000.00', $bs['total_liabilities_and_equity']);
    }

    public function test_other_income_7xxx_included_in_pl_and_reconciles_neraca(): void
    {
        // 1. Initial equity capital: Debit Kas 500,000 / Credit Modal 500,000
        $j1 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'capital',
            'reference_id' => 3,
            'description' => 'Setoran Modal Tambahan',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $j1,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => 500000.00,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $j1,
                'account_code' => '3101',
                'account_name' => 'Modal Pemilik',
                'debit' => 0.00,
                'credit' => 500000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 2. Other income 7xxx: Debit Kas 30,000 / Credit Pendapatan Bunga 30,000
        $j2 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'income',
            'reference_id' => 4,
            'description' => 'Pendapatan Bunga Rekening Koran',
            'transaction_date' => '2026-10-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $j2,
                'account_code' => '1101',
                'account_name' => 'Kas Toko',
                'debit' => 30000.00,
                'credit' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $j2,
                'account_code' => '7101',
                'account_name' => 'Pendapatan Bunga Bank',
                'debit' => 0.00,
                'credit' => 30000.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $pl = $this->reportService->generateProfitAndLoss();
        $this->assertEquals('30000.00', $pl['net_profit'], 'Net profit must reflect 7xxx income');

        $bs = $this->reportService->generateBalanceSheet('2026-10-21');
        $this->assertTrue($bs['is_balanced'], 'Balance sheet must balance after 7xxx other income');
        $this->assertEquals('530000.00', $bs['total_assets']);
        $this->assertEquals('30000.00', $bs['retained_earnings']);
        $this->assertEquals('530000.00', $bs['total_liabilities_and_equity']);
    }

    public function test_invalid_start_date_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reportService->generateProfitAndLoss('invalid-date', '2026-10-21');
    }

    public function test_invalid_end_date_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reportService->generateProfitAndLoss('2026-10-01', '2026-99-99');
    }

    public function test_invalid_as_of_date_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reportService->generateBalanceSheet('malformed-as-of');
    }

    public function test_null_and_empty_dates_remain_optional_and_valid(): void
    {
        $plNull = $this->reportService->generateProfitAndLoss(null, null);
        $this->assertIsArray($plNull);
        $this->assertArrayHasKey('net_profit', $plNull);

        $plEmpty = $this->reportService->generateProfitAndLoss('', '   ');
        $this->assertIsArray($plEmpty);
        $this->assertArrayHasKey('net_profit', $plEmpty);

        $bsNull = $this->reportService->generateBalanceSheet(null);
        $this->assertIsArray($bsNull);
        $this->assertArrayHasKey('is_balanced', $bsNull);

        $bsEmpty = $this->reportService->generateBalanceSheet('  ');
        $this->assertIsArray($bsEmpty);
        $this->assertArrayHasKey('is_balanced', $bsEmpty);
    }
}
