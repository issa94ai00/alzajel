<?php

namespace Tests\Feature\Transactions;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\ShiftAccount;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class RecordTransactionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    private function openShiftWithSypFloat(\App\Models\User $user, string $sypBalance = '1000000'): \App\Models\Shift
    {
        $syp = Currency::where('code', 'SYP')->firstOrFail();

        return app(ShiftService::class)->openShift($user, [$syp->id => $sypBalance]);
    }

    public function test_sell_transaction_updates_both_balances(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $syp = Currency::where('code', 'SYP')->firstOrFail();

        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 500]);

        $response = $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => 100,
        ]);

        $response->assertRedirect(route('transactions.create'));

        $usdAccount = ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->firstOrFail();
        $sypAccount = ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $syp->id)->firstOrFail();

        $this->assertSame('400.0000', (string) $usdAccount->current_balance);
        $this->assertSame('1010500.0000', (string) $sypAccount->current_balance);
    }

    public function test_buy_transaction_updates_both_balances(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $syp = Currency::where('code', 'SYP')->firstOrFail();

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'buy',
            'amount' => 100,
        ]);

        $usdAccount = ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->firstOrFail();
        $sypAccount = ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $syp->id)->firstOrFail();

        $this->assertSame('100.0000', (string) $usdAccount->current_balance);
        $this->assertSame('990000.0000', (string) $sypAccount->current_balance);
    }

    public function test_sell_rejected_when_currency_balance_insufficient(): void
    {
        $cashier = $this->createCashier();
        $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => 100,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, ExchangeTransaction::count());
    }

    public function test_buy_rejected_when_counter_currency_balance_insufficient(): void
    {
        $cashier = $this->createCashier();
        $this->openShiftWithSypFloat($cashier, '0');
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'buy',
            'amount' => 100,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, ExchangeTransaction::count());
    }

    public function test_rate_defaults_from_exchange_rate_table(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 500]);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => 100,
        ]);

        $transaction = ExchangeTransaction::firstOrFail();
        $this->assertSame('105.0000', (string) $transaction->rate);
        $this->assertSame('10500.0000', (string) $transaction->total);
    }

    public function test_manual_rate_override_is_respected_and_reflected_in_profit(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 500]);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => 100,
            'rate' => 103,
        ]);

        $transaction = ExchangeTransaction::firstOrFail();
        $this->assertSame('103.0000', (string) $transaction->rate);
        // profit = total(10300) - amount(100) * reference_buy_rate(100) = 300
        $this->assertSame('300.0000', (string) $transaction->profit);
    }

    public function test_reference_rates_are_always_snapshotted(): void
    {
        $cashier = $this->createCashier();
        $this->openShiftWithSypFloat($cashier);
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'buy',
            'amount' => 50,
        ]);

        $transaction = ExchangeTransaction::firstOrFail();
        $this->assertSame('100.0000', (string) $transaction->reference_buy_rate);
        $this->assertSame('105.0000', (string) $transaction->reference_sell_rate);
    }

    public function test_cannot_record_transaction_without_open_shift(): void
    {
        $cashier = $this->createCashier();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => 100,
        ]);

        $response->assertRedirect(route('shifts.create'));
        $this->assertSame(0, ExchangeTransaction::count());
    }

    public function test_cannot_record_transaction_for_currency_without_rate(): void
    {
        $cashier = $this->createCashier();
        $this->openShiftWithSypFloat($cashier);
        $sar = Currency::where('code', 'SAR')->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $sar->id,
            'type' => 'sell',
            'amount' => 100,
        ]);

        $response->assertSessionHasErrors('currency_id');
        $this->assertSame(0, ExchangeTransaction::count());
    }
}
