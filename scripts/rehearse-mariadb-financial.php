<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AccountingReportService;
use App\Services\ExpenseService;
use App\Services\InventoryReceiptService;
use App\Services\OpeningBalanceService;
use App\Services\PosCheckoutService;
use App\Services\RitaseService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

try {
    // Fail-closed guard: ensure disposable ephemeral DB
    $driver = DB::connection()->getDriverName();
    if (!in_array($driver, ['mysql', 'mariadb'], true)) {
        fwrite(STDERR, "ERROR: Expected mysql/mariadb driver, got: {$driver}\n");
        exit(1);
    }
    $dbName = DB::connection()->getDatabaseName();
    if (!str_starts_with($dbName, 'kasir_ephem_db_') || getenv('KASIR_DISPOSABLE_DB_TEST') !== '1') {
        fwrite(STDERR, "ERROR: Disposable database guard refused execution.\n");
        exit(1);
    }

    echo "[PHP 1/6] Seeding Chart of Accounts...\n";
    DB::table('chart_of_accounts')->insert([
        ['account_code' => '1101', 'account_name' => 'Kas Toko', 'account_type' => 'asset', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '1102', 'account_name' => 'Kas Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '1103', 'account_name' => 'Piutang Usaha', 'account_type' => 'asset', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '1301', 'account_name' => 'Persediaan Material', 'account_type' => 'asset', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '2101', 'account_name' => 'Utang Usaha', 'account_type' => 'liability', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '3101', 'account_name' => 'Modal Disetor', 'account_type' => 'equity', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '4101', 'account_name' => 'Pendapatan Penjualan Material', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '5101', 'account_name' => 'Beban Pokok Penjualan', 'account_type' => 'expense', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '6101', 'account_name' => 'Beban Operasional', 'account_type' => 'expense', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
        ['account_code' => '6201', 'account_name' => 'Beban Transportasi & Ritase', 'account_type' => 'expense', 'normal_balance' => 'debit', 'created_at' => now(), 'updated_at' => now()],
    ]);

    echo "[PHP 2/6] Posting neutral synthetic opening balance, then inserting product & receipt...\n";
    $openingService = new OpeningBalanceService();
    $openingService->saveDraft('2026-10-01', '2000000.00', '1000000.00');
    $openingService->post();
    $productId = DB::table('products')->insertGetId([
        'sku' => 'SYN-FIN-001',
        'name' => 'Synthetic Portland Cement 50kg',
        'unit' => 'sak',
        'cost_price' => 0.0000,
        'selling_price' => 75000.00,
        'stock_quantity' => 0.0000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $receiptService = new InventoryReceiptService();
    $receiptQty = '20.5000';
    $receiptUnitCost = '50000.2500'; // 4dp unit cost
    $receiptResult = $receiptService->receive(
        items: [
            [
                'product_id' => $productId,
                'quantity' => $receiptQty,
                'unit_cost' => $receiptUnitCost,
            ]
        ],
        receiptType: 'purchase',
        creditAccount: '2101',
        notes: 'Synthetic material purchase order rehearsal',
        supplierName: 'Synthetic Supplier Ephemeral',
        receiptDate: '2026-10-21'
    );

    $prodAfterReceipt = DB::table('products')->where('id', $productId)->first();
    if (BigDecimal::of((string)$prodAfterReceipt->stock_quantity)->compareTo(BigDecimal::of('20.5000')) !== 0) {
        fwrite(STDERR, "ERROR: Stock quantity after receipt mismatch\n");
        exit(1);
    }
    if (BigDecimal::of((string)$prodAfterReceipt->cost_price)->compareTo(BigDecimal::of('50000.2500')) !== 0) {
        fwrite(STDERR, "ERROR: Cost price after receipt mismatch\n");
        exit(1);
    }

    echo "[PHP 3/6] Recording POS checkout, operational expense, and ritase...\n";
    // POS Sale: 5.5000 units @ 75000.00
    $posService = new PosCheckoutService();
    $saleQty = 5.5;
    $checkoutResult = $posService->checkout(
        cartItems: [
            ['product_id' => $productId, 'quantity' => $saleQty]
        ],
        paymentMethod: 'cash',
        paidAmount: 500000.00,
        customerType: 'retail'
    );

    // Expense: 25,000.00 cash
    $expenseService = new ExpenseService();
    $expenseAmount = '25000.00';
    $expenseResult = $expenseService->recordExpense(
        category: 'Operational Utilities',
        amount: $expenseAmount,
        description: 'Rehearsal utility expense',
        paymentMethod: 'cash'
    );

    // Ritase: fuel 20,000.00 + allowance 30,000.00 = 50,000.00 cash
    $vehicleId = DB::table('vehicles')->insertGetId([
        'license_plate' => 'B 9999 SYN',
        'vehicle_type' => 'truck',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $ritaseService = new RitaseService();
    $fuelCost = '20000.00';
    $allowance = '30000.00';
    $ritaseResult = $ritaseService->recordRitase(
        vehicleId: $vehicleId,
        driverName: 'Driver Ephemeral',
        destination: 'Proyek Synthetic Rehearsal',
        cargoSummary: 'Material Delivery',
        fuelCost: $fuelCost,
        driverAllowance: $allowance
    );

    echo "[PHP 4/6] Inserting large DECIMAL(19,2) balanced journal pair (no int overflow)...\n";
    // 850 billion IDR capital injection: tests DECIMAL(19,2) without PHP 64-bit int overflow
    $largeAmount = '850000000000.50';
    $largeJournalId = DB::table('journal_entries')->insertGetId([
        'reference_type' => 'opening',
        'reference_id' => null,
        'description' => 'Synthetic Large Capital Injection',
        'transaction_date' => '2026-10-21',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('journal_entry_lines')->insert([
        [
            'journal_entry_id' => $largeJournalId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => $largeAmount,
            'credit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'journal_entry_id' => $largeJournalId,
            'account_code' => '3101',
            'account_name' => 'Modal Disetor',
            'debit' => '0.00',
            'credit' => $largeAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    echo "[PHP 5/6] Verifying journal equality & stock subledger...\n";
    // 1. Journal Equality
    $journalTotals = DB::table('journal_entry_lines')
        ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
        ->first();
    $totDebit = BigDecimal::of((string)$journalTotals->total_debit);
    $totCredit = BigDecimal::of((string)$journalTotals->total_credit);

    echo "Journal Equality: Total Debit={$totDebit}, Total Credit={$totCredit}\n";
    if (!$totDebit->isEqualTo($totCredit)) {
        fwrite(STDERR, "ERROR: Journal equality failed! Debit != Credit\n");
        exit(1);
    }

    // 2. Stock Subledger
    $finalProduct = DB::table('products')->where('id', $productId)->first();
    $finalStock = BigDecimal::of((string)$finalProduct->stock_quantity);
    $expectedStock = BigDecimal::of('15.0000');
    if (!$finalStock->isEqualTo($expectedStock)) {
        fwrite(STDERR, "ERROR: Stock quantity mismatch! Expected: {$expectedStock}, Got: {$finalStock}\n");
        exit(1);
    }

    $movements = DB::table('stock_movements')
        ->where('product_id', $productId)
        ->orderBy('id')
        ->get();
    $movementSum = BigDecimal::zero();
    foreach ($movements as $m) {
        $movementSum = $movementSum->plus(BigDecimal::of((string)$m->quantity_delta));
    }
    if (!$movementSum->isEqualTo($expectedStock)) {
        fwrite(STDERR, "ERROR: Stock movements sum mismatch! Sum: {$movementSum}, Expected: {$expectedStock}\n");
        exit(1);
    }

    echo "[PHP 6/6] Verifying AccountingReportService SQL SUM for P&L and Balance Sheet...\n";
    $reportService = new AccountingReportService();
    $pl = $reportService->generateProfitAndLoss('2026-10-01', '2026-10-31');
    $bs = $reportService->generateBalanceSheet('2026-10-31');

    // Expected P&L via Brick\Math:
    $expectedRevenue = BigDecimal::of('412500.00'); // 5.5 * 75000.00
    $expectedCogs = BigDecimal::of('275001.38');    // 5.5 * 50000.2500 = 275001.375 -> rounded to 2dp: 275001.38
    $expectedGrossProfit = $expectedRevenue->minus($expectedCogs); // 137498.62
    $expectedExpenses = BigDecimal::of('75000.00'); // expense 25000 + ritase 50000
    $expectedNetProfit = $expectedGrossProfit->minus($expectedExpenses); // 62498.62

    echo "P&L Report:\n";
    echo "  Total Revenue: {$pl['total_revenue']} (Expected: {$expectedRevenue})\n";
    echo "  Total COGS:    {$pl['total_cogs']} (Expected: {$expectedCogs})\n";
    echo "  Gross Profit:  {$pl['gross_profit']} (Expected: {$expectedGrossProfit})\n";
    echo "  Total Expense: {$pl['total_expense']} (Expected: {$expectedExpenses})\n";
    echo "  Net Profit:    {$pl['net_profit']} (Expected: {$expectedNetProfit})\n";

    if ($pl['total_revenue'] !== (string)$expectedRevenue) {
        fwrite(STDERR, "ERROR: P&L total_revenue mismatch!\n");
        exit(1);
    }
    if ($pl['total_cogs'] !== (string)$expectedCogs) {
        fwrite(STDERR, "ERROR: P&L total_cogs mismatch!\n");
        exit(1);
    }
    if ($pl['gross_profit'] !== (string)$expectedGrossProfit) {
        fwrite(STDERR, "ERROR: P&L gross_profit mismatch!\n");
        exit(1);
    }
    if ($pl['total_expense'] !== (string)$expectedExpenses) {
        fwrite(STDERR, "ERROR: P&L total_expense mismatch!\n");
        exit(1);
    }
    if ($pl['net_profit'] !== (string)$expectedNetProfit) {
        fwrite(STDERR, "ERROR: P&L net_profit mismatch!\n");
        exit(1);
    }

    // Balance Sheet Report:
    echo "Balance Sheet Report:\n";
    echo "  Total Assets:                {$bs['total_assets']}\n";
    echo "  Total Liabilities:           {$bs['total_liabilities']}\n";
    echo "  Total Equity:                {$bs['total_equity']}\n";
    echo "  Retained Earnings:           {$bs['retained_earnings']}\n";
    echo "  Total Liabilities & Equity:  {$bs['total_liabilities_and_equity']}\n";
    echo "  Is Balanced:                 " . ($bs['is_balanced'] ? 'TRUE' : 'FALSE') . "\n";

    // Independent fixture totals, not merely the report's own balance equation.
    $expectedAssets = BigDecimal::of('850000000000.50')
        ->plus('3000000.00')
        ->plus('412500.00')->minus('25000.00')->minus('50000.00')
        ->plus('1025005.13')->minus('275001.38');
    $expectedLiabilities = BigDecimal::of('1025005.13');
    $expectedPaidInCapital = BigDecimal::of('850000000000.50')->plus('3000000.00');
    $expectedEquity = $expectedPaidInCapital->plus($expectedNetProfit);
    if ($bs['total_assets'] !== (string) $expectedAssets->toScale(2)
        || $bs['total_liabilities'] !== (string) $expectedLiabilities->toScale(2)
        || $bs['total_equity'] !== (string) $expectedEquity->toScale(2)) {
        fwrite(STDERR, "ERROR: Balance sheet differs from independent fixture totals.\n");
        exit(1);
    }
    if (!$bs['is_balanced']) {
        fwrite(STDERR, "ERROR: Balance sheet is not balanced!\n");
        exit(1);
    }

    $netProfitBd = BigDecimal::of((string)$pl['net_profit']);
    $retainedEarningsBd = BigDecimal::of((string)$bs['retained_earnings']);
    if (!$netProfitBd->isEqualTo($retainedEarningsBd)) {
        fwrite(STDERR, "ERROR: Retained earnings does not match net profit!\n");
        exit(1);
    }

    $totAssetsBd = BigDecimal::of((string)$bs['total_assets']);
    $totLiabAndEquityBd = BigDecimal::of((string)$bs['total_liabilities_and_equity']);
    if (!$totAssetsBd->isEqualTo($totLiabAndEquityBd)) {
        fwrite(STDERR, "ERROR: Total assets != Total liabilities & equity!\n");
        exit(1);
    }

    echo "Verifying MariaDB posted-journal immutability triggers...\n";
    $lineId = (int) DB::table('journal_entry_lines')->where('journal_entry_id', $expenseResult['journal_entry_id'])->value('id');
    try {
        DB::table('journal_entry_lines')->where('id', $lineId)->update(['debit' => '1.00']);
        fwrite(STDERR, "ERROR: Direct posted-journal line update unexpectedly succeeded.\n");
        exit(1);
    } catch (\Illuminate\Database\QueryException $e) {
        echo "  Direct journal-line update rejected.\n";
    }
    try {
        DB::table('journal_entries')->where('id', $expenseResult['journal_entry_id'])->delete();
        fwrite(STDERR, "ERROR: Direct posted-journal deletion unexpectedly succeeded.\n");
        exit(1);
    } catch (\Illuminate\Database\QueryException $e) {
        echo "  Direct journal-header deletion rejected.\n";
    }

    echo "SUB-SUITE SUCCESS: All MariaDB 11.4 financial integration calculations verified.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "FATAL EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
