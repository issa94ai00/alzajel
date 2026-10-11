<?php

namespace App\Reports\Builders;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Reports\ReportResult;

class CurrencyMovementReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            $this->currencyFilter(anyOption: false),
            ...$this->dateRangeFilters(),
            $this->typeFilter(),
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $currency = ! empty($filters['currency_id'])
            ? Currency::find($filters['currency_id'])
            : Currency::where('is_active', true)->where('is_base', false)->orderBy('code')->first();

        if (! $currency) {
            return new ReportResult([], collect(), [], ['empty_message' => 'لا توجد عملات متاحة.']);
        }

        $transactions = $this->transactionQuery(array_merge($filters, ['currency_id' => $currency->id]))
            ->with(['user', 'shift', 'counterCurrency'])
            ->orderBy('created_at')
            ->get();

        $running = '0';

        $rows = $transactions->map(function (ExchangeTransaction $t) use (&$running) {
            $in = $t->type === ExchangeTransaction::TYPE_BUY ? $t->amount : null;
            $out = $t->type === ExchangeTransaction::TYPE_SELL ? $t->amount : null;
            $running = bcadd($running, (string) ($in ?? bcmul((string) $t->amount, '-1', 4)), 4);

            return [
                'created_at' => $t->created_at->toIso8601String(),
                'type' => $t->type,
                'in' => $in,
                'out' => $out,
                'rate' => $t->rate,
                'total' => $t->total,
                'running' => $running,
                'user' => $t->user->name,
                'shift_id' => $t->shift_id,
            ];
        });

        $in = $this->sum($transactions->where('type', ExchangeTransaction::TYPE_BUY)->pluck('amount'));
        $out = $this->sum($transactions->where('type', ExchangeTransaction::TYPE_SELL)->pluck('amount'));

        return new ReportResult(
            columns: [
                ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
                ['key' => 'type', 'label' => 'النوع', 'format' => 'type'],
                ['key' => 'in', 'label' => 'وارد', 'format' => 'number'],
                ['key' => 'out', 'label' => 'صادر', 'format' => 'number'],
                ['key' => 'rate', 'label' => 'السعر', 'format' => 'number'],
                ['key' => 'total', 'label' => 'المقابل', 'format' => 'number'],
                ['key' => 'running', 'label' => 'الحركة التراكمية', 'format' => 'signed'],
                ['key' => 'user', 'label' => 'الموظف', 'format' => 'text'],
                ['key' => 'shift_id', 'label' => 'الوردية', 'format' => 'integer'],
            ],
            rows: $rows,
            totals: [
                ['key' => 'count', 'label' => 'عدد العمليات', 'value' => $transactions->count(), 'format' => 'integer'],
                ['key' => 'in', 'label' => "إجمالي الوارد {$currency->code}", 'value' => $in, 'format' => 'number'],
                ['key' => 'out', 'label' => "إجمالي الصادر {$currency->code}", 'value' => $out, 'format' => 'number'],
                ['key' => 'net', 'label' => 'صافي الحركة', 'value' => bcsub($in, $out, 4), 'format' => 'signed'],
            ],
            meta: ['currency' => $currency->only(['id', 'code', 'name'])],
        );
    }
}
