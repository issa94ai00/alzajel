<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExchangeTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Settings\ReceiptTemplateController;
use App\Http\Controllers\Settings\ReportDefinitionController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftMovementController;
use App\Http\Controllers\UserController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/register-company', [\App\Http\Controllers\Central\TenantRegistrationController::class, 'create'])->name('tenant.register.create');
    Route::post('/register-company', [\App\Http\Controllers\Central\TenantRegistrationController::class, 'register'])->name('tenant.register.store');

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

        Route::middleware('permission:'.Permissions::TRANSACTIONS_DELETE)->group(function () {
            Route::delete('/transactions/{transaction}', [ExchangeTransactionController::class, 'destroy'])
                ->name('transactions.destroy');
            Route::post('/transactions/{transaction}/restore', [ExchangeTransactionController::class, 'restore'])
                ->withTrashed()
                ->name('transactions.restore');
        });

        Route::middleware('permission:'.Permissions::SHIFTS_DEPOSIT)->group(function () {
            Route::post('/shifts/{shift}/movements', [ShiftMovementController::class, 'store'])
                ->name('shifts.movements.store');
            Route::delete('/shift-movements/{movement}', [ShiftMovementController::class, 'destroy'])
                ->name('movements.destroy');
            Route::post('/shift-movements/{movement}/restore', [ShiftMovementController::class, 'restore'])
                ->withTrashed()
                ->name('movements.restore');
        });

        Route::middleware('permission:'.Permissions::SHIFTS_DELETE)->group(function () {
            Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
            Route::post('/shifts/{shift}/restore', [ShiftController::class, 'restore'])
                ->withTrashed()
                ->name('shifts.restore');
        });

        Route::get('/transactions/{transaction}/receipt', [ReceiptController::class, 'transaction'])->name('transactions.receipt');
        Route::get('/shifts/{shift}/receipt', [ReceiptController::class, 'shift'])->name('shifts.receipt');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/balances', [ReportController::class, 'balances'])->name('reports.balances');
        Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('/reports/currency-movement', [ReportController::class, 'currencyMovement'])->name('reports.currency-movement');
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');

        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');

        Route::middleware('permission:'.Permissions::USERS_CREATE)->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
        });

        Route::middleware('permission:'.Permissions::USERS_VIEW)->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::middleware('permission:'.Permissions::USERS_UPDATE)->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        });

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:'.Permissions::USERS_DELETE)
            ->name('users.destroy');

        Route::middleware('permission:'.Permissions::ROLES_MANAGE)->group(function () {
            Route::resource('roles', RoleController::class)->except(['show']);
        });

        Route::get('/activity', [ActivityLogController::class, 'index'])
            ->middleware('permission:'.Permissions::ACTIVITY_VIEW)
            ->name('activity.index');

        Route::middleware('permission:'.Permissions::SETTINGS_MANAGE)->group(function () {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/reset', [SettingsController::class, 'reset'])->name('settings.reset');

            Route::put('/settings/reports', [ReportDefinitionController::class, 'update'])->name('settings.reports.update');
            Route::post('/settings/reports', [ReportDefinitionController::class, 'store'])->name('settings.reports.store');
            Route::delete('/settings/reports/{reportDefinition}', [ReportDefinitionController::class, 'destroy'])->name('settings.reports.destroy');

            Route::post('/settings/receipt-templates', [ReceiptTemplateController::class, 'store'])->name('settings.receipt-templates.store');
            Route::put('/settings/receipt-templates/{receipt_template}', [ReceiptTemplateController::class, 'update'])->name('settings.receipt-templates.update');
            Route::delete('/settings/receipt-templates/{receipt_template}', [ReceiptTemplateController::class, 'destroy'])->name('settings.receipt-templates.destroy');
        });

        Route::get('/rates', [ExchangeRateController::class, 'index'])
            ->middleware('permission:'.Permissions::RATES_VIEW)
            ->name('rates.index');

        Route::post('/rates/sp-today/refresh', [ExchangeRateController::class, 'refreshSpToday'])
            ->middleware('permission:'.Permissions::RATES_VIEW)
            ->name('rates.sptoday.refresh');

        Route::post('/rates/sync-all', [ExchangeRateController::class, 'syncAll'])
            ->middleware('permission:'.Permissions::RATES_UPDATE)
            ->name('rates.sync-all');

        Route::put('/rates/{currency}', [ExchangeRateController::class, 'update'])
            ->middleware('permission:'.Permissions::RATES_UPDATE)
            ->name('rates.update');

        Route::middleware('permission:'.Permissions::CURRENCIES_MANAGE)->group(function () {
            Route::resource('currencies', CurrencyController::class)->except(['show', 'destroy']);
            Route::post('/currencies/{currency}/base', [CurrencyController::class, 'makeBase'])->name('currencies.base');
            Route::post('/currencies/{currency}/toggle', [CurrencyController::class, 'toggleStatus'])->name('currencies.toggle');
            Route::post('/currencies/import-sp-today', [CurrencyController::class, 'importFromSpToday'])->name('currencies.import-sp-today');
            Route::post('/currencies/batch-status', [CurrencyController::class, 'batchStatus'])->name('currencies.batch-status');
        });
    });

    require __DIR__.'/auth.php';
});
