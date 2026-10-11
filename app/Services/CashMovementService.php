<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\ShiftMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الإيداع والسحب النقدي على درج الوردية.
 *
 * كل تعديل على الرصيد يجري داخل معاملة واحدة مع قفل صفّ الحساب، لأن الصرّاف
 * قد يسجّل عملية صرافة في اللحظة نفسها التي يعزّز فيها المشرف درجه.
 */
class CashMovementService
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array{currency_id: int, type: string, amount: string, notes?: string|null}  $data
     */
    public function record(Shift $shift, User $actor, array $data): ShiftMovement
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'لا يمكن تسجيل حركة نقدية على وردية مغلقة.',
            ]);
        }

        $currency = Currency::findOrFail($data['currency_id']);
        $type = $data['type'];
        $amount = (string) $data['amount'];

        if (bccomp($amount, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
            ]);
        }

        return DB::transaction(function () use ($shift, $actor, $currency, $type, $amount, $data) {
            $account = $this->lockAccount($shift, $currency, createIfMissing: $type === ShiftMovement::TYPE_DEPOSIT);

            $this->applyDelta(
                $account,
                $currency,
                $type === ShiftMovement::TYPE_DEPOSIT ? $amount : bcmul($amount, '-1', 4),
            );

            $movement = ShiftMovement::create([
                'shift_id' => $shift->id,
                'currency_id' => $currency->id,
                'user_id' => $actor->id,
                'type' => $type,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->activity->log(
                $type === ShiftMovement::TYPE_DEPOSIT
                    ? ActivityLogger::SHIFT_DEPOSIT
                    : ActivityLogger::SHIFT_WITHDRAWAL,
                sprintf(
                    '%s %s %s %s الوردية رقم %d',
                    $movement->typeLabel(),
                    $amount,
                    $currency->code,
                    $type === ShiftMovement::TYPE_DEPOSIT ? 'إلى' : 'من',
                    $shift->id,
                ),
                $movement,
                $this->auditSnapshot($movement, $account),
                $actor,
            );

            return $movement;
        });
    }

    public function delete(ShiftMovement $movement, User $actor, string $reason): ShiftMovement
    {
        $shift = $movement->shift;

        if (! $shift || ! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'movement' => 'لا يمكن حذف حركة من وردية مغلقة — رصيد إغلاقها صار رصيد افتتاح الوردية التالية.',
            ]);
        }

        return DB::transaction(function () use ($movement, $shift, $actor, $reason) {
            $currency = $movement->currency;
            $account = $this->lockAccount($shift, $currency);

            // عكس الأثر: الإيداع المحذوف يخرج من الدرج، والسحب المحذوف يعود إليه.
            $this->applyDelta($account, $currency, bcmul($movement->signedAmount(), '-1', 4));

            $movement->forceFill([
                'deleted_by' => $actor->getKey(),
                'deletion_reason' => $reason,
            ])->save();

            $movement->delete();

            $this->activity->log(
                ActivityLogger::SHIFT_MOVEMENT_DELETED,
                "حذف {$movement->typeLabel()} رقم {$movement->id} من الوردية رقم {$shift->id}",
                $movement,
                $this->auditSnapshot($movement, $account) + ['reason' => $reason],
                $actor,
            );

            return $movement;
        });
    }

    public function restore(ShiftMovement $movement, User $actor): ShiftMovement
    {
        $shift = $movement->shift;

        if (! $shift || ! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'movement' => 'لا يمكن استعادة الحركة إلا إلى وردية مفتوحة.',
            ]);
        }

        return DB::transaction(function () use ($movement, $shift, $actor) {
            $currency = $movement->currency;
            $account = $this->lockAccount($shift, $currency);

            $this->applyDelta($account, $currency, $movement->signedAmount());

            $movement->restore();

            $movement->forceFill([
                'deleted_by' => null,
                'deletion_reason' => null,
            ])->save();

            $this->activity->log(
                ActivityLogger::SHIFT_MOVEMENT_RESTORED,
                "استعادة {$movement->typeLabel()} رقم {$movement->id} إلى الوردية رقم {$shift->id}",
                $movement,
                $this->auditSnapshot($movement, $account),
                $actor,
            );

            return $movement;
        });
    }

    /**
     * مجاميع الإيداع والسحب لكل عملة في وردية — تشرح للمستخدم من أين جاء
     * الفرق بين رصيد الافتتاح والرصيد الحالي.
     *
     * @return array<int, array{deposits: string, withdrawals: string}>
     */
    public function totalsByCurrency(Shift $shift): array
    {
        return ShiftMovement::where('shift_id', $shift->getKey())
            ->get()
            ->groupBy('currency_id')
            ->map(fn ($group) => [
                'deposits' => $this->sum($group->where('type', ShiftMovement::TYPE_DEPOSIT)->pluck('amount')),
                'withdrawals' => $this->sum($group->where('type', ShiftMovement::TYPE_WITHDRAWAL)->pluck('amount')),
            ])
            ->all();
    }

    /**
     * @param  iterable<mixed>  $values
     */
    private function sum(iterable $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, (string) ($value ?? '0'), 4);
        }

        return $total;
    }

    /**
     * يقفل صفّ حساب العملة في هذه الوردية.
     *
     * عملة فُعّلت بعد فتح الوردية لا حساب لها فيها. للإيداع نُنشئ الحساب
     * برصيد افتتاحي صفر بدل أن يصطدم المستخدم بطريق مسدود؛ أما السحب فلا
     * معنى له من حساب غير موجود أصلاً.
     */
    private function lockAccount(Shift $shift, Currency $currency, bool $createIfMissing = false): ShiftAccount
    {
        $account = ShiftAccount::where('shift_id', $shift->getKey())
            ->where('currency_id', $currency->getKey())
            ->lockForUpdate()
            ->first();

        if ($account) {
            return $account;
        }

        if (! $createIfMissing) {
            throw ValidationException::withMessages([
                'currency_id' => "لا يوجد حساب لعملة {$currency->code} في هذه الوردية.",
            ]);
        }

        return ShiftAccount::create([
            'shift_id' => $shift->getKey(),
            'currency_id' => $currency->getKey(),
            'opening_balance' => '0',
            'current_balance' => '0',
        ]);
    }

    private function applyDelta(ShiftAccount $account, Currency $currency, string $delta): void
    {
        $balance = bcadd((string) $account->current_balance, $delta, 4);

        if (bccomp($balance, '0', 4) < 0) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'رصيد %s في الوردية غير كافٍ — المتاح %s فقط.',
                    $currency->code,
                    rtrim(rtrim((string) $account->current_balance, '0'), '.') ?: '0',
                ),
            ]);
        }

        $account->update(['current_balance' => $balance]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(ShiftMovement $movement, ShiftAccount $account): array
    {
        return [
            'shift_id' => $movement->shift_id,
            'type' => $movement->type,
            'currency' => $movement->currency?->code,
            'amount' => (string) $movement->amount,
            'balance_after' => (string) $account->current_balance,
            'notes' => $movement->notes,
        ];
    }
}
