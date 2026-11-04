<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    private AccountingReportService $reportService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new AccountingReportService;

        // Seed core COA accounts
        $accounts = [
            ['account_code' => '1101', 'account_name' => 'Kas Toko (Tunai)', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1102', 'account_name' => 'Rekening Bank', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1103', 'account_name' => 'Piutang Usaha', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1301', 'account_name' => 'Persediaan Material Bangunan', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '2101', 'account_name' => 'Utang Usaha', 'account_type' => 'liability', 'normal_balance' => 'credit'],
            ['account_code' => '3101', 'account_name' => 'Modal Pemilik', 'account_type' => 'equity', 'normal_balance' => 'credit'],
            ['account_code' => '4101', 'account_name' => 'Pendapatan Penjualan Material', 'account_type' => 'revenue', 'normal_balance' => 'credit'],
            ['account_code' => '5101', 'account_name' => 'Beban Pokok Penjualan (HPP)', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6101', 'account_name' => 'Beban Operasional Toko', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6201', 'account_name' => 'Beban Transportasi & Ritase', 'account_type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($accounts as $acc) {
            DB::table('chart_of_accounts')->insert(array_merge($acc, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->user = User::factory()->create([
            'email' => 'cashier@test.com',
            'is_active' => true,
        ]);
    }

    public function test_empty_database_returns_zero_balances_and_is_reconciled(): void
    {
        $report = $this->reportService->generateCashFlow('2026-10-01', '2026-10-31');

        $this->assertSame('0.00', $report['beginning_cash']);
        $this->assertSame('0.00', $report['net_cash_change']);
        $this->assertSame('0.00', $report['ending_cash']);
        $this->assertTrue($report['is_reconciled']);
        $this->assertSame('0.00', $report['operating_activities']['total_inflow']);
        $this->assertSame('0.00', $report['operating_activities']['total_outflow']);
    }

    public function test_direct_cash_flow_classifies_operational_and_financing_correctly(): void
    {
        // 1. Initial capital deposit: 10,000,000 to Bank (Financing)
        $j1 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'opening_balance',
            'reference_id' => 1,
            'description' => 'Setoran Modal Awal',
            'transaction_date' => '2026-10-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j1, 'account_code' => '1102', 'account_name' => 'Rekening Bank', 'debit' => 10000000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j1, 'account_code' => '3101', 'account_name' => 'Modal Pemilik', 'debit' => 0.00, 'credit' => 10000000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Cash Sale: 500,000 to Kas Toko (Operating Inflow)
        $j2 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 101,
            'description' => 'Penjualan POS Tunai',
            'transaction_date' => '2026-10-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j2, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => 500000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j2, 'account_code' => '4101', 'account_name' => 'Pendapatan Penjualan', 'debit' => 0.00, 'credit' => 500000.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j2, 'account_code' => '5101', 'account_name' => 'HPP', 'debit' => 350000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j2, 'account_code' => '1301', 'account_name' => 'Persediaan', 'debit' => 0.00, 'credit' => 350000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Operating Expense: 100,000 from Kas Toko (Operating Outflow)
        $j3 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'expense',
            'reference_id' => 201,
            'description' => 'Biaya Listrik',
            'transaction_date' => '2026-10-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j3, 'account_code' => '6101', 'account_name' => 'Beban Operasional Toko', 'debit' => 100000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j3, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => 0.00, 'credit' => 100000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 4. Internal Cash Transfer: 200,000 from Kas Toko (1101) to Bank (1102) (Net zero change)
        $j4 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'adjustment',
            'reference_id' => 301,
            'description' => 'Setoran Kas Toko ke Bank',
            'transaction_date' => '2026-10-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j4, 'account_code' => '1102', 'account_name' => 'Rekening Bank', 'debit' => 200000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j4, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => 0.00, 'credit' => 200000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Generate report for full October
        $report = $this->reportService->generateCashFlow('2026-10-01', '2026-10-31');

        $this->assertSame('0.00', $report['beginning_cash']);
        $this->assertSame('500000.00', $report['operating_activities']['total_inflow']);
        $this->assertSame('100000.00', $report['operating_activities']['total_outflow']);
        $this->assertSame('400000.00', $report['operating_activities']['net_cash']);

        $this->assertSame('10000000.00', $report['financing_activities']['total_inflow']);
        $this->assertSame('0.00', $report['financing_activities']['total_outflow']);
        $this->assertSame('10000000.00', $report['financing_activities']['net_cash']);

        $this->assertSame('10400000.00', $report['net_cash_change']);
        $this->assertSame('10400000.00', $report['ending_cash']);
        $this->assertTrue($report['is_reconciled']);

        // Check breakdown: 1101 has 500k - 100k - 200k = 200k; 1102 has 10M + 200k = 10.2M. Sum = 10.4M.
        $balances = collect($report['cash_breakdown'])->pluck('balance', 'code');
        $this->assertSame('200000.00', $balances['1101']);
        $this->assertSame('10200000.00', $balances['1102']);
    }

    public function test_date_range_shifts_prior_transactions_into_beginning_cash(): void
    {
        // Transaction in October: Capital 1,000,000
        $j1 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'opening_balance',
            'reference_id' => 1,
            'description' => 'Modal Oktober',
            'transaction_date' => '2026-10-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j1, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => 1000000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j1, 'account_code' => '3101', 'account_name' => 'Modal', 'debit' => 0.00, 'credit' => 1000000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Transaction in November: Sale 250,000
        $j2 = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 102,
            'description' => 'Penjualan November',
            'transaction_date' => '2026-11-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $j2, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => 250000.00, 'credit' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $j2, 'account_code' => '4101', 'account_name' => 'Penjualan', 'debit' => 0.00, 'credit' => 250000.00, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Filter for November only
        $report = $this->reportService->generateCashFlow('2026-11-01', '2026-11-30');

        $this->assertSame('1000000.00', $report['beginning_cash']);
        $this->assertSame('250000.00', $report['net_cash_change']);
        $this->assertSame('1250000.00', $report['ending_cash']);
        $this->assertTrue($report['is_reconciled']);
    }

    public function test_cash_flow_web_route_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.cash_flow'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Arus Kas');
        $response->assertSee('Metode Langsung');
        $response->assertSee('Arus Kas dari Aktivitas Operasi');
        $response->assertSee('Arus Kas dari Aktivitas Investasi');
        $response->assertSee('Arus Kas dari Aktivitas Pendanaan');
        $response->assertSee('Rekonsiliasi Saldo Kas');
    }

    public function test_cash_flow_csv_download_returns_valid_csv(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.cash_flow.csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="laporan-arus-kas-'.date('Ymd').'.csv"');

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('ARUS KAS DARI AKTIVITAS OPERASI', $content);
        $this->assertStringContainsString('ARUS KAS DARI AKTIVITAS INVESTASI', $content);
        $this->assertStringContainsString('ARUS KAS DARI AKTIVITAS PENDANAAN', $content);
        $this->assertStringContainsString('Kas & Bank Akhir Periode', $content);
    }
}
