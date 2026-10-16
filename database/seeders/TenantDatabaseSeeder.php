<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CurrencySeeder::class,
            ExchangeRateSeeder::class,
            SystemDefaultsSeeder::class,
        ]);
    }
}
