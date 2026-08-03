<?php

namespace App\Providers;

use App\Models\ExchangeRate;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Policies\ExchangeRatePolicy;
use App\Policies\ExchangeTransactionPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(ExchangeTransaction::class, ExchangeTransactionPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
