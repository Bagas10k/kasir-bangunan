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
            $rows[] = ['Beban Pokok Penjualan (HPP)', $c['code'], $c['name'], '-'.$c['amount']];
        }
        $rows[] = ['', '', 'TOTAL HPP', '-'.$report['total_cogs']];
        $rows[] = ['', '', 'LABA KOTOR', $report['gross_profit']];
        foreach ($report['expenses'] as $e) {
            $rows[] = ['Beban Operasional', $e['code'], $e['name'], '-'.$e['amount']];
        }
        $rows[] = ['', '', 'TOTAL BEBAN OPERASIONAL', '-'.$report['total_expense']];
        foreach ($report['other_income'] as $o) {
            $rows[] = ['Pendapatan Lain-lain', $o['code'], $o['name'], $o['amount']];
        }
        if (count($report['other_income']) > 0) {
            $rows[] = ['', '', 'TOTAL PENDAPATAN LAIN-LAIN', $report['total_other_income']];
        }
        foreach ($report['other_expenses'] as $o) {
            $rows[] = ['Beban Lain-lain', $o['code'], $o['name'], '-'.$o['amount']];
        }
        if (count($report['other_expenses']) > 0) {
            $rows[] = ['', '', 'TOTAL BEBAN LAIN-LAIN', '-'.$report['total_other_expense']];
        }
        $rows[] = ['', '', 'LABA BERSIH (NET PROFIT)', $report['net_profit']];

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        $filename = 'laporan-laba-rugi-'.date('Ymd').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        $filename = 'neraca-'.date('Ymd').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function cashFlow(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $report = $service->generateCashFlow($startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('reports.cash_flow', compact('report', 'startDate', 'endDate'));
    }

    public function cashFlowCsv(Request $request, AccountingReportService $service)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $report = $service->generateCashFlow($startDate, $endDate);

        $rows = [['Aktivitas', 'Keterangan', 'Arus Masuk (Rp)', 'Arus Keluar (Rp)', 'Arus Bersih (Rp)']];

        // 1. Beginning Cash
        $rows[] = ['Saldo Awal', 'Kas & Bank Awal Periode', '', '', $report['beginning_cash']];
        $rows[] = ['', '', '', '', ''];

        // 2. Operating Activities
        $rows[] = ['Aktivitas Operasi', 'ARUS KAS DARI AKTIVITAS OPERASI', '', '', ''];
        foreach ($report['operating_activities']['inflows'] as $in) {
            $rows[] = ['Operasi (Masuk)', $in['label'], $in['amount'], '', $in['amount']];
        }
        foreach ($report['operating_activities']['outflows'] as $out) {
            $rows[] = ['Operasi (Keluar)', $out['label'], '', '-'.$out['amount'], '-'.$out['amount']];
        }
        $rows[] = ['', 'TOTAL ARUS KAS OPERASI BERSIH', $report['operating_activities']['total_inflow'], '-'.$report['operating_activities']['total_outflow'], $report['operating_activities']['net_cash']];
        $rows[] = ['', '', '', '', ''];

        // 3. Investing Activities
        $rows[] = ['Aktivitas Investasi', 'ARUS KAS DARI AKTIVITAS INVESTASI', '', '', ''];
        foreach ($report['investing_activities']['inflows'] as $in) {
            $rows[] = ['Investasi (Masuk)', $in['label'], $in['amount'], '', $in['amount']];
        }
        foreach ($report['investing_activities']['outflows'] as $out) {
            $rows[] = ['Investasi (Keluar)', $out['label'], '', '-'.$out['amount'], '-'.$out['amount']];
        }
        $rows[] = ['', 'TOTAL ARUS KAS INVESTASI BERSIH', $report['investing_activities']['total_inflow'], '-'.$report['investing_activities']['total_outflow'], $report['investing_activities']['net_cash']];
        $rows[] = ['', '', '', '', ''];

        // 4. Financing Activities
        $rows[] = ['Aktivitas Pendanaan', 'ARUS KAS DARI AKTIVITAS PENDANAAN', '', '', ''];
        foreach ($report['financing_activities']['inflows'] as $in) {
            $rows[] = ['Pendanaan (Masuk)', $in['label'], $in['amount'], '', $in['amount']];
        }
        foreach ($report['financing_activities']['outflows'] as $out) {
            $rows[] = ['Pendanaan (Keluar)', $out['label'], '', '-'.$out['amount'], '-'.$out['amount']];
        }
        $rows[] = ['', 'TOTAL ARUS KAS PENDANAAN BERSIH', $report['financing_activities']['total_inflow'], '-'.$report['financing_activities']['total_outflow'], $report['financing_activities']['net_cash']];
        $rows[] = ['', '', '', '', ''];

        // 5. Net Change & Ending Cash
        $rows[] = ['Kenaikan / (Penurunan) Bersih', 'Total Perubahan Kas Bersih Periode Ini', '', '', $report['net_cash_change']];
        $rows[] = ['Saldo Akhir', 'Kas & Bank Akhir Periode', '', '', $report['ending_cash']];
        $rows[] = ['', '', '', '', ''];

        // 6. Breakdown
        $rows[] = ['Rincian Saldo Akhir', 'Akun', 'Kode', 'Saldo (Rp)', ''];
        foreach ($report['cash_breakdown'] as $b) {
            $rows[] = ['Rincian Saldo Akhir', $b['name'], $b['code'], $b['balance'], ''];
        }

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = "\xEF\xBB\xBF".stream_get_contents($out);
        fclose($out);

        $filename = 'laporan-arus-kas-'.date('Ymd').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
