<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExchangeTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/create', [ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

    Route::get('/transactions', [ExchangeTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [ExchangeTransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [ExchangeTransactionController::class, 'store'])->name('transactions.store');

    Route::get('/reports/balances', [ReportController::class, 'balances'])->name('reports.balances');
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/currency-movement', [ReportController::class, 'currencyMovement'])->name('reports.currency-movement');

    Route::middleware('role:admin')->group(function () {
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');

        Route::get('/rates', [ExchangeRateController::class, 'index'])->name('rates.index');
        Route::put('/rates/{currency}', [ExchangeRateController::class, 'update'])->name('rates.update');

        Route::resource('users', UserController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
