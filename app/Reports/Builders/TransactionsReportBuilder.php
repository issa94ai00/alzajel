<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class TransactionsReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            $this->typeFilter(),
            $this->currencyFilter(),
            $this->userFilter(),
            ['key' => 'search', 'type' => 'text', 'label' => 'بحث (عميل / هاتف / ملاحظة)'],
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $transactions = $this->transactionQuery($filters)
            ->with(['currency', 'counterCurrency', 'user', 'shift'])
            ->orderBy('created_at')
            ->get();

        $rows = $transactions->map(fn (ExchangeTransaction $t) => [
            'id' => $t->id,
            'created_at' => $t->created_at->toIso8601String(),
            'type' => $t->type,
            'currency' => $t->currency->code,
            'amount' => $t->amount,
            'rate' => $t->rate,
            'total' => $t->total,
            'counter_currency' => $t->counterCurrency->code,
            'customer_name' => $t->customer_name,
            'user' => $t->user->name,
            'shift_id' => $t->shift_id,
            'profit' => $t->profit,
        ]);

        $columns = [
            ['key' => 'id', 'label' => 'رقم', 'format' => 'integer'],
            ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ['key' => 'type', 'label' => 'النوع', 'format' => 'type'],
            ['key' => 'currency', 'label' => 'العملة', 'format' => 'text'],
            ['key' => 'amount', 'label' => 'المبلغ', 'format' => 'number'],
            ['key' => 'rate', 'label' => 'السعر', 'format' => 'number'],
            ['key' => 'total', 'label' => 'الإجمالي', 'format' => 'number'],
            ['key' => 'customer_name', 'label' => 'العميل', 'format' => 'text'],
            ['key' => 'user', 'label' => 'الموظف', 'format' => 'text'],
            ['key' => 'shift_id', 'label' => 'الوردية', 'format' => 'integer'],
        ];

        if ($this->canSeeProfit($user)) {
            $columns[] = ['key' => 'profit', 'label' => 'الربح', 'format' => 'signed'];
        }

        $buys = $transactions->where('type', ExchangeTransaction::TYPE_BUY);
        $sells = $transactions->where('type', ExchangeTransaction::TYPE_SELL);

        $totals = [
            ['key' => 'count', 'label' => 'عدد العمليات', 'value' => $transactions->count(), 'format' => 'integer'],
            ['key' => 'buy_total', 'label' => 'إجمالي الشراء', 'value' => $this->sum($buys->pluck('total')), 'format' => 'number'],
            ['key' => 'sell_total', 'label' => 'إجمالي البيع', 'value' => $this->sum($sells->pluck('total')), 'format' => 'number'],
        ];

        if ($this->canSeeProfit($user)) {
            $totals[] = ['key' => 'profit', 'label' => 'إجمالي الربح', 'value' => $this->sum($transactions->pluck('profit')), 'format' => 'signed'];
        }

        return new ReportResult($columns, $rows, $totals, ['row_link' => 'transaction']);
    }
}
