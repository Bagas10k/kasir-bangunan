<?php

namespace App\Http\Controllers;

use App\Services\AccountingReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function profitLoss(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $report = $service->generateProfitAndLoss($startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.profit_loss', compact('report', 'startDate', 'endDate'));
    }

    public function balanceSheet(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'as_of_date' => 'nullable|date_format:Y-m-d',
        ]);

        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $report = $service->generateBalanceSheet($asOfDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.balance_sheet', compact('report', 'asOfDate'));
    }

    public function profitLossCsv(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $report = $service->generateProfitAndLoss(
            $request->query('start_date'),
            $request->query('end_date')
        );

        $rows = [['Kategori', 'Kode', 'Nama Akun', 'Nominal (Rp)']];
        foreach ($report['revenues'] as $r) {
            $rows[] = ['Pendapatan Operasional', $r['code'], $r['name'], $r['amount']];
        }
        $rows[] = ['', '', 'TOTAL PENDAPATAN', $report['total_revenue']];
        foreach ($report['cogs'] as $c) {
            $rows[] = ['Beban Pokok Penjualan (HPP)', $c['code'], $c['name'], '-' . $c['amount']];
        }
        $rows[] = ['', '', 'TOTAL HPP', '-' . $report['total_cogs']];
        $rows[] = ['', '', 'LABA KOTOR', $report['gross_profit']];
        foreach ($report['expenses'] as $e) {
            $rows[] = ['Beban Operasional', $e['code'], $e['name'], '-' . $e['amount']];
        }
        $rows[] = ['', '', 'TOTAL BEBAN OPERASIONAL', '-' . $report['total_expense']];
        foreach ($report['other_income'] as $o) {
            $rows[] = ['Pendapatan Lain-lain', $o['code'], $o['name'], $o['amount']];
        }
        if (count($report['other_income']) > 0) {
            $rows[] = ['', '', 'TOTAL PENDAPATAN LAIN-LAIN', $report['total_other_income']];
        }
        foreach ($report['other_expenses'] as $o) {
            $rows[] = ['Beban Lain-lain', $o['code'], $o['name'], '-' . $o['amount']];
        }
        if (count($report['other_expenses']) > 0) {
            $rows[] = ['', '', 'TOTAL BEBAN LAIN-LAIN', '-' . $report['total_other_expense']];
        }
        $rows[] = ['', '', 'LABA BERSIH (NET PROFIT)', $report['net_profit']];

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        $filename = 'laporan-laba-rugi-' . date('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function balanceSheetCsv(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'as_of_date' => 'nullable|date_format:Y-m-d',
        ]);

        $report = $service->generateBalanceSheet($request->query('as_of_date', date('Y-m-d')));

        $rows = [['Kategori', 'Kode', 'Nama Akun', 'Nominal (Rp)']];
        foreach ($report['assets'] as $a) {
            $rows[] = ['Aktiva (Aset)', $a['code'], $a['name'], $a['amount']];
        }
        $rows[] = ['', '', 'TOTAL ASET (AKTIVA)', $report['total_assets']];
        foreach ($report['liabilities'] as $l) {
            $rows[] = ['Kewajiban (Liabilitas)', $l['code'], $l['name'], $l['amount']];
        }
        $rows[] = ['', '', 'TOTAL KEWAJIBAN', $report['total_liabilities']];
        foreach ($report['equity'] as $e) {
            $rows[] = ['Ekuitas (Modal)', $e['code'], $e['name'], $e['amount']];
        }
        $rows[] = ['Ekuitas (Modal)', '', 'Laba Tahun Berjalan (Net Profit)', $report['retained_earnings']];
        $rows[] = ['', '', 'TOTAL EKUITAS', $report['total_equity']];
        $rows[] = ['', '', 'TOTAL PASIVA (KEWAJIBAN + EKUITAS)', $report['total_liabilities_and_equity']];

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF" . stream_get_contents($out);
        fclose($out);

        $filename = 'neraca-' . date('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}