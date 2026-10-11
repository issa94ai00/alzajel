<?php

namespace Tests\Feature\Receipts;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use App\Support\SettingsRepository;
use Database\Seeders\SystemDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class PrintReceiptTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    private User $cashier;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
        $this->seed(SystemDefaultsSeeder::class);

        $this->cashier = $this->createCashier();

        $syp = Currency::where('code', 'SYP')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $this->shift = app(ShiftService::class)->openShift($this->cashier, [$syp->id => '1000000']);
        ShiftAccount::where('shift_id', $this->shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);
    }

    private function transaction(array $overrides = []): ExchangeTransaction
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        return app(ExchangeService::class)->recordTransaction($this->shift, $this->cashier, array_merge([
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => '100',
            'customer_name' => 'سامر',
            'customer_phone' => '0955555555',
        ], $overrides));
    }

    public function test_transaction_receipt_uses_the_default_template(): void
    {
        $transaction = $this->transaction();

        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction));

        $response->assertOk();
        $response->assertSee('إيصال عملية صرف', false);
        $response->assertSee('TX-'.$transaction->id, false);
        $response->assertSee('سامر', false);
    }

    public function test_receipt_template_can_be_chosen_per_print(): void
    {
        $transaction = $this->transaction();

        $response = $this->actingAs($this->cashier)
            ->get(route('transactions.receipt', ['transaction' => $transaction, 'template' => 'invoice-a5']));

        $response->assertOk();
        $response->assertSee('فاتورة صرف عملات', false);
        // قالب A5 مضبوط على نسختين
        $response->assertSee('نسخة 2 من 2', false);
    }

    /**
     * الإيصال يحمل سطراً يشرح للعميل أن مبالغ الليرة بالعملة الجديدة،
     * ما دامت العملية تذكر الليرة أصلاً.
     */
    public function test_receipt_explains_the_new_lira(): void
    {
        $transaction = $this->transaction();

        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction));

        $response->assertOk();
        $response->assertSee('مبالغ الليرة السورية بالعملة الجديدة', false);
        $response->assertSee('100 ل.س قديمة = 1 ل.س ج', false);
    }

    public function test_new_lira_note_disappears_when_the_setting_is_off(): void
    {
        app(SettingsRepository::class)->set('currency.new_lira_notice', false);

        $transaction = $this->transaction();

        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction));

        $response->assertOk();
        $response->assertDontSee('مبالغ الليرة السورية بالعملة الجديدة', false);
    }

    public function test_receipt_content_follows_the_settings(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->setMany([
            'receipts.show_customer' => false,
            'receipts.show_rate' => false,
            'company.name' => 'صرافة الشام',
            'receipts.number_prefix' => 'INV-',
        ]);

        $transaction = $this->transaction();

        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction));

        $response->assertOk();
        $response->assertSee('صرافة الشام', false);
        $response->assertSee('INV-'.$transaction->id, false);
        $response->assertDontSee('سامر', false);
        $response->assertDontSee('سعر الصرف', false);
    }

    public function test_shift_receipt_lists_currency_balances(): void
    {
        $this->transaction();

        $response = $this->actingAs($this->cashier)->get(route('shifts.receipt', $this->shift));

        $response->assertOk();
        $response->assertSee('كشف إغلاق وردية', false);
        $response->assertSee('أرصدة العملات', false);
        $response->assertSee('USD', false);
    }

    public function test_receipts_can_be_disabled_from_settings(): void
    {
        app(SettingsRepository::class)->set('receipts.enabled', false);

        $transaction = $this->transaction();

        $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction))->assertNotFound();
        $this->actingAs($this->cashier)->get(route('shifts.receipt', $this->shift))->assertNotFound();
    }

    private function sell(array $payload = []): TestResponse
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();

        return $this->actingAs($this->cashier)->post(route('transactions.store'), array_merge([
            'currency_id' => $usd->id,
            'type' => 'sell',
            'amount' => '10',
        ], $payload));
    }

    public function test_receipt_opens_automatically_after_a_transaction_when_enabled(): void
    {
        app(SettingsRepository::class)->setMany([
            'receipts.enabled' => true,
            'receipts.auto_open_after_transaction' => true,
        ]);

        $response = $this->sell();

        $response->assertRedirect(route('transactions.create'));
        $response->assertSessionHas('receipt', fn ($receipt) => $receipt['auto'] === true && str_contains($receipt['url'], 'receipt'));
    }

    /**
     * افتراضياً لا تُفتح نافذة تلقائياً، لكن الإيصال يُعرض على الواجهة لتسأل
     * الصرّاف عن طباعته — وهذا ما يميّزه عن حالة تعطيل الإيصالات كلياً.
     */
    public function test_transaction_offers_the_receipt_for_printing_by_default(): void
    {
        $response = $this->sell();

        $response->assertSessionHas('receipt', fn ($receipt) => $receipt['auto'] === false && ! empty($receipt['url']));
    }

    public function test_no_receipt_is_offered_when_receipts_are_disabled(): void
    {
        app(SettingsRepository::class)->set('receipts.enabled', false);

        $this->sell()->assertSessionMissing('receipt');
    }

    /**
     * الفئة تُنسخ على العملية، فتبقى على الإيصال حتى لو عُدّلت العملة لاحقاً.
     */
    public function test_receipt_prints_the_currency_tag_recorded_with_the_transaction(): void
    {
        $transaction = $this->transaction(['currency_tag' => 'USD-100']);

        Currency::where('code', 'USD')->update(['tag' => 'USD-50']);

        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $transaction));

        $response->assertOk();
        $response->assertSee('الفئة', false);
        $response->assertSee('USD-100', false);
        $response->assertDontSee('USD-50', false);
    }

    public function test_receipt_omits_the_category_line_when_no_tag_is_recorded(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('transactions.receipt', $this->transaction()));

        $response->assertOk();
        $response->assertDontSee('الفئة', false);
    }
}
