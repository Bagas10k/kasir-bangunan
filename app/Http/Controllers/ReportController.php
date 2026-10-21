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
}