<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function getCurrentOpenShift(): ?Shift
    {
        return Shift::open()->first();
    }

    /**
     * @param  array<int, string>  $openingBalances  currency_id => opening balance
     */
    public function openShift(User $user, array $openingBalances = []): Shift
    {
        if ($this->getCurrentOpenShift()) {
            throw ValidationException::withMessages([
                'shift' => 'توجد وردية مفتوحة بالفعل — أغلقها قبل فتح وردية جديدة.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $openingBalances) {
                $shift = Shift::create([
                    'opened_by' => $user->id,
                    'status' => 'open',
                    'business_date' => now()->toDateString(),
                    'opened_at' => now(),
                ]);

                foreach (Currency::where('is_active', true)->get() as $currency) {
                    $opening = $openingBalances[$currency->id]
                        ?? $this->previousClosingBalance($currency->id)
                        ?? '0';

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
                'shift' => 'توجد وردية مفتوحة بالفعل — أغلقها قبل فتح وردية جديدة.',
            ]);
        }
    }

    public function closeShift(Shift $shift, User $user): Shift
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'هذه الوردية مغلقة بالفعل.',
            ]);
        }

        return DB::transaction(function () use ($shift, $user) {
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
    }

    /**
     * Closes whichever shift is currently open (if any) and immediately opens a new one.
     *
     * @param  array<int, string>  $openingBalances  currency_id => opening balance
     */
    public function closeCurrentAndOpenNew(User $user, array $openingBalances = []): Shift
    {
        return DB::transaction(function () use ($user, $openingBalances) {
            $current = $this->getCurrentOpenShift();

            if ($current) {
                $this->closeShift($current, $user);
            }

            return $this->openShift($user, $openingBalances);
        });
    }

    private function previousClosingBalance(int $currencyId): ?string
    {
        $account = ShiftAccount::where('currency_id', $currencyId)
            ->whereHas('shift', fn ($q) => $q->where('status', 'closed'))
            ->whereNotNull('closing_balance')
            ->join('shifts', 'shifts.id', '=', 'shift_accounts.shift_id')
            ->orderByDesc('shifts.closed_at')
            ->select('shift_accounts.*')
            ->first();

        return $account?->closing_balance;
    }
}
