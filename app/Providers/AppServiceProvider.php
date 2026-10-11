<?php

namespace App\Providers;

use App\Models\ExchangeRate;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftMovement;
use App\Models\User;
use App\Policies\ExchangeRatePolicy;
use App\Policies\ExchangeTransactionPolicy;
use App\Policies\RolePolicy;
use App\Policies\ShiftMovementPolicy;
use App\Policies\ShiftPolicy;
use App\Policies\UserPolicy;
use App\Reports\ReportRegistry;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // مستودع واحد للإعدادات طوال الطلب حتى لا نكرر الاستعلام عن جدول settings.
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(ReportRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(ShiftMovement::class, ShiftMovementPolicy::class);
        Gate::policy(ExchangeTransaction::class, ExchangeTransactionPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
    }
}
