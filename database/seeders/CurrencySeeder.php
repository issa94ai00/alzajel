<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'SYP', 'name' => 'ليرة سورية', 'symbol' => 'ل.س', 'is_base' => true],
            ['code' => 'USD', 'name' => 'دولار أمريكي', 'symbol' => '$', 'is_base' => false],
            ['code' => 'EUR', 'name' => 'يورو', 'symbol' => '€', 'is_base' => false],
            ['code' => 'SAR', 'name' => 'ريال سعودي', 'symbol' => 'ر.س', 'is_base' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
