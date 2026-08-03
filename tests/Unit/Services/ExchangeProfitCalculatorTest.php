<?php

namespace Tests\Unit\Services;

use App\Models\ExchangeTransaction;
use App\Services\ExchangeProfitCalculator;
use PHPUnit\Framework\TestCase;

class ExchangeProfitCalculatorTest extends TestCase
{
    public function test_sell_at_reference_rate_yields_pure_spread(): void
    {
        // amount=100, sell rate=105 (total=10500), reference buy=100, reference sell=105
        $profit = ExchangeProfitCalculator::calculate(
            ExchangeTransaction::TYPE_SELL,
            '100',
            '10500',
            '100',
            '105',
        );

        $this->assertSame('500.0000', $profit);
    }

    public function test_buy_at_reference_rate_yields_pure_spread(): void
    {
        // amount=100, buy rate=100 (total=10000), reference buy=100, reference sell=105
        $profit = ExchangeProfitCalculator::calculate(
            ExchangeTransaction::TYPE_BUY,
            '100',
            '10000',
            '100',
            '105',
        );

        $this->assertSame('500.0000', $profit);
    }

    public function test_sell_override_toward_loss_reduces_profit(): void
    {
        // cashier sells at 102 instead of the suggested 105 -> less revenue, less profit
        $profit = ExchangeProfitCalculator::calculate(
            ExchangeTransaction::TYPE_SELL,
            '100',
            '10200',
            '100',
            '105',
        );

        $this->assertSame('200.0000', $profit);
    }

    public function test_buy_override_toward_extra_profit(): void
    {
        // cashier buys at 98 instead of the suggested 100 -> cheaper acquisition, more profit
        $profit = ExchangeProfitCalculator::calculate(
            ExchangeTransaction::TYPE_BUY,
            '100',
            '9800',
            '100',
            '105',
        );

        $this->assertSame('700.0000', $profit);
    }

    public function test_sell_override_can_produce_a_loss(): void
    {
        // cashier sells below their own cost rate
        $profit = ExchangeProfitCalculator::calculate(
            ExchangeTransaction::TYPE_SELL,
            '100',
            '9000',
            '100',
            '105',
        );

        $this->assertSame('-1000.0000', $profit);
    }
}
