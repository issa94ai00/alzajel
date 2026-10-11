<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class ProfitLossReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            $this->currencyFilter(),
            [
                'key' => 'group_by',
                'type' => 'select',
                'label' => 'التجميع حسب',
                'default' => 'shift',
                'options' => [
                    ['value' => 'shift', 'label' => 'الوردية'],
                    ['value' => 'day', 'label' => 'اليوم'],
                    ['value' => 'currency', 'label' => 'العملة'],
                    ['value' => 'user', 'label' => 'الموظف'],
                ],
            ],
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $groupBy = $filters['group_by'] ?? 'shift';

        $transactions = $this->transactionQuery($filters)
            ->with(['shift', 'currency', 'user'])
            ->get();

        [$label, $grouper] = match ($groupBy) {
            'day' => ['اليوم', fn (ExchangeTransaction $t) => $t->created_at->toDateString()],
            'currency' => ['العملة', fn (ExchangeTransaction $t) => $t->currency->code],
            'user' => ['الموظف', fn (ExchangeTransaction $t) => $t->user?->name ?? '—'],
            default => ['الوردية', fn (ExchangeTransaction $t) => $t->shift_id],
        };

        $rows = $transactions
            ->groupBy($grouper)
            ->map(function ($group, $key) use ($groupBy) {
                $buys = $group->where('type', ExchangeTransaction::TYPE_BUY);
                $sells = $group->where('type', ExchangeTransaction::TYPE_SELL);

                return [
                    'group' => $groupBy === 'shift' ? "#{$key}" : (string) $key,
                    'business_date' => $groupBy === 'shift'
                        ? $group->first()->shift?->business_date?->toDateString()
                        : null,
                    'transaction_count' => $group->count(),
                    'buy_profit' => $this->sum($buys->pluck('profit')),
                    'sell_profit' => $this->sum($sells->pluck('profit')),
                    'volume' => $this->sum($group->pluck('total')),
                    'profit' => $this->sum($group->pluck('profit')),
                ];
            })
            ->sortByDesc(fn ($row) => (float) $row['profit'])
            ->values();

        $columns = [
            ['key' => 'group', 'label' => $label, 'format' => 'text'],
            ['key' => 'business_date', 'label' => 'التاريخ', 'format' => 'date'],
            ['key' => 'transaction_count', 'label' => 'عدد العمليات', 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'حجم التداول', 'format' => 'number'],
            ['key' => 'buy_profit', 'label' => 'ربح الشراء', 'format' => 'signed'],
            ['key' => 'sell_profit', 'label' => 'ربح البيع', 'format' => 'signed'],
            ['key' => 'profit', 'label' => 'صافي الربح', 'format' => 'signed'],
        ];

        if ($groupBy !== 'shift') {
            $columns = array_values(array_filter($columns, fn ($c) => $c['key'] !== 'business_date'));
        }

        return new ReportResult(
            columns: $columns,
            rows: $rows,
            totals: [
                ['key' => 'count', 'label' => 'عدد العمليات', 'value' => $transactions->count(), 'format' => 'integer'],
                ['key' => 'volume', 'label' => 'حجم التداول', 'value' => $this->sum($transactions->pluck('total')), 'format' => 'number'],
                ['key' => 'profit', 'label' => 'صافي الربح', 'value' => $this->sum($transactions->pluck('profit')), 'format' => 'signed'],
            ],
        );
    }
}
