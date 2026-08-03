<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use Illuminate\Support\Collection;

class ReportService
{
    public function currentBalances(Shift $shift): Collection
    {
        return $shift->shiftAccounts()
            ->with('currency')
            ->get()
            ->map(fn ($account) => [
                'currency' => $account->currency->only(['id', 'code', 'name', 'symbol']),
                'opening_balance' => $account->opening_balance,
                'current_balance' => $account->current_balance,
            ]);
    }

    /**
     * @param  array{date_from?: string, date_to?: string}  $range
     */
    public function dailyOperationsReport(array $range): Collection
    {
        return ExchangeTransaction::query()
            ->with(['currency', 'counterCurrency', 'user', 'shift'])
            ->when($range['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($range['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy('created_at')
            ->get();
    }

    public function profitLossForShift(Shift $shift): array
    {
        $transactions = $shift->exchangeTransactions;

        return [
            'shift' => $this->shiftSummary($shift),
            'total_profit' => (string) $transactions->reduce(fn ($carry, $t) => bcadd($carry, (string) $t->profit, 4), '0'),
            'transaction_count' => $transactions->count(),
            'by_currency' => $transactions->groupBy('currency_id')->map(fn ($group) => [
                'currency' => $group->first()->currency->only(['id', 'code', 'name']),
                'profit' => (string) $group->reduce(fn ($carry, $t) => bcadd($carry, (string) $t->profit, 4), '0'),
                'transaction_count' => $group->count(),
            ])->values(),
        ];
    }

    /**
     * @param  array{date_from?: string, date_to?: string}  $range
     */
    public function profitLossForRange(array $range): Collection
    {
        return ExchangeTransaction::query()
            ->with('shift')
            ->when($range['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($range['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->get()
            ->groupBy('shift_id')
            ->map(fn ($group) => [
                'shift' => $this->shiftSummary($group->first()->shift),
                'profit' => (string) $group->reduce(fn ($carry, $t) => bcadd($carry, (string) $t->profit, 4), '0'),
                'transaction_count' => $group->count(),
            ])
            ->values();
    }

    /**
     * @return array{id: int, business_date: string, status: string, opened_at?: string, closed_at?: string|null}
     */
    private function shiftSummary(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'business_date' => $shift->business_date->toDateString(),
            'status' => $shift->status,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{date_from?: string, date_to?: string}  $range
     */
    public function currencyMovementReport(Currency $currency, array $range): Collection
    {
        return ExchangeTransaction::query()
            ->where('currency_id', $currency->id)
            ->with(['user', 'shift'])
            ->when($range['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($range['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy('created_at')
            ->get();
    }
}
