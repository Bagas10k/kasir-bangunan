<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoSetupController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReceivablesController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\RitaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\JournalController;

// Guest authentication & demo setup routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/demo-setup', [DemoSetupController::class, 'create'])->name('demo.setup');
    Route::post('/demo-setup', [DemoSetupController::class, 'store'])->name('demo.setup.store');
});

// Authenticated cashier / store / accounting routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // POS & Cashier
    Route::get('/', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/dashboard-sales', [PosController::class, 'dashboardSales'])->name('pos.dashboard.sales');
    Route::get('/pos/products/search', [PosController::class, 'searchProducts'])->name('pos.products.search');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/receipt/{id}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('/pos/sales', [PosController::class, 'salesHistory'])->name('pos.sales.history');
    Route::get('/piutang', [ReceivablesController::class, 'index'])->name('receivables.index');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Vehicles
    Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');

    // Inventory Receipts
    Route::get('/inventory/receipts', [InventoryReceiptController::class, 'index'])->name('inventory.receipts');
    Route::post('/inventory/receipts', [InventoryReceiptController::class, 'store'])->name('inventory.receipts.store');

    // Stock Opname
    Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('opname.index');
    Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('opname.store');

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Ritase
    Route::get('/ritase', [RitaseController::class, 'index'])->name('ritase.index');
    Route::post('/ritase', [RitaseController::class, 'store'])->name('ritase.store');
    Route::patch('/ritase/{id}/details', [RitaseController::class, 'completeDetails'])->name('ritase.details');

    // Reports
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit_loss');
    Route::get('/reports/profit-loss/csv', [ReportController::class, 'profitLossCsv'])->name('reports.profit_loss.csv');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance_sheet');
    Route::get('/reports/balance-sheet/csv', [ReportController::class, 'balanceSheetCsv'])->name('reports.balance_sheet.csv');

    // General Journal & Opening Balances
    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::get('/journals/feed', [JournalController::class, 'feed'])->name('journals.feed');
    Route::post('/journals/opening-balance', [JournalController::class, 'saveOpening'])->name('journals.opening.save');
    Route::post('/journals/opening-balance/post', [JournalController::class, 'postOpening'])->name('journals.opening.post');

    // Chart of Accounts (COA)
    Route::get('/coa', [ChartOfAccountController::class, 'index'])->name('coa.index');
    Route::post('/coa', [ChartOfAccountController::class, 'store'])->name('coa.store');
    Route::put('/coa/{id}', [ChartOfAccountController::class, 'update'])->name('coa.update');
    Route::delete('/coa/{id}', [ChartOfAccountController::class, 'destroy'])->name('coa.destroy');
});
