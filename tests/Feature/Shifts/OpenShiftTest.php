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

    public function test_cashier_cannot_open_shift_when_one_already_open(): void
    {
        $admin = $this->createAdmin();
        app(ShiftService::class)->openShift($admin);

        $cashier = $this->createCashier();
        $response = $this->actingAs($cashier)->post(route('shifts.store'), []);

        $response->assertForbidden();
        $this->assertSame(1, Shift::open()->count());
    }

    public function test_admin_closing_current_and_opening_new_replaces_the_open_shift(): void
    {
        $admin = $this->createAdmin();
        $firstShift = app(ShiftService::class)->openShift($admin);

        $response = $this->actingAs($admin)->post(route('shifts.store'), []);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('closed', $firstShift->fresh()->status);
        $this->assertSame(1, Shift::open()->count());
        $this->assertNotSame($firstShift->id, Shift::open()->firstOrFail()->id);
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
}
