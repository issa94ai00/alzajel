<?php

namespace App\Services;

use App\Models\ExchangeTransaction;

class ExchangeProfitCalculator
{
    /**
     * Spread-based profit: values the "other side" of the trade at the current
     * reference rate rather than tracking historical cost-basis per unit.
     *
     * sell (office gives currency to customer): profit = total - (amount * reference_buy_rate)
     * buy  (office receives currency from customer): profit = (amount * reference_sell_rate) - total
     */
    public static function calculate(
        string $type,
        string $amount,
        string $total,
        string $referenceBuyRate,
        string $referenceSellRate,
    ): string {
        return $type === ExchangeTransaction::TYPE_SELL
            ? bcsub($total, bcmul($amount, $referenceBuyRate, 4), 4)
            : bcsub(bcmul($amount, $referenceSellRate, 4), $total, 4);
    }
}
