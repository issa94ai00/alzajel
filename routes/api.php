<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\TransactionController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/shifts/open-form-data', [ShiftController::class, 'openFormData']);
    Route::post('/shifts', [ShiftController::class, 'store']);
    Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/create-form-data', [TransactionController::class, 'createFormData']);
    Route::post('/transactions', [TransactionController::class, 'store']);

    Route::get('/currencies', [CurrencyController::class, 'index']);

    Route::get('/settings', [SettingsController::class, 'index']);

    Route::get('/receipts/templates', [ReceiptController::class, 'templates']);
    Route::get('/transactions/{transaction}/receipt', [ReceiptController::class, 'transaction']);
    Route::get('/shifts/{shift}/receipt', [ReceiptController::class, 'shift']);

    Route::get('/reports/catalog', [ReportController::class, 'catalog']);
    Route::get('/reports/run/{report}', [ReportController::class, 'run']);
    Route::get('/reports/balances', [ReportController::class, 'balances']);
    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::get('/reports/currency-movement', [ReportController::class, 'currencyMovement']);

    Route::put('/rates/{currency}', [ExchangeRateController::class, 'update'])
        ->middleware('permission:'.Permissions::RATES_UPDATE);

    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])
        ->middleware('permission:'.Permissions::REPORTS_PROFIT_LOSS);

    Route::middleware('permission:'.Permissions::CURRENCIES_MANAGE)->group(function () {
        Route::get('/admin/currencies', [CurrencyController::class, 'adminIndex']);
        Route::post('/admin/currencies', [CurrencyController::class, 'store']);
        Route::put('/admin/currencies/{currency}', [CurrencyController::class, 'update']);
    });
});
