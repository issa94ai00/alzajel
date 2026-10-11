<?php

namespace Tests\Feature\Transactions;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpExchangeData;
use Tests\TestCase;

class DeleteTransactionTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpExchangeData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndCurrencies();
    }

    public function test_deleting_a_transaction_hides_it_and_reverses_the_shift_balances(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->buyUsd($shift, $admin, '10');

        // شراء ١٠ دولار بسعر ١٠٠: الدولار يدخل والليرة تخرج.
        $this->assertBalance($shift, 'USD', '1010.0000');
        $this->assertBalance($shift, 'SYP', '499000.0000');

        $response = $this->actingAs($admin)
            ->from(route('transactions.index'))
            ->delete(route('transactions.destroy', $transaction), ['reason' => 'أُدخلت بمبلغ خاطئ']);

        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
        $this->assertBalance($shift, 'USD', '1000.0000');
        $this->assertBalance($shift, 'SYP', '500000.0000');
    }

    public function test_deletion_records_who_why_and_when_on_the_row_and_in_the_activity_log(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->buyUsd($shift, $admin, '10');

        $this->actingAs($admin)->delete(route('transactions.destroy', $transaction), [
            'reason' => 'عملية مكرّرة',
        ]);

        $deleted = ExchangeTransaction::withTrashed()->findOrFail($transaction->id);
        $this->assertSame($admin->id, $deleted->deleted_by);
        $this->assertSame('عملية مكرّرة', $deleted->deletion_reason);
        $this->assertFalse($deleted->deleted_with_shift);
        $this->assertNotNull($deleted->deleted_at);

        $log = ActivityLog::where('event', ActivityLogger::TRANSACTION_DELETED)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame($transaction->id, $log->subject_id);
        $this->assertSame('عملية مكرّرة', $log->properties['reason']);
        $this->assertSame('USD', $log->properties['currency']);
        $this->assertSame('10.0000', $log->properties['amount']);
    }

    public function test_a_reason_is_required(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->buyUsd($shift, $admin, '10');

        $response = $this->actingAs($admin)->delete(route('transactions.destroy', $transaction));

        $response->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
    }

    /**
     * رصيد إغلاق الوردية المغلقة صار رصيد افتتاح التالية؛ تعديله بأثر رجعي
     * يكسر السلسلة، فالحذف ممنوع بعد الإغلاق.
     */
    public function test_a_transaction_in_a_closed_shift_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->buyUsd($shift, $admin, '10');

        app(ShiftService::class)->closeShift($shift, $admin);

        $response = $this->actingAs($admin)->delete(route('transactions.destroy', $transaction), [
            'reason' => 'محاولة حذف بعد الإغلاق',
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
    }

    public function test_a_cashier_without_the_delete_permission_is_refused(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftWith($cashier);
        $transaction = $this->buyUsd($shift, $cashier, '10');

        $response = $this->actingAs($cashier)->delete(route('transactions.destroy', $transaction), [
            'reason' => 'محاولة غير مصرّح بها',
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
    }

    /**
     * الصلاحية وحدها تكفي — لا حاجة لدور المدير، فالأدوار المخصّصة يجب أن تعمل.
     */
    public function test_a_cashier_granted_the_permission_may_delete_their_own_transaction(): void
    {
        $cashier = $this->createCashier();
        $cashier->givePermissionTo(Permissions::TRANSACTIONS_DELETE);

        $shift = $this->openShiftWith($cashier);
        $transaction = $this->buyUsd($shift, $cashier, '10');

        $response = $this->actingAs($cashier)->delete(route('transactions.destroy', $transaction), [
            'reason' => 'خطأ في الإدخال',
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
    }

    /**
     * بلا TRANSACTIONS_VIEW_ANY_USER لا يطال المستخدمُ عملياتِ غيره — ولا يحذفها.
     */
    public function test_a_user_cannot_delete_another_users_transaction_without_the_oversight_permission(): void
    {
        $owner = $this->createCashier();
        $shift = $this->openShiftWith($owner);
        $transaction = $this->buyUsd($shift, $owner, '10');

        $other = $this->createCashier();
        $other->givePermissionTo(Permissions::TRANSACTIONS_DELETE);

        $response = $this->actingAs($other)->delete(route('transactions.destroy', $transaction), [
            'reason' => 'ليست عمليتي',
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
    }

    public function test_restoring_a_transaction_reapplies_its_effect_on_the_balances(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $transaction = $this->sellUsd($shift, $admin, '10');

        // بيع ١٠ دولار بسعر ١٠٥: الدولار يخرج والليرة تدخل.
        $this->assertBalance($shift, 'USD', '990.0000');
        $this->assertBalance($shift, 'SYP', '501050.0000');

        $this->actingAs($admin)->delete(route('transactions.destroy', $transaction), ['reason' => 'تراجع العميل']);
        $this->assertBalance($shift, 'USD', '1000.0000');
        $this->assertBalance($shift, 'SYP', '500000.0000');

        $response = $this->actingAs($admin)->post(route('transactions.restore', $transaction));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('exchange_transactions', ['id' => $transaction->id]);
        $this->assertBalance($shift, 'USD', '990.0000');
        $this->assertBalance($shift, 'SYP', '501050.0000');

        $restored = $transaction->fresh();
        $this->assertNull($restored->deleted_by);
        $this->assertNull($restored->deletion_reason);

        $this->assertDatabaseHas('activity_logs', [
            'event' => ActivityLogger::TRANSACTION_RESTORED,
            'subject_id' => $transaction->id,
        ]);
    }

    public function test_deleted_transactions_disappear_from_the_log_and_the_reports(): void
    {
        $admin = $this->createAdmin();
        $shift = $this->openShiftWith($admin);
        $kept = $this->buyUsd($shift, $admin, '10');
        $removed = $this->buyUsd($shift, $admin, '7');

        $this->actingAs($admin)->delete(route('transactions.destroy', $removed), ['reason' => 'مكرّرة']);

        $listed = app(ExchangeService::class)->listTransactions();
        $this->assertEqualsCanonicalizing([$kept->id], $listed->pluck('id')->all());

        $this->assertSame(1, $shift->fresh()->exchangeTransactions()->count());

        // ومع نطاق المحذوفات تظهر من جديد، لمن يملك صلاحية الحذف.
        $trashed = app(ExchangeService::class)->listTransactions(['trashed' => 'only']);
        $this->assertEqualsCanonicalizing([$removed->id], $trashed->pluck('id')->all());
    }

    public function test_the_trashed_scope_is_ignored_for_a_user_without_the_delete_permission(): void
    {
        $owner = $this->createAdmin();
        $shift = $this->openShiftWith($owner);
        $removed = $this->buyUsd($shift, $owner, '10');
        $this->actingAs($owner)->delete(route('transactions.destroy', $removed), ['reason' => 'مكرّرة']);

        $cashier = $this->createCashier();

        $response = $this->actingAs($cashier)->get(route('transactions.index', ['trashed' => 'only']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->where('canSeeTrashed', false)
                ->where('transactions.data', [])
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
        return $this->record($shift, $user, ExchangeTransaction::TYPE_BUY, $amount);
    }

    private function sellUsd(Shift $shift, User $user, string $amount): ExchangeTransaction
    {
        return $this->record($shift, $user, ExchangeTransaction::TYPE_SELL, $amount);
    }

    private function record(Shift $shift, User $user, string $type, string $amount): ExchangeTransaction
    {
        return app(ExchangeService::class)->recordTransaction($shift, $user, [
            'currency_id' => Currency::where('code', 'USD')->firstOrFail()->id,
            'type' => $type,
            'amount' => $amount,
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
