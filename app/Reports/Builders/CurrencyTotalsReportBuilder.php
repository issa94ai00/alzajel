<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class CurrencyTotalsReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            $this->shiftFilter(),
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $transactions = $this->transactionQuery($filters)->with('currency')->get();

        $rows = $transactions
            ->groupBy('currency_id')
            ->map(function ($group) use ($user) {
                $buys = $group->where('type', ExchangeTransaction::TYPE_BUY);
                $sells = $group->where('type', ExchangeTransaction::TYPE_SELL);
                $bought = $this->sum($buys->pluck('amount'));
                $sold = $this->sum($sells->pluck('amount'));

                return [
                    'currency' => $group->first()->currency->code,
                    'currency_name' => $group->first()->currency->name,
                    'buy_count' => $buys->count(),
                    'bought_amount' => $bought,
                    'buy_value' => $this->sum($buys->pluck('total')),
                    'sell_count' => $sells->count(),
                    'sold_amount' => $sold,
                    'sell_value' => $this->sum($sells->pluck('total')),
                    'net_amount' => bcsub($bought, $sold, 4),
                    'avg_buy_rate' => $buys->count() ? bcdiv($this->sum($buys->pluck('total')), $bought === '0.0000' ? '1' : $bought, 4) : null,
                    'avg_sell_rate' => $sells->count() ? bcdiv($this->sum($sells->pluck('total')), $sold === '0.0000' ? '1' : $sold, 4) : null,
                    'profit' => $this->canSeeProfit($user) ? $this->sum($group->pluck('profit')) : null,
                ];
            })
            ->sortBy('currency')
            ->values();

        $columns = [
            ['key' => 'currency', 'label' => 'العملة', 'format' => 'text'],
            ['key' => 'currency_name', 'label' => 'الاسم', 'format' => 'text'],
            ['key' => 'buy_count', 'label' => 'عمليات الشراء', 'format' => 'integer'],
            ['key' => 'bought_amount', 'label' => 'الكمية المشتراة', 'format' => 'number'],
            ['key' => 'avg_buy_rate', 'label' => 'متوسط سعر الشراء', 'format' => 'number'],
            ['key' => 'sell_count', 'label' => 'عمليات البيع', 'format' => 'integer'],
            ['key' => 'sold_amount', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'avg_sell_rate', 'label' => 'متوسط سعر البيع', 'format' => 'number'],
            ['key' => 'net_amount', 'label' => 'الصافي', 'format' => 'signed'],
        ];

        $totals = [
            ['key' => 'currencies', 'label' => 'عدد العملات', 'value' => $rows->count(), 'format' => 'integer'],
            ['key' => 'count', 'label' => 'إجمالي العمليات', 'value' => $transactions->count(), 'format' => 'integer'],
        ];

        if ($this->canSeeProfit($user)) {
            $columns[] = ['key' => 'profit', 'label' => 'الربح', 'format' => 'signed'];
            $totals[] = ['key' => 'profit', 'label' => 'إجمالي الربح', 'value' => $this->sum($transactions->pluck('profit')), 'format' => 'signed'];
        }

        return new ReportResult($columns, $rows, $totals);
    }
}
