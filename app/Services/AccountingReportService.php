<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    /**
     * Sanitizes and validates date string to prevent query errors and malformed inputs.
     */
    private function sanitizeDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $trimmed = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            $d = DateTime::createFromFormat('Y-m-d', $trimmed);
            if ($d && $d->format('Y-m-d') === $trimmed) {
                return $trimmed;
            }
        }

        throw new \InvalidArgumentException("Invalid date format [{$date}]. Expected Y-m-d.");
    }

    /**
     * Fetches aggregated journal lines for Profit & Loss calculation.
     */
    protected function fetchProfitAndLossLines(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('chart_of_accounts', 'journal_entry_lines.account_code', '=', 'chart_of_accounts.account_code');

        if ($startDate) {
            $query->where('journal_entries.transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('journal_entries.transaction_date', '<=', $endDate);
        }

        return $query->select(
            'journal_entry_lines.account_code',
            DB::raw('COALESCE(MAX(chart_of_accounts.account_name), MAX(journal_entry_lines.account_name), journal_entry_lines.account_code) as account_name'),
            DB::raw('MAX(chart_of_accounts.account_type) as coa_type'),
            DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
            DB::raw('SUM(journal_entry_lines.credit) as total_credit')
        )->groupBy('journal_entry_lines.account_code')
        ->orderBy('journal_entry_lines.account_code')
        ->get();
    }

    /**
     * Fetches aggregated journal lines for Balance Sheet calculation.
     */
    protected function fetchBalanceSheetLines(?string $asOfDate = null): Collection
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('chart_of_accounts', 'journal_entry_lines.account_code', '=', 'chart_of_accounts.account_code');

        if ($asOfDate) {
            $query->where('journal_entries.transaction_date', '<=', $asOfDate);
        }

        return $query->select(
            'journal_entry_lines.account_code',
            DB::raw('COALESCE(MAX(chart_of_accounts.account_name), MAX(journal_entry_lines.account_name), journal_entry_lines.account_code) as account_name'),
            DB::raw('MAX(chart_of_accounts.account_type) as coa_type'),
            DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
            DB::raw('SUM(journal_entry_lines.credit) as total_credit')
        )->groupBy('journal_entry_lines.account_code')
        ->orderBy('journal_entry_lines.account_code')
        ->get();
    }

    /**
     * Calculates Profit & Loss (Laba Rugi) directly from verified double-entry journal lines:
     * Operating Revenue (4xxx): Credit - Debit
     * COGS (5xxx): Debit - Credit
     * Operating Expenses (6xxx): Debit - Credit
     * Other Income (7xxx): Credit - Debit
     * Other Expenses (8xxx): Debit - Credit
     * Gross Profit = Revenue - COGS
     * Net Profit = Gross Profit - Operating Expenses + Other Income - Other Expenses
     */
    public function generateProfitAndLoss(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $this->sanitizeDate($startDate);
        $endDate = $this->sanitizeDate($endDate);

        $lines = $this->fetchProfitAndLossLines($startDate, $endDate);

        $revenues = [];
        $cogs = [];
        $expenses = [];
        $otherIncome = [];
        $otherExpenses = [];

        $totalRevenue = BigDecimal::zero();
        $totalCogs = BigDecimal::zero();
        $totalExpense = BigDecimal::zero();
        $totalOtherIncome = BigDecimal::zero();
        $totalOtherExpense = BigDecimal::zero();

        foreach ($lines as $line) {
            $code = $line->account_code;
            $name = $line->account_name;
            $coaType = $line->coa_type;
            $debit = BigDecimal::of((string)($line->total_debit ?? '0'));
            $credit = BigDecimal::of((string)($line->total_credit ?? '0'));

            if (str_starts_with($code, '4') || ($coaType === 'revenue' && !str_starts_with($code, '7'))) {
                // Revenue: Normal Balance Credit
                $net = $credit->minus($debit);
                $totalRevenue = $totalRevenue->plus($net);
                $revenues[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '5')) {
                // COGS / Cost adjustments: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalCogs = $totalCogs->plus($net);
                $cogs[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '6') || ($coaType === 'expense' && !str_starts_with($code, '5') && !str_starts_with($code, '8'))) {
                // Operating expenses: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalExpense = $totalExpense->plus($net);
                $expenses[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '7')) {
                // Other Income: Normal Balance Credit
                $net = $credit->minus($debit);
                $totalOtherIncome = $totalOtherIncome->plus($net);
                $otherIncome[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '8')) {
                // Other Expense: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalOtherExpense = $totalOtherExpense->plus($net);
                $otherExpenses[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            }
        }

        $grossProfit = $totalRevenue->minus($totalCogs);
        $operatingProfit = $grossProfit->minus($totalExpense);
        $netProfit = $operatingProfit->plus($totalOtherIncome)->minus($totalOtherExpense);

        return [
            'revenues' => $revenues,
            'total_revenue' => (string)$totalRevenue->toScale(2),
            'cogs' => $cogs,
            'total_cogs' => (string)$totalCogs->toScale(2),
            'gross_profit' => (string)$grossProfit->toScale(2),
            'expenses' => $expenses,
            'total_expense' => (string)$totalExpense->toScale(2),
            'other_income' => $otherIncome,
            'total_other_income' => (string)$totalOtherIncome->toScale(2),
            'other_expenses' => $otherExpenses,
            'total_other_expense' => (string)$totalOtherExpense->toScale(2),
            'net_profit' => (string)$netProfit->toScale(2),
        ];
    }

    /**
     * Validate each posted entry, not only aggregate balance sheet totals.
     * Streaming rows preserves exact DECIMAL values and avoids loading the ledger at once.
     */
    private function journalsAreBalanced(?string $asOfDate): bool
    {
        $rows = DB::table('journal_entries')
            ->leftJoin('journal_entry_lines', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->when($asOfDate, fn ($q) => $q->where('journal_entries.transaction_date', '<=', $asOfDate))
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entries.id as entry_id', 'journal_entry_lines.id as line_id',
                'journal_entry_lines.debit', 'journal_entry_lines.credit')
            ->cursor();
        $entryId = null;
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        foreach ($rows as $row) {
            if ($entryId !== $row->entry_id) {
                if ($entryId !== null && !$debit->isEqualTo($credit)) {
                    return false;
                }
                $entryId = $row->entry_id;
                $debit = BigDecimal::zero();
                $credit = BigDecimal::zero();
            }
            if ($row->line_id === null) {
                return false;
            }
            $debit = $debit->plus((string) $row->debit);
            $credit = $credit->plus((string) $row->credit);
        }
        return $debit->isEqualTo($credit);
    }

    /**
     * Calculates Balance Sheet (Neraca) from journal entries:
     * Assets (1xxx) = Liabilities (2xxx) + Equity (3xxx) + Net Profit
     */
    public function generateBalanceSheet(?string $asOfDate = null): array
    {
        $asOfDate = $this->sanitizeDate($asOfDate);

        $lines = $this->fetchBalanceSheetLines($asOfDate);

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAsset = BigDecimal::zero();
        $totalLiability = BigDecimal::zero();
        $totalEquity = BigDecimal::zero();

        foreach ($lines as $line) {
            $code = $line->account_code;
            $name = $line->account_name;
            $coaType = $line->coa_type;
            $debit = BigDecimal::of((string)($line->total_debit ?? '0'));
            $credit = BigDecimal::of((string)($line->total_credit ?? '0'));

            if (str_starts_with($code, '1') || $coaType === 'asset') {
                // Assets: Debit - Credit
                $net = $debit->minus($credit);
                $totalAsset = $totalAsset->plus($net);
                $assets[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '2') || $coaType === 'liability') {
                // Liabilities: Credit - Debit
                $net = $credit->minus($debit);
                $totalLiability = $totalLiability->plus($net);
                $liabilities[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            } elseif (str_starts_with($code, '3') || $coaType === 'equity') {
                // Equity: Credit - Debit
                $net = $credit->minus($debit);
                $totalEquity = $totalEquity->plus($net);
                $equity[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string)$net->toScale(2),
                ];
            }
        }

        $pl = $this->generateProfitAndLoss(null, $asOfDate);
        $netProfit = BigDecimal::of((string)($pl['net_profit'] ?? '0'));

        $totalLiabilitiesAndEquity = $totalLiability->plus($totalEquity)->plus($netProfit);
        $totalEquityWithRetained = $totalEquity->plus($netProfit);

        return [
            'assets' => $assets,
            'total_assets' => (string)$totalAsset->toScale(2),
            'liabilities' => $liabilities,
            'total_liabilities' => (string)$totalLiability->toScale(2),
            'equity' => $equity,
            'retained_earnings' => (string)$netProfit->toScale(2),
            'total_equity' => (string)$totalEquityWithRetained->toScale(2),
            'total_liabilities_and_equity' => (string)$totalLiabilitiesAndEquity->toScale(2),
            'is_balanced' => $totalAsset->isEqualTo($totalLiabilitiesAndEquity) && $this->journalsAreBalanced($asOfDate),
        ];
    }
}
