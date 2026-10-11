<?php

namespace Tests\Feature\Currencies;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

/**
 * رمز الفئة: سلسلة اختيارية تميّز فئات العملة الواحدة (USD-100، USD-50…)،
 * فتصير لكل فئة صفّها وسعرها ورصيدها. الاختبارات هنا تحرس شرطين:
 * أن يبقى الرمز مميزاً، وأن تُنسخ الفئة على العملية لا أن تُقرأ منها لاحقاً.
 */
class CurrencyTagTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
        $this->admin = $this->createAdmin();
    }

    private function storeCurrency(array $payload = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('currencies.store'), array_merge([
            'code' => 'USD',
            'name' => 'دولار أمريكي — فئة المئة',
            'tag' => 'USD-100',
            'is_active' => true,
        ], $payload));
    }

    public function test_same_code_is_allowed_twice_with_different_tags(): void
    {
        $this->storeCurrency(['tag' => 'USD-100'])->assertSessionHasNoErrors();
        $this->storeCurrency(['tag' => 'USD-50', 'name' => 'دولار أمريكي — فئة الخمسين'])->assertSessionHasNoErrors();

        $this->assertSame(3, Currency::where('code', 'USD')->count());
    }

    public function test_same_code_is_rejected_when_both_rows_have_no_tag(): void
    {
        // العملة USD المزروعة في الإعداد بلا فئة، فإضافة USD أخرى بلا فئة تكرار.
        $this->storeCurrency(['tag' => ''])->assertSessionHasErrors('code');

        $this->assertSame(1, Currency::where('code', 'USD')->count());
    }

    public function test_same_code_is_rejected_when_the_tag_repeats(): void
    {
        $this->storeCurrency(['tag' => 'USD-100'])->assertSessionHasNoErrors();
        $this->storeCurrency(['tag' => 'USD-100'])->assertSessionHasErrors('code');
    }

    public function test_tag_must_be_unique_across_currencies(): void
    {
        $this->storeCurrency(['tag' => 'BIG-NOTE'])->assertSessionHasNoErrors();

        $this->storeCurrency(['code' => 'EUR', 'name' => 'يورو — فئة كبيرة', 'tag' => 'BIG-NOTE'])
            ->assertSessionHasErrors('tag');
    }

    public function test_blank_tag_is_stored_as_null(): void
    {
        $this->actingAs($this->admin)->post(route('currencies.store'), [
            'code' => 'GBP',
            'name' => 'جنيه إسترليني',
            'tag' => '   ',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Currency::where('code', 'GBP')->firstOrFail()->tag);
    }

    public function test_editing_a_currency_keeps_its_own_tag_valid(): void
    {
        $this->storeCurrency(['tag' => 'USD-100'])->assertSessionHasNoErrors();

        $currency = Currency::where('tag', 'USD-100')->firstOrFail();

        $this->actingAs($this->admin)->put(route('currencies.update', $currency), [
            'code' => 'USD',
            'name' => 'دولار أمريكي — فئة المئة (معدّل)',
            'tag' => 'USD-100',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
    }

    /* ------------------------- الفئة على العملية ------------------------- */

    private function shiftFor(User $cashier): Shift
    {
        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $shift = app(ShiftService::class)->openShift($cashier, [$syp->id => '1000000']);
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);

        return $shift;
    }

    public function test_transaction_falls_back_to_the_currency_tag_when_none_is_typed(): void
    {
        Currency::where('code', 'USD')->update(['tag' => 'USD-100']);

        $cashier = $this->createCashier();
        $this->shiftFor($cashier);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
            'type' => 'sell',
            'amount' => '10',
        ])->assertSessionHasNoErrors();

        $this->assertSame('USD-100', ExchangeTransaction::latest('id')->firstOrFail()->currency_tag);
    }

    public function test_typed_tag_overrides_the_currency_tag(): void
    {
        Currency::where('code', 'USD')->update(['tag' => 'USD-100']);

        $cashier = $this->createCashier();
        $this->shiftFor($cashier);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
            'type' => 'sell',
            'amount' => '10',
            'currency_tag' => 'ورقة مهترئة',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ورقة مهترئة', ExchangeTransaction::latest('id')->firstOrFail()->currency_tag);
    }

    public function test_transaction_tag_stays_null_when_neither_side_provides_one(): void
    {
        $cashier = $this->createCashier();
        $this->shiftFor($cashier);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
            'type' => 'sell',
            'amount' => '10',
        ])->assertSessionHasNoErrors();

        $this->assertNull(ExchangeTransaction::latest('id')->firstOrFail()->currency_tag);
    }

    /**
     * فئتان بنفس الرمز صفّان مستقلان تماماً: سعر لكلٍّ ورصيد لكلٍّ، وإلا
     * لم يكن لفصلهما معنى.
     */
    public function test_two_tags_of_the_same_code_keep_separate_rates_and_balances(): void
    {
        $this->storeCurrency(['tag' => 'USD-50', 'name' => 'دولار — فئة الخمسين'])->assertSessionHasNoErrors();

        $hundred = Currency::where('code', 'USD')->whereNull('tag')->firstOrFail();
        $fifty = Currency::where('tag', 'USD-50')->firstOrFail();

        ExchangeRate::create(['currency_id' => $fifty->id, 'buy_rate' => 95, 'sell_rate' => 99]);

        $cashier = $this->createCashier();
        $shift = $this->shiftFor($cashier);
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $fifty->id)->update(['current_balance' => 500]);

        $this->actingAs($cashier)->post(route('transactions.store'), [
            'currency_id' => $fifty->id,
            'type' => 'sell',
            'amount' => '10',
        ])->assertSessionHasNoErrors();

        // البيع خصم من رصيد فئة الخمسين وحدها، وبسعرها هي.
        $this->assertSame('490.0000', ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $fifty->id)->firstOrFail()->current_balance);
        $this->assertSame('1000.0000', ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $hundred->id)->firstOrFail()->current_balance);
        $this->assertSame('99.0000000000', ExchangeTransaction::latest('id')->firstOrFail()->rate);
    }
}
