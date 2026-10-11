<?php

namespace Tests\Feature\Shifts;

use App\Models\Currency;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class OpenShiftTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_cashier_can_open_shift_when_none_open(): void
    {
        $cashier = $this->createCashier();

        $response = $this->actingAs($cashier)->post(route('shifts.store'), []);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(1, Shift::open()->count());
    }

    public function test_opening_balance_defaults_to_zero_for_first_shift(): void
    {
        $cashier = $this->createCashier();

        $this->actingAs($cashier)->post(route('shifts.store'), []);

        $shift = Shift::open()->firstOrFail();

        foreach ($shift->shiftAccounts as $account) {
            $this->assertSame('0.0000', (string) $account->opening_balance);
        }
    }

    public function test_each_user_gets_their_own_concurrent_shift(): void
    {
        $admin = $this->createAdmin();
        app(ShiftService::class)->openShift($admin);

        $cashier = $this->createCashier();
        $response = $this->actingAs($cashier)->post(route('shifts.store'), []);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(2, Shift::open()->count());
        $this->assertSame(1, Shift::open()->where('opened_by', $cashier->id)->count());
    }

    public function test_user_cannot_open_a_second_shift_for_themselves(): void
    {
        $cashier = $this->createCashier();
        app(ShiftService::class)->openShift($cashier);

        // الصرّاف يملك إغلاق ورديته الخاصة، فالطلب يغلق القديمة ويفتح جديدة
        // بدل أن يُرفض — المهم أن يبقى له وردية مفتوحة واحدة لا اثنتان.
        $firstShift = Shift::open()->where('opened_by', $cashier->id)->firstOrFail();

        $response = $this->actingAs($cashier)->post(route('shifts.store'), []);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('closed', $firstShift->fresh()->status);
        $this->assertSame(1, Shift::open()->where('opened_by', $cashier->id)->count());
    }

    public function test_opening_a_second_shift_is_rejected_at_the_service_level(): void
    {
        $admin = $this->createAdmin();
        app(ShiftService::class)->openShift($admin);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ShiftService::class)->openShift($admin);
    }

    public function test_admin_closing_current_and_opening_new_replaces_the_open_shift(): void
    {
        $admin = $this->createAdmin();
        $firstShift = app(ShiftService::class)->openShift($admin);

        $response = $this->actingAs($admin)->post(route('shifts.store'), []);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('closed', $firstShift->fresh()->status);
        $this->assertSame(1, Shift::open()->where('opened_by', $admin->id)->count());
        $this->assertNotSame($firstShift->id, Shift::open()->where('opened_by', $admin->id)->firstOrFail()->id);
    }

    public function test_opening_balance_prefills_from_previous_closed_shift(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $shiftService = app(ShiftService::class);
        $firstShift = $shiftService->openShift($admin);

        ShiftAccount::where('shift_id', $firstShift->id)
            ->where('currency_id', $usd->id)
            ->update(['current_balance' => 500]);

        $shiftService->closeShift($firstShift, $admin);

        $secondShift = $shiftService->openShift($admin);

        $usdAccount = $secondShift->shiftAccounts()->where('currency_id', $usd->id)->firstOrFail();
        $this->assertSame('500.0000', (string) $usdAccount->opening_balance);
    }

    /**
     * عند استبدال وردية مفتوحة، الرقم المقترح هو رصيدها الحالي — لا رصيد إغلاق
     * وردية أقدم. اقتراح الرقم القديم كان يفتح الوردية الجديدة بفارق صامت يساوي
     * كل ما جرى في الوردية الجارية.
     */
    public function test_create_screen_suggests_the_open_shift_balances_not_an_older_closed_one(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $shiftService = app(ShiftService::class);

        // وردية أقدم أُغلقت على 1000.
        $old = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $old->id)->where('currency_id', $usd->id)->update(['current_balance' => 1000]);
        $shiftService->closeShift($old, $admin);

        // الوردية الجارية انخفض رصيدها إلى 600 بعد عمليات اليوم.
        $current = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $current->id)->where('currency_id', $usd->id)->update(['current_balance' => 600]);

        $response = $this->actingAs($admin)->get(route('shifts.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('balanceSource', 'current_shift')
            ->where("previousBalances.{$usd->id}", '600.0000')
        );
    }

    public function test_create_screen_suggests_previous_closing_balances_when_no_shift_is_open(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $shiftService = app(ShiftService::class);

        $old = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $old->id)->where('currency_id', $usd->id)->update(['current_balance' => 750]);
        $shiftService->closeShift($old, $admin);

        $response = $this->actingAs($admin)->get(route('shifts.create'));

        $response->assertInertia(fn ($page) => $page
            ->where('balanceSource', 'previous_shift')
            ->where("previousBalances.{$usd->id}", '750.0000')
        );
    }

    /**
     * حقل مُفرَّغ يعني صفراً. كان الرصيد السابق يعود مكانه بصمت، فيفتح الصرّاف
     * وردية برصيد ظنّ أنه صفّره.
     */
    public function test_explicitly_empty_opening_balance_means_zero(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $shiftService = app(ShiftService::class);

        $old = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $old->id)->where('currency_id', $usd->id)->update(['current_balance' => 900]);
        $shiftService->closeShift($old, $admin);

        $this->actingAs($admin)->post(route('shifts.store'), [
            'opening_balances' => [$usd->id => null],
        ])->assertSessionHasNoErrors();

        $account = Shift::open()->firstOrFail()->shiftAccounts()->where('currency_id', $usd->id)->firstOrFail();
        $this->assertSame('0.0000', (string) $account->opening_balance);
    }

    /**
     * العملات الغائبة عن الحمولة تُبقي سلوك الرجوع إلى رصيد الإغلاق السابق —
     * وهو ما تعتمد عليه الحمولات الجزئية القادمة من التطبيق المحمول.
     */
    public function test_omitted_currency_still_falls_back_to_previous_closing_balance(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $shiftService = app(ShiftService::class);

        $old = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $old->id)->where('currency_id', $usd->id)->update(['current_balance' => 900]);
        $shiftService->closeShift($old, $admin);

        $this->actingAs($admin)->post(route('shifts.store'), ['opening_balances' => []])
            ->assertSessionHasNoErrors();

        $account = Shift::open()->firstOrFail()->shiftAccounts()->where('currency_id', $usd->id)->firstOrFail();
        $this->assertSame('900.0000', (string) $account->opening_balance);
    }

    /** رصيد عملة عُطّلت لا ينتقل للوردية الجديدة، فيُعرض تنبيهاً قبل الفتح. */
    public function test_create_screen_reports_balances_stranded_on_deactivated_currencies(): void
    {
        $admin = $this->createAdmin();
        $eur = Currency::where('code', 'EUR')->firstOrFail();
        $shiftService = app(ShiftService::class);

        $current = $shiftService->openShift($admin);
        ShiftAccount::where('shift_id', $current->id)->where('currency_id', $eur->id)->update(['current_balance' => 320]);

        $eur->update(['is_active' => false]);

        $this->actingAs($admin)->get(route('shifts.create'))->assertInertia(fn ($page) => $page
            ->has('strandedBalances', 1)
            ->where('strandedBalances.0.code', 'EUR')
            ->where('strandedBalances.0.balance', '320.0000')
        );
    }
}
