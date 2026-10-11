<?php

namespace Tests\Feature\Shifts;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class DeleteShiftTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    /**
     * القفل الفريد shifts_user_open_lock_unique مبنيّ على عمود افتراضي لا يرى
     * deleted_at: وردية مفتوحة محذوفة تبقى حاجزةً صاحبَها عن فتح وردية جديدة.
     */
    public function test_an_open_shift_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $response = $this->actingAs($admin)->delete(route('shifts.destroy', $shift), [
            'reason' => 'محاولة حذف وردية مفتوحة',
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('shifts', ['id' => $shift->id]);
    }

    public function test_deleting_a_closed_shift_also_hides_its_transactions(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->buyUsd($shift, $admin, '10');
        app(ShiftService::class)->closeShift($shift, $admin);

        $response = $this->actingAs($admin)->delete(route('shifts.destroy', $shift), [
            'reason' => 'وردية تجريبية',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $this->assertSoftDeleted('shifts', ['id' => $shift->id]);
        $this->assertSoftDeleted('exchange_transactions', ['id' => $transaction->id]);

        $cascaded = ExchangeTransaction::withTrashed()->findOrFail($transaction->id);
        $this->assertTrue($cascaded->deleted_with_shift);
        $this->assertSame($admin->id, $cascaded->deleted_by);
        $this->assertSame('وردية تجريبية', $cascaded->deletion_reason);

        $deletedShift = Shift::withTrashed()->findOrFail($shift->id);
        $this->assertSame($admin->id, $deletedShift->deleted_by);
        $this->assertSame('وردية تجريبية', $deletedShift->deletion_reason);

        $log = ActivityLog::where('event', ActivityLogger::SHIFT_DELETED)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($shift->id, $log->subject_id);
        $this->assertSame(1, $log->properties['cascaded_transactions']);
        $this->assertSame('وردية تجريبية', $log->properties['reason']);
    }

    public function test_a_reason_is_required(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        app(ShiftService::class)->closeShift($shift, $admin);

        $response = $this->actingAs($admin)->delete(route('shifts.destroy', $shift));

        $response->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted('shifts', ['id' => $shift->id]);
    }

    public function test_a_cashier_without_the_delete_permission_is_refused(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWith($cashier);
        app(ShiftService::class)->closeShift($shift, $cashier);

        $response = $this->actingAs($cashier)->delete(route('shifts.destroy', $shift), [
            'reason' => 'محاولة غير مصرّح بها',
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('shifts', ['id' => $shift->id]);
    }

    /**
     * جوهر عمود deleted_with_shift: استعادة الوردية تُعيد ما حُذف معها فقط،
     * ولا تُحيي عمليةً حذفها المحاسب وحدها عن قصد قبل الإغلاق.
     */
    public function test_restoring_a_shift_brings_back_only_the_transactions_deleted_with_it(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);

        $deletedAlone = $this->buyUsd($shift, $admin, '10');
        $deletedWithShift = $this->buyUsd($shift, $admin, '7');

        $this->actingAs($admin)->delete(route('transactions.destroy', $deletedAlone), [
            'reason' => 'خطأ في الإدخال',
        ]);

        app(ShiftService::class)->closeShift($shift, $admin);

        $this->actingAs($admin)->delete(route('shifts.destroy', $shift), ['reason' => 'أرشفة']);

        $response = $this->actingAs($admin)->post(route('shifts.restore', $shift));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('shifts', ['id' => $shift->id]);
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $deletedWithShift->id]);
        $this->assertSoftDeleted('exchange_transactions', ['id' => $deletedAlone->id]);

        $stillDeleted = ExchangeTransaction::withTrashed()->findOrFail($deletedAlone->id);
        $this->assertSame('خطأ في الإدخال', $stillDeleted->deletion_reason);

        $restoredShift = Shift::findOrFail($shift->id);
        $this->assertNull($restoredShift->deleted_by);
        $this->assertNull($restoredShift->deletion_reason);

        $log = ActivityLog::where('event', ActivityLogger::SHIFT_RESTORED)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->properties['restored_transactions']);
    }

    /**
     * أرصدة الوردية المحذوفة يجب ألّا تُملي رصيد افتتاح الوردية التالية —
     * والاستعلام الذي يجلبها ضمٌّ يدوي لا يمرّ عليه نطاق الحذف الناعم.
     */
    public function test_a_deleted_shift_no_longer_suggests_the_next_opening_balance(): void
    {
        $admin = $this->createAdmin();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $first = app(ShiftService::class)->openShift($admin, [$usd->id => '400']);
        app(ShiftService::class)->closeShift($first, $admin);

        $second = app(ShiftService::class)->openShift($admin, [$usd->id => '900']);
        app(ShiftService::class)->closeShift($second, $admin);

        $this->assertSame('900.0000', app(ShiftService::class)->previousClosingBalances($admin)[$usd->id]);

        $this->actingAs($admin)->delete(route('shifts.destroy', $second), ['reason' => 'أُدخلت بالخطأ']);

        // يرجع الاقتراح إلى الوردية السارية الأحدث، لا إلى المحذوفة.
        $this->assertSame('400.0000', app(ShiftService::class)->previousClosingBalances($admin)[$usd->id]);
    }

    public function test_deleting_a_shift_does_not_block_opening_a_new_one(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        app(ShiftService::class)->closeShift($shift, $admin);

        $this->actingAs($admin)->delete(route('shifts.destroy', $shift), ['reason' => 'أرشفة']);

        $fresh = app(ShiftService::class)->openShift($admin);

        $this->assertTrue($fresh->isOpen());
        $this->assertNotSame($shift->id, $fresh->id);
    }

    public function test_the_index_hides_deleted_shifts_unless_the_trashed_scope_is_asked_for(): void
    {
        $admin = $this->createAdmin();
        $kept = $this->openShiftWith($admin);
        app(ShiftService::class)->closeShift($kept, $admin);

        $removed = app(ShiftService::class)->openShift($admin);
        app(ShiftService::class)->closeShift($removed, $admin);

        $this->actingAs($admin)->delete(route('shifts.destroy', $removed), ['reason' => 'أرشفة']);

        $this->actingAs($admin)->get(route('shifts.index'))->assertInertia(
            fn ($page) => $page->where('shifts.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$kept->id])
                ->where('trashedCount', 1)
        );

        $this->actingAs($admin)->get(route('shifts.index', ['trashed' => 'only']))->assertInertia(
            fn ($page) => $page->where('shifts.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$removed->id])
        );
    }

    private function openShiftWith(User $user): Shift
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $syp = Currency::where('code', 'SYP')->firstOrFail();

        return app(ShiftService::class)->openShift($user, [
            $usd->id => '1000',
            $syp->id => '500000',
        ]);
    }

    private function buyUsd(Shift $shift, User $user, string $amount): ExchangeTransaction
    {
        return app(ExchangeService::class)->recordTransaction($shift, $user, [
            'currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
            'type' => ExchangeTransaction::TYPE_BUY,
            'amount' => $amount,
        ]);
    }
}
