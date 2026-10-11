<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class UserPerformanceReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            $this->currencyFilter(),
            $this->typeFilter(),
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $transactions = $this->transactionQuery($filters)->with('user')->get();

        $rows = $transactions
            ->groupBy('user_id')
            ->map(function ($group) use ($user) {
                $buys = $group->where('type', ExchangeTransaction::TYPE_BUY);
                $sells = $group->where('type', ExchangeTransaction::TYPE_SELL);

                return [
                    'user' => $group->first()->user?->name ?? '—',
                    'transaction_count' => $group->count(),
                    'buy_count' => $buys->count(),
                    'sell_count' => $sells->count(),
                    'volume' => $this->sum($group->pluck('total')),
                    'avg_ticket' => $group->count()
                        ? bcdiv($this->sum($group->pluck('total')), (string) $group->count(), 4)
                        : '0',
                    'last_activity' => $group->max('created_at')?->toIso8601String(),
                    'profit' => $this->canSeeProfit($user) ? $this->sum($group->pluck('profit')) : null,
                ];
            })
            ->sortByDesc(fn ($row) => (float) $row['volume'])
            ->values();

        $columns = [
            ['key' => 'user', 'label' => 'الموظف', 'format' => 'text'],
            ['key' => 'transaction_count', 'label' => 'عدد العمليات', 'format' => 'integer'],
            ['key' => 'buy_count', 'label' => 'شراء', 'format' => 'integer'],
            ['key' => 'sell_count', 'label' => 'بيع', 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'حجم التداول', 'format' => 'number'],
            ['key' => 'avg_ticket', 'label' => 'متوسط العملية', 'format' => 'number'],
            ['key' => 'last_activity', 'label' => 'آخر نشاط', 'format' => 'datetime'],
        ];

        $totals = [
            ['key' => 'users', 'label' => 'عدد الموظفين', 'value' => $rows->count(), 'format' => 'integer'],
            ['key' => 'count', 'label' => 'إجمالي العمليات', 'value' => $transactions->count(), 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'إجمالي التداول', 'value' => $this->sum($transactions->pluck('total')), 'format' => 'number'],
        ];

        if ($this->canSeeProfit($user)) {
            $columns[] = ['key' => 'profit', 'label' => 'الربح', 'format' => 'signed'];
            $totals[] = ['key' => 'profit', 'label' => 'إجمالي الربح', 'value' => $this->sum($transactions->pluck('profit')), 'format' => 'signed'];
        }

        return new ReportResult($columns, $rows, $totals);
    }
}
