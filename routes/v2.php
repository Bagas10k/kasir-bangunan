<?php

use App\Http\Controllers\V2\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Fresh operational surface; legacy routes remain untouched until release gates pass.
Route::middleware('auth')->prefix('v2')->name('v2.')->group(function (): void {
    Route::get('/', [WorkspaceController::class, 'index'])->name('index');
    Route::get('/accounts', [\App\Http\Controllers\V2\AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [\App\Http\Controllers\V2\AccountController::class, 'store'])->middleware('throttle:20,1')->name('accounts.store');
    Route::put('/accounts/{id}', [\App\Http\Controllers\V2\AccountController::class, 'update'])->whereNumber('id')->middleware('throttle:20,1')->name('accounts.update');
    Route::get('/documents/{id}/{kind}', [WorkspaceController::class, 'document'])->whereNumber('id')->whereIn('kind',['receipt','delivery'])->name('documents');
    Route::post('/opening-balances', [WorkspaceController::class, 'opening'])->name('openings.store');
    Route::post('/deliveries/{id}/dispatch', [WorkspaceController::class, 'dispatch'])->whereNumber('id')->name('deliveries.dispatch');
    Route::post('/deliveries/{id}/receive', [WorkspaceController::class, 'receiveDelivery'])->whereNumber('id')->name('deliveries.receive');
    Route::post('/driver-advances', [WorkspaceController::class, 'advance'])->name('driver-advances.store');
    Route::post('/driver-advances/{id}/settle', [WorkspaceController::class, 'settle'])->whereNumber('id')->name('driver-advances.settle');
    Route::post('/shifts/{id}/resolve', [WorkspaceController::class, 'resolveShift'])->whereNumber('id')->name('shifts.resolve');
    Route::post('/payments/{id}/reject', [WorkspaceController::class, 'reject'])->whereNumber('id')->name('payments.reject');
    Route::get('/templates/{kind}', [WorkspaceController::class, 'template'])->name('templates');
    Route::post('/imports/{kind}/preview', [WorkspaceController::class, 'importPreview'])->name('imports.preview');
    Route::post('/import-batches/{id}/commit', [WorkspaceController::class, 'importCommit'])->whereNumber('id')->name('imports.commit');
    Route::post('/receipts', [WorkspaceController::class, 'receive'])->name('receipts.store');
    Route::post('/trips', [WorkspaceController::class, 'trip'])->name('trips.store');
    Route::put('/master/{kind}/{id}', [WorkspaceController::class, 'updateMaster'])->whereNumber('id')->name('master.update');
    Route::patch('/master/{kind}/{id}', [WorkspaceController::class, 'archiveMaster'])->whereNumber('id')->name('master.archive');
    Route::post('/opening-balances/preview', [WorkspaceController::class, 'openingPreview'])->name('openings.preview');
    Route::get('/master/{kind}', [WorkspaceController::class, 'master'])->name('master');
    Route::post('/master/{kind}', [WorkspaceController::class, 'saveMaster'])->name('master.store');
    Route::post('/shifts', [WorkspaceController::class, 'openShift'])->name('shifts.open');
    Route::post('/shifts/{id}/close', [WorkspaceController::class, 'closeShift'])->whereNumber('id')->name('shifts.close');
    Route::post('/sales', [WorkspaceController::class, 'checkout'])->name('sales.store');
    Route::post('/invoices/{id}/payments', [WorkspaceController::class, 'collect'])->whereNumber('id')->name('payments.store');
    Route::post('/payments/{id}/approve', [WorkspaceController::class, 'approve'])->whereNumber('id')->name('payments.approve');
});
