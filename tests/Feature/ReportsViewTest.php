<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ReportsViewTest extends TestCase
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
            'name' => 'Reports Viewer',
            'email' => 'reports_view_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_profit_loss_renders_with_indonesian_currency_format(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.profit_loss'));

        $response->assertOk();
        $response->assertSee('Laba Rugi', false);
        $response->assertSee('0,00', false);
        $response->assertDontSee('0.00', false);
    }

    public function test_balance_sheet_renders_with_indonesian_currency_format(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.balance_sheet'));

        $response->assertOk();
        $response->assertSee('Neraca', false);
        $response->assertSee('SEIMBANG', false);
        $response->assertSee('0,00', false);
        $response->assertDontSee('0.00', false);
    }

    public function test_profit_loss_has_executive_summary_and_date_range_picker(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.profit_loss'));

        $response->assertOk();
        // KPI ringkasan
        $response->assertSee('Total Pendapatan', false);
        $response->assertSee('Total Beban', false);
        $response->assertSee('Laba Bersih (Net Profit)', false);
        // breadcrumb + preset periode cepat
        $response->assertSee('Laba Rugi (Income Statement)', false);
        $response->assertSee('Bulan Ini', false);
        $response->assertSee('Bulan Lalu', false);
        $response->assertSee('Tahun Ini', false);
        // header kolom tabel
        $response->assertSee('Nama Akun', false);
        $response->assertSee('Jumlah (Rp)', false);
        // rentang tanggal custom (dari -> sampai)
        $response->assertSee('Dari', false);
        $response->assertSee('Sampai', false);
        // aksi ekspor
        $response->assertSee('Unduh CSV', false);
        $response->assertSee('Cetak / PDF', false);
    }

    public function test_profit_loss_csv_export_is_downloadable(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.profit_loss.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $response->assertSee('Kategori', false);
        $response->assertSee('TOTAL PENDAPATAN', false);
        $response->assertSee('LABA BERSIH (NET PROFIT)', false);
    }

    public function test_balance_sheet_has_executive_summary_and_date_picker(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.balance_sheet'));

        $response->assertOk();
        $response->assertSee('Total Aset (Aktiva)', false);
        $response->assertSee('Total Pasiva (Kewajiban + Ekuitas)', false);
        $response->assertSee('SEIMBANG', false);
        $response->assertSee('Neraca (Balance Sheet)', false);
        $response->assertSee('Hari Ini', false);
        $response->assertSee('Akhir Bulan Lalu', false);
        $response->assertSee('Per Tanggal', false);
        $response->assertSee('Kode', false);
        $response->assertSee('Jumlah (Rp)', false);
        $response->assertSee('Unduh CSV', false);
        $response->assertSee('Cetak / PDF', false);
    }

    public function test_balance_sheet_csv_export_is_downloadable(): void
    {
        $response = $this->actingAs($this->user())->get(route('reports.balance_sheet.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $response->assertSee('TOTAL ASET (AKTIVA)', false);
        $response->assertSee('TOTAL PASIVA (KEWAJIBAN + EKUITAS)', false);
    }

    public function test_report_views_use_full_width_ledger_and_preserve_decimal_19_2_precision(): void
    {
        view()->share('errors', new ViewErrorBag());
        $maximum = '9999999999999999.99';

        $profitLoss = [
            'revenues' => [['code' => '4101', 'name' => 'Pendapatan Sintetis', 'amount' => $maximum]],
            'cogs' => [], 'expenses' => [], 'other_income' => [], 'other_expenses' => [],
            'total_revenue' => $maximum, 'total_cogs' => '0.00', 'gross_profit' => $maximum,
            'total_expense' => '0.00', 'total_other_income' => '0.00',
            'total_other_expense' => '0.00', 'net_profit' => $maximum,
        ];
        $profitHtml = view('reports.profit_loss', [
            'report' => $profitLoss, 'startDate' => '2026-10-01', 'endDate' => '2026-10-31',
        ])->render();

        $balanceSheet = [
            'assets' => [['code' => '1101', 'name' => 'Kas Sintetis', 'amount' => $maximum]],
            'liabilities' => [], 'equity' => [['code' => '3101', 'name' => 'Modal Sintetis', 'amount' => $maximum]],
            'total_assets' => $maximum, 'total_liabilities' => '0.00',
            'total_equity' => $maximum, 'retained_earnings' => '0.00',
            'total_liabilities_and_equity' => $maximum, 'is_balanced' => true,
        ];
        $balanceHtml = view('reports.balance_sheet', [
            'report' => $balanceSheet, 'asOfDate' => '2026-10-31',
        ])->render();

        foreach ([$profitHtml, $balanceHtml] as $html) {
            $this->assertStringContainsString('9.999.999.999.999.999,99', $html);
            $this->assertStringNotContainsString('10.000.000.000.000.000,00', $html);
            $this->assertStringContainsString('class="report-ledger"', $html);
            $this->assertStringNotContainsString('max-width: 760px', $html);
            $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
            $this->assertStringContainsString('scope="col"', $html);
            $this->assertStringContainsString('scope="row"', $html);
            $this->assertStringContainsString('min-height: 44px', $html);
            $this->assertStringContainsString('break-inside: avoid', $html);
        }
    }
}