<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Placeholder starter rates for development only — an admin must set real rates from the Rates page.
     */
    public function run(): void
    {
        $rates = [
            'USD' => ['buy_rate' => 13000, 'sell_rate' => 13200],
            'EUR' => ['buy_rate' => 14000, 'sell_rate' => 14300],
            'SAR' => ['buy_rate' => 3450, 'sell_rate' => 3500],
        ];

        foreach ($rates as $code => $rate) {
            $currency = Currency::where('code', $code)->first();

            if ($currency) {
                ExchangeRate::firstOrCreate(['currency_id' => $currency->id], $rate);
            }
        }
    }
}
