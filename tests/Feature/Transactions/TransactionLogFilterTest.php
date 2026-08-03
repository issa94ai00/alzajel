<?php

namespace Tests\Feature\Transactions;

use App\Models\Currency;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class TransactionLogFilterTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_filters_by_currency_and_type_and_search(): void
    {
        $cashier = $this->createCashier();
        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $shiftService = app(ShiftService::class);
        $exchangeService = app(ExchangeService::class);

        $shift = $shiftService->openShift($cashier, [$syp->id => '1000000']);

        // Give the cashier some USD to sell.
        \App\Models\ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);

        $exchangeService->recordTransaction($shift, $cashier, [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => '100',
            'customer_name' => 'أحمد',
        ]);

        $exchangeService->recordTransaction($shift, $cashier, [
            'currency_id' => $usd->id,
            'type' => 'buy',
            'amount' => '50',
            'customer_name' => 'خالد',
        ]);

        $byType = $this->actingAs($cashier)->get(route('transactions.index', ['type' => 'sell']));
        $byType->assertInertia(fn ($page) => $page->has('transactions.data', 1));

        $bySearch = $this->actingAs($cashier)->get(route('transactions.index', ['search' => 'خالد']));
        $bySearch->assertInertia(fn ($page) => $page->has('transactions.data', 1));

        $byCurrency = $this->actingAs($cashier)->get(route('transactions.index', ['currency_id' => $usd->id]));
        $byCurrency->assertInertia(fn ($page) => $page->has('transactions.data', 2));
    }
}
