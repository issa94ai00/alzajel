<?php

namespace App\Reports\Builders;

use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Reports\ReportBuilder;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Permissions;

abstract class BaseReportBuilder implements ReportBuilder
{
    /**
     * فلاتر النطاق الزمني المشتركة بين معظم التقارير.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dateRangeFilters(): array
    {
        return [
            ['key' => 'date_from', 'type' => 'date', 'label' => 'من تاريخ'],
            ['key' => 'date_to', 'type' => 'date', 'label' => 'إلى تاريخ'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function currencyFilter(string $key = 'currency_id', string $label = 'العملة', bool $includeBase = false, bool $anyOption = true): array
    {
        $options = Currency::query()
            ->where('is_active', true)
            ->when(! $includeBase, fn (Builder $q) => $q->where('is_base', false))
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $c) => ['value' => $c->id, 'label' => "{$c->code} — {$c->name}"])
            ->all();

        if ($anyOption) {
            array_unshift($options, ['value' => '', 'label' => 'كل العملات']);
        }

        return ['key' => $key, 'type' => 'select', 'label' => $label, 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeFilter(): array
    {
        return [
            'key' => 'type',
            'type' => 'select',
            'label' => 'نوع العملية',
            'options' => [
                ['value' => '', 'label' => 'الكل'],
                ['value' => 'buy', 'label' => 'شراء'],
                ['value' => 'sell', 'label' => 'بيع'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userFilter(): array
    {
        $options = User::orderBy('name')->get()
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])
            ->all();

        array_unshift($options, ['value' => '', 'label' => 'كل الموظفين']);

        return ['key' => 'user_id', 'type' => 'select', 'label' => 'الموظف', 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shiftFilter(): array
    {
        $options = Shift::latest('opened_at')->limit(60)->get()
            ->map(fn (Shift $s) => [
                'value' => $s->id,
                'label' => "#{$s->id} — {$s->business_date->toDateString()}",
            ])
            ->all();

        array_unshift($options, ['value' => '', 'label' => 'كل الورديات']);

        return ['key' => 'shift_id', 'type' => 'select', 'label' => 'الوردية', 'options' => $options];
    }

    /**
     * استعلام العمليات مع تطبيق الفلاتر المشتركة.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function transactionQuery(array $filters): Builder
    {
        return ExchangeTransaction::query()
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['currency_id'] ?? null, fn (Builder $q, $v) => $q->where('currency_id', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['shift_id'] ?? null, fn (Builder $q, $v) => $q->where('shift_id', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(function (Builder $q) use ($v) {
                $q->where('customer_name', 'like', "%{$v}%")
                    ->orWhere('customer_phone', 'like', "%{$v}%")
                    ->orWhere('notes', 'like', "%{$v}%");
            }));
    }

    protected function canSeeProfit(?User $user): bool
    {
        return $user?->can(Permissions::REPORTS_PROFIT_LOSS) ?? false;
    }

    protected function sum(iterable $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, (string) ($value ?? '0'), 4);
        }

        return $total;
    }
}
