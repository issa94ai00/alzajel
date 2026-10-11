<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class CustomerActivityReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            $this->currencyFilter(),
            ['key' => 'search', 'type' => 'text', 'label' => 'اسم العميل أو رقمه'],
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $transactions = $this->transactionQuery($filters)
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->get();

        $rows = $transactions
            ->groupBy(fn (ExchangeTransaction $t) => trim((string) $t->customer_name).'|'.trim((string) $t->customer_phone))
            ->map(function ($group) use ($user) {
                $buys = $group->where('type', ExchangeTransaction::TYPE_BUY);
                $sells = $group->where('type', ExchangeTransaction::TYPE_SELL);

                return [
                    'customer_name' => $group->first()->customer_name,
                    'customer_phone' => $group->first()->customer_phone,
                    'transaction_count' => $group->count(),
                    'buy_count' => $buys->count(),
                    'sell_count' => $sells->count(),
                    'volume' => $this->sum($group->pluck('total')),
                    'first_activity' => $group->min('created_at')?->toIso8601String(),
                    'last_activity' => $group->max('created_at')?->toIso8601String(),
                    'profit' => $this->canSeeProfit($user) ? $this->sum($group->pluck('profit')) : null,
                ];
            })
            ->sortByDesc(fn ($row) => (float) $row['volume'])
            ->values();

        $columns = [
            ['key' => 'customer_name', 'label' => 'العميل', 'format' => 'text'],
            ['key' => 'customer_phone', 'label' => 'الهاتف', 'format' => 'text'],
            ['key' => 'transaction_count', 'label' => 'عدد العمليات', 'format' => 'integer'],
            ['key' => 'buy_count', 'label' => 'شراء', 'format' => 'integer'],
            ['key' => 'sell_count', 'label' => 'بيع', 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'حجم التعامل', 'format' => 'number'],
            ['key' => 'first_activity', 'label' => 'أول تعامل', 'format' => 'datetime'],
            ['key' => 'last_activity', 'label' => 'آخر تعامل', 'format' => 'datetime'],
        ];

        $totals = [
            ['key' => 'customers', 'label' => 'عدد العملاء', 'value' => $rows->count(), 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'إجمالي التعامل', 'value' => $this->sum($transactions->pluck('total')), 'format' => 'number'],
        ];

        if ($this->canSeeProfit($user)) {
            $columns[] = ['key' => 'profit', 'label' => 'الربح', 'format' => 'signed'];
        }

        return new ReportResult($columns, $rows, $totals, [
            'empty_message' => 'لا توجد عمليات مسجّلة باسم عميل ضمن هذه الفترة.',
        ]);
    }
}
