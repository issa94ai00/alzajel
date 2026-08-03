<?php

namespace Tests\Feature\Shifts;

use App\Models\Currency;
use App\Models\ShiftAccount;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class CloseShiftTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_admin_can_close_shift(): void
    {
        $admin = $this->createAdmin();
        $shift = app(ShiftService::class)->openShift($admin);

        $response = $this->actingAs($admin)->post(route('shifts.close', $shift));

        $response->assertRedirect(route('shifts.show', $shift));
        $this->assertSame('closed', $shift->fresh()->status);
    }

    public function test_cashier_cannot_close_shift(): void
    {
        $admin = $this->createAdmin();
        $shift = app(ShiftService::class)->openShift($admin);

        $cashier = $this->createCashier();
        $response = $this->actingAs($cashier)->post(route('shifts.close', $shift));

        $response->assertForbidden();
        $this->assertSame('open', $shift->fresh()->status);
    }

    public function test_closing_snapshots_current_balance_as_closing_balance(): void
    {
        $admin = $this->createAdmin();
        $shift = app(ShiftService::class)->openShift($admin);

        $usd = Currency::where('code', 'USD')->firstOrFail();
        ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->update(['current_balance' => 250]);

        $this->actingAs($admin)->post(route('shifts.close', $shift));

        $usdAccount = ShiftAccount::where('shift_id', $shift->id)->where('currency_id', $usd->id)->firstOrFail();
        $this->assertSame('250.0000', (string) $usdAccount->closing_balance);
    }

    public function test_cannot_close_an_already_closed_shift(): void
    {
        $admin = $this->createAdmin();
        $shift = app(ShiftService::class)->openShift($admin);
        app(ShiftService::class)->closeShift($shift, $admin);

        $response = $this->actingAs($admin)->post(route('shifts.close', $shift));

        $response->assertSessionHasErrors('shift');
    }
}
