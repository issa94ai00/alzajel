<?php

namespace Tests\Feature\Shifts;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\ShiftMovement;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CashMovementService;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class CashMovementTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_a_deposit_increases_the_drawer_balance(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $response = $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
            'notes' => 'تعزيز من الخزنة الرئيسية',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertBalance($shift, 'USD', '1250.0000');

        $movement = ShiftMovement::firstOrFail();
        $this->assertSame(ShiftMovement::TYPE_DEPOSIT, $movement->type);
        $this->assertSame('250.0000', (string) $movement->amount);
        $this->assertSame($admin->id, $movement->user_id);
        $this->assertSame('تعزيز من الخزنة الرئيسية', $movement->notes);
    }

    public function test_a_withdrawal_decreases_the_drawer_balance(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_WITHDRAWAL,
            'currency_id' => $this->usd()->id,
            'amount' => '400',
        ]);

        $this->assertBalance($shift, 'USD', '600.0000');
    }

    public function test_a_withdrawal_cannot_overdraw_the_drawer(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $response = $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_WITHDRAWAL,
            'currency_id' => $this->usd()->id,
            'amount' => '1500',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertBalance($shift, 'USD', '1000.0000');
        $this->assertSame(0, ShiftMovement::count());
    }

    public function test_the_amount_must_be_positive(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        foreach (['0', '-5'] as $amount) {
            $response = $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
                'type' => ShiftMovement::TYPE_DEPOSIT,
                'currency_id' => $this->usd()->id,
                'amount' => $amount,
            ]);

            $response->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, ShiftMovement::count());
    }

    public function test_movements_are_recorded_in_the_activity_log(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
            'notes' => 'تعزيز',
        ]);

        $log = ActivityLog::where('event', ActivityLogger::SHIFT_DEPOSIT)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame('USD', $log->properties['currency']);
        $this->assertSame('250.0000', $log->properties['amount']);
        // الرصيد بعد الحركة يُحفظ في السجل: يقرأه المدقّق دون إعادة حسابه.
        $this->assertSame('1250.0000', $log->properties['balance_after']);
        $this->assertSame('تعزيز', $log->properties['notes']);
    }

    public function test_a_closed_shift_accepts_no_movements(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        app(ShiftService::class)->closeShift($shift, $admin);

        $response = $this->actingAs($admin)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '100',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ShiftMovement::count());
    }

    public function test_a_cashier_without_the_permission_is_refused(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWith($cashier);

        $response = $this->actingAs($cashier)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '100',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ShiftMovement::count());
    }

    public function test_a_cashier_granted_the_permission_may_deposit_into_their_own_shift(): void
    {
        $cashier = $this->createCashier();
        $cashier->givePermissionTo(Permissions::SHIFTS_DEPOSIT);
        $shift = $this->openShiftWith($cashier);

        $response = $this->actingAs($cashier)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '100',
        ]);

        $response->assertRedirect();
        $this->assertBalance($shift, 'USD', '1100.0000');
    }

    /**
     * بلا SHIFTS_VIEW_ANY لا يطال المستخدمُ ورديةَ غيره — ولا يودع فيها.
     */
    public function test_a_user_cannot_deposit_into_another_users_shift(): void
    {
        $owner = $this->createCashier();
        $shift = $this->openShiftWith($owner);

        $other = $this->createCashier();
        $other->givePermissionTo(Permissions::SHIFTS_DEPOSIT);

        $response = $this->actingAs($other)->post(route('shifts.movements.store', $shift), [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '100',
        ]);

        $response->assertForbidden();
    }

    public function test_deleting_a_movement_reverses_its_effect(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $movement = app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
        ]);

        $this->assertBalance($shift, 'USD', '1250.0000');

        $response = $this->actingAs($admin)->delete(route('movements.destroy', $movement), [
            'reason' => 'أُدخلت بمبلغ خاطئ',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('shift_movements', ['id' => $movement->id]);
        $this->assertBalance($shift, 'USD', '1000.0000');

        $deleted = ShiftMovement::withTrashed()->findOrFail($movement->id);
        $this->assertSame($admin->id, $deleted->deleted_by);
        $this->assertSame('أُدخلت بمبلغ خاطئ', $deleted->deletion_reason);

        $this->assertDatabaseHas('activity_logs', [
            'event' => ActivityLogger::SHIFT_MOVEMENT_DELETED,
            'subject_id' => $movement->id,
        ]);
    }

    public function test_deleting_a_withdrawal_returns_the_money_to_the_drawer(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $movement = app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_WITHDRAWAL,
            'currency_id' => $this->usd()->id,
            'amount' => '300',
        ]);

        $this->assertBalance($shift, 'USD', '700.0000');

        $this->actingAs($admin)->delete(route('movements.destroy', $movement), ['reason' => 'تراجع']);

        $this->assertBalance($shift, 'USD', '1000.0000');
    }

    public function test_deleting_a_movement_requires_a_reason(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $movement = app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
        ]);

        $response = $this->actingAs($admin)->delete(route('movements.destroy', $movement));

        $response->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted('shift_movements', ['id' => $movement->id]);
        $this->assertBalance($shift, 'USD', '1250.0000');
    }

    public function test_restoring_a_movement_reapplies_its_effect(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $movement = app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
        ]);

        $this->actingAs($admin)->delete(route('movements.destroy', $movement), ['reason' => 'خطأ']);
        $this->assertBalance($shift, 'USD', '1000.0000');

        $response = $this->actingAs($admin)->post(route('movements.restore', $movement));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('shift_movements', ['id' => $movement->id]);
        $this->assertBalance($shift, 'USD', '1250.0000');

        $restored = $movement->fresh();
        $this->assertNull($restored->deleted_by);
        $this->assertNull($restored->deletion_reason);
    }

    /**
     * الرصيد الحالي هو ما يُثبَّت عند الإغلاق، فالحركات اليدوية يجب أن تصل
     * إلى رصيد الإغلاق كما تصل إليه عمليات الصرافة.
     */
    public function test_movements_carry_into_the_closing_balance(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
        ]);

        app(ShiftService::class)->closeShift($shift, $admin);

        $account = ShiftAccount::where('shift_id', $shift->id)
            ->where('currency_id', $this->usd()->id)
            ->firstOrFail();

        $this->assertSame('1250.0000', (string) $account->closing_balance);
    }

    public function test_the_shift_page_exposes_movements_and_their_per_currency_totals(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_DEPOSIT,
            'currency_id' => $this->usd()->id,
            'amount' => '250',
        ]);
        app(CashMovementService::class)->record($shift, $admin, [
            'type' => ShiftMovement::TYPE_WITHDRAWAL,
            'currency_id' => $this->usd()->id,
            'amount' => '100',
        ]);

        $response = $this->actingAs($admin)->get(route('shifts.show', $shift));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->where('canRecordMovement', true)
                ->where('movements', fn ($rows) => count($rows) === 2)
                ->where('balances', function ($rows) {
                    $usd = collect($rows)->firstWhere('currency.code', 'USD');

                    return $usd['deposits'] === '250.0000' && $usd['withdrawals'] === '100.0000';
                })
        );
    }

    private function usd(): Currency
    {
        return Currency::where('code', 'USD')->firstOrFail();
    }

    private function openShiftWith(User $user): Shift
    {
        return app(ShiftService::class)->openShift($user, [
            $this->usd()->id => '1000',
            Currency::where('code', 'SYP')->firstOrFail()->id => '500000',
        ]);
    }

    private function assertBalance(Shift $shift, string $code, string $expected): void
    {
        $currency = Currency::where('code', $code)->firstOrFail();

        $account = ShiftAccount::where('shift_id', $shift->id)
            ->where('currency_id', $currency->id)
            ->firstOrFail();

        $this->assertSame($expected, (string) $account->current_balance, "رصيد {$code} غير متوقّع");
    }
}
