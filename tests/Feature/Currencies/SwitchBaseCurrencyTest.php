<?php

namespace Tests\Feature\Currencies;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\BaseCurrencySwitcher;
use App\Services\ExchangeProfitCalculator;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class SwitchBaseCurrencyTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    private function switcher(): BaseCurrencySwitcher
    {
        return app(BaseCurrencySwitcher::class);
    }

    private function rate(string $code): ?ExchangeRate
    {
        $currency = Currency::where('code', $code)->firstOrFail();

        return ExchangeRate::where('currency_id', $currency->id)->first();
    }

    /**
     * التحويل الحسابي: الأسعار مقوّمة بالأساس القديم فتُقسَم على سعر الهدف.
     * الأساس القديم يصبح عملة متداولة بسعر مقلوب، والهدف يفقد صفّه.
     */
    public function test_convert_mode_rebuilds_rates_around_the_new_base(): void
    {
        $eur = Currency::where('code', 'EUR')->firstOrFail();
        ExchangeRate::create(['currency_id' => $eur->id, 'buy_rate' => 110, 'sell_rate' => 120]);

        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->switcher()->apply($usd, BaseCurrencySwitcher::MODE_CONVERT, $this->createAdmin());

        $this->assertTrue($usd->fresh()->is_base);
        $this->assertFalse(Currency::where('code', 'SYP')->firstOrFail()->is_base);

        // USD صار الأساس فلا سعر له.
        $this->assertNull($this->rate('USD'));

        // SYP كان الأساس: 1/sell=1/105، 1/buy=1/100.
        $this->assertSame('0.0095238095', (string) $this->rate('SYP')->buy_rate);
        $this->assertSame('0.0100000000', (string) $this->rate('SYP')->sell_rate);

        // EUR تقاطعي محافظ: buy=110/105، sell=120/100.
        $this->assertSame('1.0476190476', (string) $this->rate('EUR')->buy_rate);
        $this->assertSame('1.2000000000', (string) $this->rate('EUR')->sell_rate);
    }

    /**
     * انحدار: بأربع خانات كان مقلوب سعر حول 134 يُقصّ إلى 0.0074 شراءً وبيعاً
     * معاً، فيختفي الهامش ويصير ربح كل عملية على العملة صفراً.
     */
    public function test_inverted_rate_keeps_its_spread_at_realistic_rates(): void
    {
        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        // سعر واقعي للدولار مقابل الليرة، بهامش نصف ليرة.
        ExchangeRate::where('currency_id', $usd->id)->update(['buy_rate' => '133.75', 'sell_rate' => '134.25']);

        $this->switcher()->apply($usd, BaseCurrencySwitcher::MODE_CONVERT, $this->createAdmin());

        $sypRate = $this->rate('SYP');

        $this->assertSame('0.0074487895', (string) $sypRate->buy_rate);
        $this->assertSame('0.0074766355', (string) $sypRate->sell_rate);

        // الشرط الجوهري: الهامش ما يزال قائماً بعد القلب.
        $this->assertNotSame(
            (string) $sypRate->buy_rate,
            (string) $sypRate->sell_rate,
            'انهار الهامش بعد تبديل العملة الأساس — عاد عطل الدقّة.',
        );
        $this->assertSame(1, bccomp((string) $sypRate->sell_rate, (string) $sypRate->buy_rate, 10));

        $this->assertSame($syp->id, Currency::where('code', 'SYP')->firstOrFail()->id);
    }

    /** ربح عملية على السعر المقلوب يجب ألّا يكون صفراً. */
    public function test_profit_survives_a_base_currency_switch(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ExchangeRate::where('currency_id', $usd->id)->update(['buy_rate' => '133.75', 'sell_rate' => '134.25']);

        $this->switcher()->apply($usd, BaseCurrencySwitcher::MODE_CONVERT, $this->createAdmin());

        $sypRate = $this->rate('SYP');

        // بيع 100000 ليرة: الربح = الفرق بين السعرين مضروباً بالكمية.
        $profit = ExchangeProfitCalculator::calculate(
            'sell',
            '100000',
            bcmul('100000', (string) $sypRate->sell_rate, 4),
            (string) $sypRate->buy_rate,
            (string) $sypRate->sell_rate,
        );

        $this->assertSame(1, bccomp($profit, '0', 4), "الربح صفر بعد التبديل — انهار الهامش. القيمة: {$profit}");
    }

    /** الهامش يجب أن يبقى في مصلحة الصرافة بعد التحويل. */
    public function test_convert_mode_keeps_sell_above_buy(): void
    {
        $eur = Currency::where('code', 'EUR')->firstOrFail();
        ExchangeRate::create(['currency_id' => $eur->id, 'buy_rate' => 110, 'sell_rate' => 120]);

        $this->switcher()->apply(
            Currency::where('code', 'USD')->firstOrFail(),
            BaseCurrencySwitcher::MODE_CONVERT,
            $this->createAdmin(),
        );

        foreach (['SYP', 'EUR'] as $code) {
            $rate = $this->rate($code);
            $this->assertGreaterThan((float) $rate->buy_rate, (float) $rate->sell_rate, $code);
        }
    }

    /**
     * وضع التصحيح: الأرقام كانت مُدخلة بالعملة الهدف أصلاً، فلا تتغيّر.
     * هذه حالة النظام الحالي حيث الأسعار مكتوبة بالليرة والأساس دولار.
     */
    public function test_relabel_mode_leaves_the_numbers_untouched(): void
    {
        $eur = Currency::where('code', 'EUR')->firstOrFail();
        ExchangeRate::create(['currency_id' => $eur->id, 'buy_rate' => 153.3, 'sell_rate' => 155.1]);

        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->switcher()->apply($usd, BaseCurrencySwitcher::MODE_RELABEL, $this->createAdmin());

        $this->assertTrue($usd->fresh()->is_base);
        $this->assertNull($this->rate('USD'));
        $this->assertSame('153.3000000000', (string) $this->rate('EUR')->buy_rate);
        $this->assertSame('155.1000000000', (string) $this->rate('EUR')->sell_rate);
    }

    /** دفتر الوردية الواحدة لا يجوز أن يُقوَّم بعملتين. */
    public function test_switching_is_blocked_while_a_shift_is_open(): void
    {
        app(ShiftService::class)->openShift($this->createAdmin());

        $blockers = $this->switcher()->blockers(
            Currency::where('code', 'USD')->firstOrFail(),
            BaseCurrencySwitcher::MODE_RELABEL,
        );

        $this->assertNotEmpty($blockers);
        $this->assertStringContainsString('وردية مفتوحة', implode(' ', $blockers));
    }

    public function test_route_requires_the_currencies_permission(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->actingAs($this->createCashier())
            ->post(route('currencies.base', $usd), ['mode' => 'relabel'])
            ->assertForbidden();

        $this->assertFalse($usd->fresh()->is_base);
    }

    public function test_admin_can_switch_the_base_through_the_route(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->actingAs($this->createAdmin())
            ->post(route('currencies.base', $usd), ['mode' => 'relabel'])
            ->assertRedirect(route('currencies.index'));

        $this->assertTrue($usd->fresh()->is_base);
    }

    public function test_index_exposes_a_preview_without_changing_anything(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $response = $this->actingAs($this->createAdmin())
            ->get(route('currencies.index', ['base_preview' => $usd->id, 'mode' => 'convert']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('basePreview.target.code', 'USD')->has('basePreview.rows'));

        // المعاينة قراءة محضة.
        $this->assertFalse($usd->fresh()->is_base);
        $this->assertTrue(Currency::where('code', 'SYP')->firstOrFail()->is_base);
    }
}
