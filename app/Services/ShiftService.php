<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * الوردية المفتوحة للمستخدم المحدد (أو للمستخدم الحالي افتراضياً).
     *
     * لكل مستخدم صندوقه النقدي الخاص، فلا وجود بعد اليوم لوردية "النظام" الوحيدة.
     */
    public function getCurrentOpenShift(?User $user = null): ?Shift
    {
        $user ??= Auth::user();

        if (! $user) {
            return null;
        }

        return Shift::open()->where('opened_by', $user->getKey())->first();
    }

    /**
     * كل الورديات المفتوحة حالياً في النظام — لشاشات الإشراف.
     *
     * @return Collection<int, Shift>
     */
    public function openShifts(): Collection
    {
        return Shift::open()->with('openedBy')->orderBy('opened_at')->get();
    }

    /**
     * @param  array<int, string>  $openingBalances  currency_id => opening balance
     */
    public function openShift(User $user, array $openingBalances = []): Shift
    {
        if ($this->getCurrentOpenShift($user)) {
            throw ValidationException::withMessages([
                'shift' => 'لديك وردية مفتوحة بالفعل — أغلقها قبل فتح وردية جديدة.',
            ]);
        }

        try {
            $shift = DB::transaction(function () use ($user, $openingBalances) {
                $shift = Shift::create([
                    'opened_by' => $user->id,
                    'status' => 'open',
                    'business_date' => now()->toDateString(),
                    'opened_at' => now(),
                ]);

                foreach (Currency::where('is_active', true)->get() as $currency) {
                    /*
                     * مفتاح موجود = قرار صريح من المستخدم، ويُحترم حتى لو كان فارغاً
                     * (الحقل المُفرَّغ يعني صفراً). غيابُه وحده هو ما يسمح بالرجوع
                     * إلى رصيد الإغلاق السابق — وإلا صار تفريغ الحقل يُعيد رصيداً
                     * قديماً بصمت بدل أن يصفّره.
                     */
                    $opening = array_key_exists($currency->id, $openingBalances)
                        ? ($openingBalances[$currency->id] ?? '0')
                        : ($this->previousClosingBalance($user, $currency->id) ?? '0');

                    if ($opening === '') {
                        $opening = '0';
                    }

                    ShiftAccount::create([
                        'shift_id' => $shift->id,
                        'currency_id' => $currency->id,
                        'opening_balance' => $opening,
                        'current_balance' => $opening,
                    ]);
                }

                return $shift;
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'shift' => 'لديك وردية مفتوحة بالفعل — أغلقها قبل فتح وردية جديدة.',
            ]);
        }

        $this->activity->log(
            ActivityLogger::SHIFT_OPENED,
            "فتح الوردية رقم {$shift->id}",
            $shift,
            ['business_date' => $shift->business_date->toDateString()],
            $user,
        );

        return $shift;
    }

    public function closeShift(Shift $shift, User $user): Shift
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'هذه الوردية مغلقة بالفعل.',
            ]);
        }

        $closed = DB::transaction(function () use ($shift, $user) {
            foreach ($shift->shiftAccounts as $account) {
                $account->update(['closing_balance' => $account->current_balance]);
            }

            $shift->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            return $shift->fresh();
        });

        $isOwnShift = $shift->opened_by === $user->id;

        $this->activity->log(
            $isOwnShift ? ActivityLogger::SHIFT_CLOSED : ActivityLogger::SHIFT_FORCE_CLOSED,
            $isOwnShift
                ? "إغلاق الوردية رقم {$shift->id}"
                : "إغلاق الوردية رقم {$shift->id} العائدة للمستخدم ".($shift->openedBy?->name ?? '—'),
            $closed,
            ['owner_id' => $shift->opened_by],
            $user,
        );

        return $closed;
    }

    /**
     * يغلق وردية المستخدم المفتوحة (إن وُجدت) ويفتح له وردية جديدة فوراً.
     *
     * @param  array<int, string>  $openingBalances  currency_id => opening balance
     */
    public function closeCurrentAndOpenNew(User $user, array $openingBalances = []): Shift
    {
        return DB::transaction(function () use ($user, $openingBalances) {
            $current = $this->getCurrentOpenShift($user);

            if ($current) {
                $this->closeShift($current, $user);
            }

            return $this->openShift($user, $openingBalances);
        });
    }

    /**
     * حذف ناعم لوردية مغلقة، ومعها عملياتها.
     *
     * لو بقيت العمليات ظاهرة لظهرت في التقارير منسوبةً إلى وردية لم تعد
     * موجودة. تُوسَم المحذوفة تبعاً بـ deleted_with_shift حتى تعرف الاستعادة
     * لاحقاً أيَّها تُعيد، فلا تُحيي عمليةً حُذفت وحدها قبل ذلك عن قصد.
     */
    public function deleteShift(Shift $shift, User $actor, string $reason): Shift
    {
        if ($shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'لا يمكن حذف وردية مفتوحة — أغلقها أولاً.',
            ]);
        }

        return DB::transaction(function () use ($shift, $actor, $reason) {
            $cascaded = ExchangeTransaction::where('shift_id', $shift->getKey())->update([
                'deleted_at' => now(),
                'deleted_by' => $actor->getKey(),
                'deletion_reason' => $reason,
                'deleted_with_shift' => true,
            ]);

            $shift->forceFill([
                'deleted_by' => $actor->getKey(),
                'deletion_reason' => $reason,
            ])->save();

            $shift->delete();

            $this->activity->log(
                ActivityLogger::SHIFT_DELETED,
                "حذف الوردية رقم {$shift->id} العائدة للمستخدم ".($shift->openedBy?->name ?? '—'),
                $shift,
                [
                    'business_date' => $shift->business_date->toDateString(),
                    'owner_id' => $shift->opened_by,
                    'closed_at' => $shift->closed_at?->toIso8601String(),
                    'cascaded_transactions' => $cascaded,
                    'reason' => $reason,
                ],
                $actor,
            );

            return $shift;
        });
    }

    /**
     * استعادة وردية محذوفة، ومعها العمليات التي حُذفت ضمنها وحدها.
     */
    public function restoreShift(Shift $shift, User $actor): Shift
    {
        return DB::transaction(function () use ($shift, $actor) {
            $restored = ExchangeTransaction::onlyTrashed()
                ->where('shift_id', $shift->getKey())
                ->where('deleted_with_shift', true)
                ->update([
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                    'deleted_with_shift' => false,
                ]);

            $shift->restore();

            $shift->forceFill([
                'deleted_by' => null,
                'deletion_reason' => null,
            ])->save();

            $this->activity->log(
                ActivityLogger::SHIFT_RESTORED,
                "استعادة الوردية رقم {$shift->id}",
                $shift,
                [
                    'business_date' => $shift->business_date->toDateString(),
                    'owner_id' => $shift->opened_by,
                    'restored_transactions' => $restored,
                ],
                $actor,
            );

            return $shift;
        });
    }

    /**
     * الأرصدة الحالية لوردية مفتوحة.
     *
     * @return array<int, string> currency_id => current balance
     */
    public function currentBalances(Shift $shift): array
    {
        return $shift->shiftAccounts()
            ->get()
            ->mapWithKeys(fn (ShiftAccount $account) => [$account->currency_id => (string) $account->current_balance])
            ->all();
    }

    /**
     * عملات معطّلة ما تزال تحمل رصيداً.
     *
     * فتح الوردية يمرّ على العملات المفعّلة فقط، فرصيد عملة عُطّلت لا ينتقل
     * إلى الوردية الجديدة ويختفي من الحسابات دون أثر ظاهر. نعرضه للمستخدم
     * قبل الفتح بدل أن يكتشف النقص لاحقاً.
     *
     * @return list<array{id: int, code: string, name: string, balance: string}>
     */
    public function strandedBalances(User $user, ?Shift $currentShift = null): array
    {
        $balances = $currentShift ? $this->currentBalances($currentShift) : [];

        return Currency::where('is_active', false)
            ->get()
            ->map(function (Currency $currency) use ($user, $balances, $currentShift) {
                $balance = $currentShift
                    ? ($balances[$currency->id] ?? '0')
                    : ($this->previousClosingBalance($user, $currency->id) ?? '0');

                return [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'balance' => $balance,
                ];
            })
            ->filter(fn (array $row) => bccomp($row['balance'], '0', 4) !== 0)
            ->values()
            ->all();
    }

    /**
     * أرصدة إغلاق آخر وردية لهذا المستخدم، لتعبئة نموذج الفتح.
     *
     * @return array<int, string> currency_id => previous closing balance
     */
    public function previousClosingBalances(User $user): array
    {
        return Currency::where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Currency $currency) => [
                $currency->id => $this->previousClosingBalance($user, $currency->id) ?? '0',
            ])
            ->all();
    }

    private function previousClosingBalance(User $user, int $currencyId): ?string
    {
        $account = ShiftAccount::where('currency_id', $currencyId)
            ->whereNotNull('closing_balance')
            ->join('shifts', 'shifts.id', '=', 'shift_accounts.shift_id')
            ->where('shifts.status', 'closed')
            // ضمّ يدوي لا يمرّ عليه نطاق الحذف الناعم، فنستبعد المحذوف صراحةً:
            // وردية محذوفة يجب ألّا تُملي رصيد افتتاح الوردية التالية.
            ->whereNull('shifts.deleted_at')
            ->where('shifts.opened_by', $user->getKey())
            ->orderByDesc('shifts.closed_at')
            // closed_at بدقّة الثانية: ورديتان تُغلقان في الثانية نفسها تتساويان
            // في الترتيب فيختار MySQL أيّهما شاء. المعرّف يكسر التعادل، وإلا صار
            // رصيد الافتتاح المقترح — وما يعود إليه الاقتراح بعد حذف وردية —
            // رهنَ الصدفة.
            ->orderByDesc('shifts.id')
            ->select('shift_accounts.*')
            ->first();

        return $account?->closing_balance;
    }
}
