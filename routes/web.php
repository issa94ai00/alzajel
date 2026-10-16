<?php

use App\Http\Controllers\Central\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (Landlord) Web Routes
|--------------------------------------------------------------------------
| هذه المسارات خاصة بالنطاق المركزي فقط (Landing, Plans, Registration)
| مسارات المحاسبة لكل عميل موجودة في routes/tenant.php
*/

Route::get('/', function () {
    return response()->json([
        'platform' => config('app.name', 'DamaTech SaaS'),
        'status' => 'online',
        'message' => 'Central Platform Landing & API',
        'subdomains_format' => '{subdomain}.' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'bashar.damatech.sy'),
    ]);
})->name('central.landing');

Route::prefix('api/central')->group(function () {
    Route::get('/plans', [TenantRegistrationController::class, 'plans'])->name('central.plans');
    Route::post('/register-tenant', [TenantRegistrationController::class, 'register'])->name('central.register-tenant');
});

Route::get('/register-company', [TenantRegistrationController::class, 'create'])->name('tenant.register.create');
Route::post('/register-company', [TenantRegistrationController::class, 'register'])->name('tenant.register.store');
