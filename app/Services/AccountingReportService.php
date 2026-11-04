<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    public const CASH_ACCOUNTS = ['1101', '1102'];

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
            $debit = BigDecimal::of((string) ($line->total_debit ?? '0'));
            $credit = BigDecimal::of((string) ($line->total_credit ?? '0'));

            if (str_starts_with($code, '4') || ($coaType === 'revenue' && ! str_starts_with($code, '7'))) {
                // Revenue: Normal Balance Credit
                $net = $credit->minus($debit);
                $totalRevenue = $totalRevenue->plus($net);
                $revenues[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '5')) {
                // COGS / Cost adjustments: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalCogs = $totalCogs->plus($net);
                $cogs[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '6') || ($coaType === 'expense' && ! str_starts_with($code, '5') && ! str_starts_with($code, '8'))) {
                // Operating expenses: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalExpense = $totalExpense->plus($net);
                $expenses[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '7')) {
                // Other Income: Normal Balance Credit
                $net = $credit->minus($debit);
                $totalOtherIncome = $totalOtherIncome->plus($net);
                $otherIncome[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '8')) {
                // Other Expense: Normal Balance Debit
                $net = $debit->minus($credit);
                $totalOtherExpense = $totalOtherExpense->plus($net);
                $otherExpenses[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            }
        }

        $grossProfit = $totalRevenue->minus($totalCogs);
        $operatingProfit = $grossProfit->minus($totalExpense);
        $netProfit = $operatingProfit->plus($totalOtherIncome)->minus($totalOtherExpense);

        return [
            'revenues' => $revenues,
            'total_revenue' => (string) $totalRevenue->toScale(2),
            'cogs' => $cogs,
            'total_cogs' => (string) $totalCogs->toScale(2),
            'gross_profit' => (string) $grossProfit->toScale(2),
            'expenses' => $expenses,
            'total_expense' => (string) $totalExpense->toScale(2),
            'other_income' => $otherIncome,
            'total_other_income' => (string) $totalOtherIncome->toScale(2),
            'other_expenses' => $otherExpenses,
            'total_other_expense' => (string) $totalOtherExpense->toScale(2),
            'net_profit' => (string) $netProfit->toScale(2),
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
                if ($entryId !== null && ! $debit->isEqualTo($credit)) {
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
            $debit = BigDecimal::of((string) ($line->total_debit ?? '0'));
            $credit = BigDecimal::of((string) ($line->total_credit ?? '0'));

            if (str_starts_with($code, '1') || $coaType === 'asset') {
                // Assets: Debit - Credit
                $net = $debit->minus($credit);
                $totalAsset = $totalAsset->plus($net);
                $assets[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '2') || $coaType === 'liability') {
                // Liabilities: Credit - Debit
                $net = $credit->minus($debit);
                $totalLiability = $totalLiability->plus($net);
                $liabilities[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            } elseif (str_starts_with($code, '3') || $coaType === 'equity') {
                // Equity: Credit - Debit
                $net = $credit->minus($debit);
                $totalEquity = $totalEquity->plus($net);
                $equity[] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => (string) $net->toScale(2),
                ];
            }
        }

        $pl = $this->generateProfitAndLoss(null, $asOfDate);
        $netProfit = BigDecimal::of((string) ($pl['net_profit'] ?? '0'));

        $totalLiabilitiesAndEquity = $totalLiability->plus($totalEquity)->plus($netProfit);
        $totalEquityWithRetained = $totalEquity->plus($netProfit);

        return [
            'assets' => $assets,
            'total_assets' => (string) $totalAsset->toScale(2),
            'liabilities' => $liabilities,
            'total_liabilities' => (string) $totalLiability->toScale(2),
            'equity' => $equity,
            'retained_earnings' => (string) $netProfit->toScale(2),
            'total_equity' => (string) $totalEquityWithRetained->toScale(2),
            'total_liabilities_and_equity' => (string) $totalLiabilitiesAndEquity->toScale(2),
            'is_balanced' => $totalAsset->isEqualTo($totalLiabilitiesAndEquity) && $this->journalsAreBalanced($asOfDate),
        ];
    }

    /**
     * Calculates Cash Flow Statement (Laporan Arus Kas) using Direct Method (Metode Langsung)
     * from verified double-entry journal lines involving Cash & Bank accounts (1101 & 1102).
     */
    public function generateCashFlow(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $this->sanitizeDate($startDate);
        $endDate = $this->sanitizeDate($endDate);

        // 1. Calculate Beginning Cash (Saldo Awal Kas & Bank sebelum startDate)
        $beginningCash = BigDecimal::zero();
        if ($startDate) {
            $beforeQuery = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->whereIn('journal_entry_lines.account_code', self::CASH_ACCOUNTS)
                ->where('journal_entries.transaction_date', '<', $startDate);

            $begDebit = BigDecimal::zero();
            $begCredit = BigDecimal::zero();
            foreach ($beforeQuery->select('journal_entry_lines.debit', 'journal_entry_lines.credit')->cursor() as $r) {
                $begDebit = $begDebit->plus((string) $r->debit);
                $begCredit = $begCredit->plus((string) $r->credit);
            }
            $beginningCash = $begDebit->minus($begCredit);
        }

        // 2. Calculate Ending Cash (Saldo Akhir Kas & Bank per endDate)
        $endingQuery = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_code', self::CASH_ACCOUNTS);

        if ($endDate) {
            $endingQuery->where('journal_entries.transaction_date', '<=', $endDate);
        }

        $endDebit = BigDecimal::zero();
        $endCredit = BigDecimal::zero();
        foreach ($endingQuery->select('journal_entry_lines.debit', 'journal_entry_lines.credit')->cursor() as $r) {
            $endDebit = $endDebit->plus((string) $r->debit);
            $endCredit = $endCredit->plus((string) $r->credit);
        }
        $endingCash = $endDebit->minus($endCredit);

        // 3. Ending Cash Breakdown per Account
        $breakdownAccounts = DB::table('chart_of_accounts')
            ->whereIn('account_code', self::CASH_ACCOUNTS)
            ->orderBy('account_code')
            ->get(['account_code', 'account_name']);

        $cashBreakdown = [];
        $breakdownSum = BigDecimal::zero();
        foreach ($breakdownAccounts as $acc) {
            $q = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entry_lines.account_code', $acc->account_code);
            if ($endDate) {
                $q->where('journal_entries.transaction_date', '<=', $endDate);
            }
            $d = BigDecimal::zero();
            $c = BigDecimal::zero();
            foreach ($q->select('journal_entry_lines.debit', 'journal_entry_lines.credit')->cursor() as $r) {
                $d = $d->plus((string) $r->debit);
                $c = $c->plus((string) $r->credit);
            }
            $bal = $d->minus($c);
            $breakdownSum = $breakdownSum->plus($bal);
            $cashBreakdown[] = [
                'code' => $acc->account_code,
                'name' => $acc->account_name,
                'balance' => (string) $bal->toScale(2),
            ];
        }

        // 4. Retrieve journal entries with cash movements within the period
        $entryIdsQuery = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_code', self::CASH_ACCOUNTS);

        if ($startDate) {
            $entryIdsQuery->where('journal_entries.transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $entryIdsQuery->where('journal_entries.transaction_date', '<=', $endDate);
        }

        $entryIds = $entryIdsQuery->distinct()->pluck('journal_entries.id');

        $operatingInflows = [];
        $operatingOutflows = [];
        $investingInflows = [];
        $investingOutflows = [];
        $financingInflows = [];
        $financingOutflows = [];

        if (! $entryIds->isEmpty()) {
            $entries = DB::table('journal_entries')
                ->join('journal_entry_lines', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->leftJoin('chart_of_accounts', 'journal_entry_lines.account_code', '=', 'chart_of_accounts.account_code')
                ->whereIn('journal_entries.id', $entryIds)
                ->select([
                    'journal_entries.id as entry_id',
                    'journal_entries.reference_type',
                    'journal_entries.reference_id',
                    'journal_entries.description',
                    'journal_entries.transaction_date',
                    'journal_entry_lines.account_code',
                    'journal_entry_lines.account_name',
                    'journal_entry_lines.debit',
                    'journal_entry_lines.credit',
                    'chart_of_accounts.account_type',
                ])
                ->orderBy('journal_entries.transaction_date')
                ->orderBy('journal_entries.id')
                ->orderBy('journal_entry_lines.id')
                ->get()
                ->groupBy('entry_id');

            foreach ($entries as $entryLines) {
                $refType = (string) ($entryLines->first()->reference_type ?? '');
                $cashDebit = BigDecimal::zero();
                $cashCredit = BigDecimal::zero();
                $nonCashLines = [];

                foreach ($entryLines as $l) {
                    if (in_array($l->account_code, self::CASH_ACCOUNTS, true)) {
                        $cashDebit = $cashDebit->plus((string) $l->debit);
                        $cashCredit = $cashCredit->plus((string) $l->credit);
                    } else {
                        $nonCashLines[] = $l;
                    }
                }

                $netCash = $cashDebit->minus($cashCredit);
                if ($netCash->isZero()) {
                    // Internal transfer between cash and bank: net change is zero
                    continue;
                }

                if ($netCash->isPositive()) {
                    // CASH INFLOW
                    $amount = $netCash;
                    $key = 'OTHER';
                    $label = 'Penerimaan Operasional Lainnya';
                    $activity = 'operating';

                    if (in_array($refType, ['sale', 'v2_sale'], true)) {
                        $key = 'CASH_SALE';
                        $label = 'Penerimaan dari Penjualan Tunai / Uang Muka';
                        $activity = 'operating';
                    } elseif (in_array($refType, ['opening_balance', 'v2_opening_balance'], true)) {
                        $key = 'CAPITAL';
                        $label = 'Setoran Modal Awal / Pemilik';
                        $activity = 'financing';
                    } elseif ($refType === 'v2_driver_advance') {
                        $key = 'ADVANCE_REFUND';
                        $label = 'Pengembalian Sisa Uang Muka Sopir';
                        $activity = 'operating';
                    } elseif ($refType === 'v2_payment') {
                        $key = 'AR_COLLECTION';
                        $label = 'Penerimaan Pelunasan Piutang Pelanggan';
                        $activity = 'operating';
                    } else {
                        // Fallback inspection of non-cash counter lines
                        $hasAr = false;
                        $hasRev = false;
                        $hasOtherInc = false;
                        $hasEquity = false;
                        $hasFixedAsset = false;
                        $hasLiability = false;

                        foreach ($nonCashLines as $nc) {
                            $code = $nc->account_code;
                            $t = $nc->account_type;
                            if ($code === '1103') {
                                $hasAr = true;
                            } elseif (str_starts_with($code, '4') || $t === 'revenue') {
                                $hasRev = true;
                            } elseif (str_starts_with($code, '7')) {
                                $hasOtherInc = true;
                            } elseif (str_starts_with($code, '3') || $t === 'equity') {
                                $hasEquity = true;
                            } elseif (str_starts_with($code, '12') || str_starts_with($code, '15') || str_starts_with($code, '16')) {
                                $hasFixedAsset = true;
                            } elseif (str_starts_with($code, '2') || $t === 'liability') {
                                $hasLiability = true;
                            }
                        }

                        if ($hasAr) {
                            $key = 'AR_COLLECTION';
                            $label = 'Penerimaan Pelunasan Piutang Pelanggan';
                            $activity = 'operating';
                        } elseif ($hasRev) {
                            $key = 'CASH_SALE';
                            $label = 'Penerimaan dari Penjualan Tunai / Pendapatan';
                            $activity = 'operating';
                        } elseif ($hasOtherInc) {
                            $key = 'OTHER_INCOME';
                            $label = 'Penerimaan Pendapatan Lain-lain';
                            $activity = 'operating';
                        } elseif ($hasEquity) {
                            $key = 'CAPITAL';
                            $label = 'Setoran Modal Pemilik';
                            $activity = 'financing';
                        } elseif ($hasFixedAsset) {
                            $key = 'ASSET_SALE';
                            $label = 'Penerimaan Penjualan Aset Tetap';
                            $activity = 'investing';
                        } elseif ($hasLiability) {
                            $key = 'LOAN_INFLOW';
                            $label = 'Penerimaan Pinjaman / Liabilitas';
                            $activity = 'financing';
                        }
                    }

                    if ($activity === 'operating') {
                        $operatingInflows[$key] = [
                            'label' => $label,
                            'amount' => ($operatingInflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    } elseif ($activity === 'investing') {
                        $investingInflows[$key] = [
                            'label' => $label,
                            'amount' => ($investingInflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    } else {
                        $financingInflows[$key] = [
                            'label' => $label,
                            'amount' => ($financingInflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    }
                } else {
                    // CASH OUTFLOW
                    $amount = $netCash->abs();
                    $key = 'OTHER';
                    $label = 'Pengeluaran Operasional Lainnya';
                    $activity = 'operating';

                    if (in_array($refType, ['inventory_receipt', 'v2_receipt'], true)) {
                        $key = 'INVENTORY';
                        $label = 'Pembayaran Pembelian Persediaan Material';
                        $activity = 'operating';
                    } elseif ($refType === 'expense') {
                        $key = 'EXPENSE';
                        $label = 'Pembayaran Beban Operasional Toko';
                        $activity = 'operating';
                    } elseif (in_array($refType, ['ritase', 'v2_trip'], true)) {
                        $key = 'DELIVERY';
                        $label = 'Pembayaran Biaya Transportasi & Ritase Armada';
                        $activity = 'operating';
                    } elseif ($refType === 'v2_driver_advance') {
                        $key = 'ADVANCE';
                        $label = 'Pengeluaran Uang Muka Sopir & Pengiriman';
                        $activity = 'operating';
                    } elseif ($refType === 'v2_payment') {
                        $key = 'AP_PAYMENT';
                        $label = 'Pembayaran Utang Usaha ke Pemasok';
                        $activity = 'operating';
                    } elseif (in_array($refType, ['opening_balance', 'v2_opening_balance'], true)) {
                        $key = 'EQUITY_OUTFLOW';
                        $label = 'Penarikan Modal / Penyesuaian Saldo Awal';
                        $activity = 'financing';
                    } else {
                        // Fallback inspection of non-cash counter lines
                        $hasInventoryOrAp = false;
                        $hasDelivery = false;
                        $hasExpense = false;
                        $hasFixedAsset = false;
                        $hasEquity = false;

                        foreach ($nonCashLines as $nc) {
                            $code = $nc->account_code;
                            $t = $nc->account_type;
                            if ($code === '1301' || $code === '2101' || $t === 'liability') {
                                $hasInventoryOrAp = true;
                            } elseif ($code === '6201' || $code === '1191') {
                                $hasDelivery = true;
                            } elseif (str_starts_with($code, '6') || str_starts_with($code, '5') || str_starts_with($code, '8') || $t === 'expense') {
                                $hasExpense = true;
                            } elseif (str_starts_with($code, '12') || str_starts_with($code, '15') || str_starts_with($code, '16')) {
                                $hasFixedAsset = true;
                            } elseif (str_starts_with($code, '3') || $t === 'equity') {
                                $hasEquity = true;
                            }
                        }

                        if ($hasInventoryOrAp) {
                            $key = 'INVENTORY';
                            $label = 'Pembayaran Persediaan & Utang Pemasok';
                            $activity = 'operating';
                        } elseif ($hasDelivery) {
                            $key = 'DELIVERY';
                            $label = 'Pembayaran Biaya Transportasi & Ritase';
                            $activity = 'operating';
                        } elseif ($hasExpense) {
                            $key = 'EXPENSE';
                            $label = 'Pembayaran Beban Operasional';
                            $activity = 'operating';
                        } elseif ($hasFixedAsset) {
                            $key = 'ASSET_PURCHASE';
                            $label = 'Pembelian Aset Tetap / Peralatan';
                            $activity = 'investing';
                        } elseif ($hasEquity) {
                            $key = 'PRIVE';
                            $label = 'Penarikan Modal Pemilik (Prive)';
                            $activity = 'financing';
                        }
                    }

                    if ($activity === 'operating') {
                        $operatingOutflows[$key] = [
                            'label' => $label,
                            'amount' => ($operatingOutflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    } elseif ($activity === 'investing') {
                        $investingOutflows[$key] = [
                            'label' => $label,
                            'amount' => ($investingOutflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    } else {
                        $financingOutflows[$key] = [
                            'label' => $label,
                            'amount' => ($financingOutflows[$key]['amount'] ?? BigDecimal::zero())->plus($amount),
                        ];
                    }
                }
            }
        }

        // Helper to format activity totals
        $formatActivity = function (array $inflows, array $outflows) {
            $totalInflow = BigDecimal::zero();
            $totalOutflow = BigDecimal::zero();

            $formattedInflows = [];
            foreach ($inflows as $k => $item) {
                $totalInflow = $totalInflow->plus($item['amount']);
                $formattedInflows[] = [
                    'key' => $k,
                    'label' => $item['label'],
                    'amount' => (string) $item['amount']->toScale(2),
                ];
            }

            $formattedOutflows = [];
            foreach ($outflows as $k => $item) {
                $totalOutflow = $totalOutflow->plus($item['amount']);
                $formattedOutflows[] = [
                    'key' => $k,
                    'label' => $item['label'],
                    'amount' => (string) $item['amount']->toScale(2),
                ];
            }

            $net = $totalInflow->minus($totalOutflow);

            return [
                'inflows' => $formattedInflows,
                'total_inflow' => (string) $totalInflow->toScale(2),
                'outflows' => $formattedOutflows,
                'total_outflow' => (string) $totalOutflow->toScale(2),
                'net_cash' => (string) $net->toScale(2),
                '_net' => $net,
            ];
        };

        $op = $formatActivity($operatingInflows, $operatingOutflows);
        $inv = $formatActivity($investingInflows, $investingOutflows);
        $fin = $formatActivity($financingInflows, $financingOutflows);

        $netCashChange = $op['_net']->plus($inv['_net'])->plus($fin['_net']);
        $reconciledEnding = $beginningCash->plus($netCashChange);

        unset($op['_net'], $inv['_net'], $fin['_net']);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'beginning_cash' => (string) $beginningCash->toScale(2),
            'operating_activities' => $op,
            'investing_activities' => $inv,
            'financing_activities' => $fin,
            'net_cash_change' => (string) $netCashChange->toScale(2),
            'ending_cash' => (string) $endingCash->toScale(2),
            'cash_breakdown' => $cashBreakdown,
            'is_reconciled' => $reconciledEnding->isEqualTo($endingCash) && $breakdownSum->isEqualTo($endingCash),
        ];
    }
}
