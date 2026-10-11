<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeService
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array{currency_id: int, type: string, amount: string, rate?: string|null, currency_tag?: string|null, customer_name?: string|null, customer_phone?: string|null, notes?: string|null}  $data
     */
    public function recordTransaction(Shift $shift, User $user, array $data): ExchangeTransaction
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'لا يمكن تسجيل عملية على وردية مغلقة.',
            ]);
        }

        $currency = Currency::findOrFail($data['currency_id']);
        $counterCurrency = Currency::where('is_base', true)->firstOrFail();

        if ($currency->id === $counterCurrency->id) {
            throw ValidationException::withMessages([
                'currency_id' => 'لا يمكن اختيار العملة الأساسية كعملة متداولة.',
            ]);
        }

        $rateRow = ExchangeRate::where('currency_id', $currency->id)->first();

        if (! $rateRow) {
            throw ValidationException::withMessages([
                'currency_id' => "لم يتم تحديد سعر صرف لعملة {$currency->code} بعد.",
            ]);
        }

        $type = $data['type'];
        $amount = (string) $data['amount'];
        $rate = (string) ($data['rate'] ?? ($type === ExchangeTransaction::TYPE_BUY ? $rateRow->buy_rate : $rateRow->sell_rate));
        $total = bcmul($amount, $rate, 4);

        $profit = ExchangeProfitCalculator::calculate(
            $type,
            $amount,
            $total,
            (string) $rateRow->buy_rate,
            (string) $rateRow->sell_rate,
        );

        return DB::transaction(function () use ($shift, $user, $currency, $counterCurrency, $rateRow, $type, $amount, $rate, $total, $profit, $data) {
            [$currencyAccount, $counterAccount] = $this->lockShiftAccounts($shift, $currency->id, $counterCurrency->id);

            if ($type === ExchangeTransaction::TYPE_SELL) {
                if (bccomp((string) $currencyAccount->current_balance, $amount, 4) < 0) {
                    throw ValidationException::withMessages([
                        'amount' => "رصيد {$currency->code} في الوردية غير كافٍ لإتمام هذه العملية.",
                    ]);
                }

                $currencyAccount->update(['current_balance' => bcsub((string) $currencyAccount->current_balance, $amount, 4)]);
                $counterAccount->update(['current_balance' => bcadd((string) $counterAccount->current_balance, $total, 4)]);
            } else {
                if (bccomp((string) $counterAccount->current_balance, $total, 4) < 0) {
                    throw ValidationException::withMessages([
                        'amount' => "رصيد {$counterAccount->currency->code} في الوردية غير كافٍ لإتمام هذه العملية.",
                    ]);
                }

                $currencyAccount->update(['current_balance' => bcadd((string) $currencyAccount->current_balance, $amount, 4)]);
                $counterAccount->update(['current_balance' => bcsub((string) $counterAccount->current_balance, $total, 4)]);
            }

            return ExchangeTransaction::create([
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'type' => $type,
                'currency_id' => $currency->id,
                'counter_currency_id' => $counterCurrency->id,
                // ما يكتبه الصرّاف يغلب رمز الفئة المسجّل، وعند تركه فارغاً
                // نثبّت رمز العملة نفسها حتى لا يخرج إيصال بلا فئة.
                'currency_tag' => $this->resolveCurrencyTag($data['currency_tag'] ?? null, $currency),
                'amount' => $amount,
                'rate' => $rate,
                'total' => $total,
                'reference_buy_rate' => $rateRow->buy_rate,
                'reference_sell_rate' => $rateRow->sell_rate,
                'profit' => $profit,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    private function resolveCurrencyTag(?string $provided, Currency $currency): ?string
    {
        $tag = trim((string) $provided);

        return $tag !== '' ? $tag : $currency->tag;
    }

    /**
     * حذف ناعم لعملية صرافة مع عكس أثرها على صندوق الوردية.
     *
     * إخفاء السطر وحده لا يكفي: المال الذي حرّكته العملية ما يزال في
     * current_balance، فيبقى الدرج معلناً رصيداً لا يقابله سجل.
     */
    public function deleteTransaction(ExchangeTransaction $transaction, User $actor, string $reason): ExchangeTransaction
    {
        $shift = $transaction->shift;

        if (! $shift || ! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'transaction' => 'لا يمكن حذف عملية من وردية مغلقة — رصيد إغلاقها صار رصيد افتتاح الوردية التالية.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $shift, $actor, $reason) {
            $this->shiftBalanceEffect($shift, $transaction, reverse: true);

            $transaction->forceFill([
                'deleted_by' => $actor->getKey(),
                'deletion_reason' => $reason,
                // حذف مستقل: استعادة الوردية لاحقاً يجب ألّا تُحييه.
                'deleted_with_shift' => false,
            ])->save();

            $transaction->delete();

            $this->activity->log(
                ActivityLogger::TRANSACTION_DELETED,
                "حذف العملية رقم {$transaction->id} من الوردية رقم {$shift->id}",
                $transaction,
                $this->auditSnapshot($transaction) + ['reason' => $reason],
                $actor,
            );

            return $transaction;
        });
    }

    /**
     * استعادة عملية محذوفة وإعادة تطبيق أثرها على أرصدة الوردية.
     */
    public function restoreTransaction(ExchangeTransaction $transaction, User $actor): ExchangeTransaction
    {
        if ($transaction->deleted_with_shift) {
            throw ValidationException::withMessages([
                'transaction' => 'هذه العملية حُذفت ضمن حذف ورديتها — استعِد الوردية لتعود معها.',
            ]);
        }

        // العلاقة تُخفي الوردية المحذوفة، فيصبح null هنا معناه: وردية محذوفة.
        $shift = $transaction->shift;

        if (! $shift || ! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'transaction' => 'لا يمكن استعادة العملية إلا إلى وردية مفتوحة.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $shift, $actor) {
            $this->shiftBalanceEffect($shift, $transaction, reverse: false);

            $transaction->restore();

            $transaction->forceFill([
                'deleted_by' => null,
                'deletion_reason' => null,
                'deleted_with_shift' => false,
            ])->save();

            $this->activity->log(
                ActivityLogger::TRANSACTION_RESTORED,
                "استعادة العملية رقم {$transaction->id} إلى الوردية رقم {$shift->id}",
                $transaction,
                $this->auditSnapshot($transaction),
                $actor,
            );

            return $transaction;
        });
    }

    /**
     * صورة العملية وقت الحذف — يقرأها المدقّق من سجل النشاط دون الرجوع للجدول.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(ExchangeTransaction $transaction): array
    {
        return [
            'shift_id' => $transaction->shift_id,
            'user_id' => $transaction->user_id,
            'type' => $transaction->type,
            'currency' => $transaction->currency?->code,
            'currency_tag' => $transaction->currency_tag,
            'amount' => (string) $transaction->amount,
            'rate' => (string) $transaction->rate,
            'total' => (string) $transaction->total,
            'profit' => (string) $transaction->profit,
            'customer_name' => $transaction->customer_name,
            'recorded_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * يطبّق أثر العملية على حسابَي الوردية، أو يعكسه.
     *
     * البيع يُخرج العملة ويُدخل ما يقابلها بالأساسية، والشراء بالعكس؛ ومع
     * reverse تنقلب الإشارتان.
     */
    private function shiftBalanceEffect(Shift $shift, ExchangeTransaction $transaction, bool $reverse): void
    {
        [$currencyAccount, $counterAccount] = $this->lockShiftAccounts(
            $shift,
            $transaction->currency_id,
            $transaction->counter_currency_id,
        );

        if (! $currencyAccount || ! $counterAccount) {
            throw ValidationException::withMessages([
                'transaction' => 'أحد حسابات العملة غير موجود في هذه الوردية، فتعذّر تعديل الأرصدة.',
            ]);
        }

        $direction = $reverse ? -1 : 1;
        $isSell = $transaction->type === ExchangeTransaction::TYPE_SELL;

        $this->applyDelta(
            $currencyAccount,
            bcmul((string) $transaction->amount, (string) (($isSell ? -1 : 1) * $direction), 4),
            $reverse,
        );

        $this->applyDelta(
            $counterAccount,
            bcmul((string) $transaction->total, (string) (($isSell ? 1 : -1) * $direction), 4),
            $reverse,
        );
    }

    private function applyDelta(ShiftAccount $account, string $delta, bool $reverse): void
    {
        $balance = bcadd((string) $account->current_balance, $delta, 4);

        if (bccomp($balance, '0', 4) < 0) {
            $code = $account->currency->code;

            throw ValidationException::withMessages([
                'transaction' => $reverse
                    ? "عكس أثر هذه العملية يجعل رصيد {$code} في الوردية سالباً — يبدو أن عمليات لاحقة اعتمدت عليه."
                    : "رصيد {$code} في الوردية لا يكفي لإعادة تطبيق هذه العملية.",
            ]);
        }

        $account->update(['current_balance' => $balance]);
    }

    /**
     * @return array{0: ?ShiftAccount, 1: ?ShiftAccount}
     */
    private function lockShiftAccounts(Shift $shift, int $currencyId, int $counterCurrencyId): array
    {
        $ids = collect([$currencyId, $counterCurrencyId])->sort()->values();

        $accounts = ShiftAccount::where('shift_id', $shift->id)
            ->whereIn('currency_id', $ids)
            ->orderBy('currency_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('currency_id');

        return [$accounts->get($currencyId), $accounts->get($counterCurrencyId)];
    }

    /**
     * @param  array{shift_id?: int, currency_id?: int, type?: string, date_from?: string, date_to?: string, search?: string, user_id?: int, trashed?: string}  $filters
     */
    public function listTransactions(array $filters = []): LengthAwarePaginator
    {
        return ExchangeTransaction::query()
            ->with([
                'currency',
                'counterCurrency',
                'user',
                // عملية حُذفت مع ورديتها تفقد وردَيتها من العلاقة ما لم نضمّ المحذوف.
                'shift' => fn ($q) => $q->withTrashed(),
                'deletedBy' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            ])
            // بلا نطاق صريح تُعرض السارية وحدها — الحذف يُخفي فعلاً.
            ->trashedFilter($filters['trashed'] ?? null)
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['shift_id'] ?? null, fn (Builder $q, $v) => $q->where('shift_id', $v))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $v) => $q->where('currency_id', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $q) use ($v) {
                $q->where('customer_name', 'like', "%{$v}%")
                    ->orWhere('customer_phone', 'like', "%{$v}%")
                    ->orWhere('currency_tag', 'like', "%{$v}%")
                    ->orWhere('notes', 'like', "%{$v}%");
            }))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();
    }
}
