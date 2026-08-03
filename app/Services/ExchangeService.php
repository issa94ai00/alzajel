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
    /**
     * @param  array{currency_id: int, type: string, amount: string, rate?: string|null, customer_name?: string|null, customer_phone?: string|null, notes?: string|null}  $data
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

    /**
     * @return array{0: ShiftAccount, 1: ShiftAccount}
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
     * @param  array{shift_id?: int, currency_id?: int, type?: string, date_from?: string, date_to?: string, search?: string}  $filters
     */
    public function listTransactions(array $filters = []): LengthAwarePaginator
    {
        return ExchangeTransaction::query()
            ->with(['currency', 'counterCurrency', 'user'])
            ->when($filters['shift_id'] ?? null, fn (Builder $q, $v) => $q->where('shift_id', $v))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $v) => $q->where('currency_id', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $q) use ($v) {
                $q->where('customer_name', 'like', "%{$v}%")
                    ->orWhere('customer_phone', 'like', "%{$v}%")
                    ->orWhere('notes', 'like', "%{$v}%");
            }))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();
    }
}
