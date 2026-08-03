<?php

namespace Tests\Concerns;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

trait SetsUpExchangeData
{
    protected function seedRolesAndCurrencies(): void
    {
        $this->seed(RoleSeeder::class);

        Currency::create(['code' => 'SYP', 'name' => 'ليرة سورية', 'symbol' => 'ل.س', 'is_base' => true]);
        $usd = Currency::create(['code' => 'USD', 'name' => 'دولار أمريكي', 'symbol' => '$', 'is_base' => false]);
        Currency::create(['code' => 'EUR', 'name' => 'يورو', 'symbol' => '€', 'is_base' => false]);
        Currency::create(['code' => 'SAR', 'name' => 'ريال سعودي', 'symbol' => 'ر.س', 'is_base' => false]);

        ExchangeRate::create(['currency_id' => $usd->id, 'buy_rate' => 100, 'sell_rate' => 105]);
    }

    protected function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    protected function createCashier(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        return $user;
    }
}
