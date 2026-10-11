<?php

namespace App\Reports\Builders;

use App\Models\ExchangeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Reports\ReportResult;
use Illuminate\Database\Eloquent\Builder;

class ShiftSummaryReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [
            ...$this->dateRangeFilters(),
            [
                'key' => 'status',
                'type' => 'select',
                'label' => 'الحالة',
                'options' => [
                    ['value' => '', 'label' => 'الكل'],
                    ['value' => 'open', 'label' => 'مفتوحة'],
                    ['value' => 'closed', 'label' => 'مغلقة'],
                ],
            ],
        ];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $shifts = Shift::query()
            ->with(['openedBy', 'closedBy'])
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('business_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('business_date', '<=', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->orderByDesc('business_date')
            ->get();

        $stats = ExchangeTransaction::query()
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->get()
            ->groupBy('shift_id');

        $rows = $shifts->map(function (Shift $shift) use ($stats, $user) {
            $group = $stats->get($shift->id, collect());

            return [
                'id' => $shift->id,
                'business_date' => $shift->business_date->toDateString(),
                'status' => $shift->status === 'open' ? 'مفتوحة' : 'مغلقة',
                'opened_by' => $shift->openedBy?->name,
                'closed_by' => $shift->closedBy?->name,
                'opened_at' => $shift->opened_at?->toIso8601String(),
                'closed_at' => $shift->closed_at?->toIso8601String(),
                'transaction_count' => $group->count(),
                'volume' => $this->sum($group->pluck('total')),
                'profit' => $this->canSeeProfit($user) ? $this->sum($group->pluck('profit')) : null,
            ];
        });

        $columns = [
            ['key' => 'id', 'label' => 'رقم الوردية', 'format' => 'integer'],
            ['key' => 'business_date', 'label' => 'التاريخ', 'format' => 'date'],
            ['key' => 'status', 'label' => 'الحالة', 'format' => 'text'],
            ['key' => 'opened_by', 'label' => 'فتحها', 'format' => 'text'],
            ['key' => 'closed_by', 'label' => 'أغلقها', 'format' => 'text'],
            ['key' => 'opened_at', 'label' => 'وقت الفتح', 'format' => 'datetime'],
            ['key' => 'closed_at', 'label' => 'وقت الإغلاق', 'format' => 'datetime'],
            ['key' => 'transaction_count', 'label' => 'عدد العمليات', 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'حجم التداول', 'format' => 'number'],
        ];

        $totals = [
            ['key' => 'shifts', 'label' => 'عدد الورديات', 'value' => $shifts->count(), 'format' => 'integer'],
            ['key' => 'count', 'label' => 'إجمالي العمليات', 'value' => $rows->sum('transaction_count'), 'format' => 'integer'],
            ['key' => 'volume', 'label' => 'إجمالي التداول', 'value' => $this->sum($rows->pluck('volume')), 'format' => 'number'],
        ];

        if ($this->canSeeProfit($user)) {
            $columns[] = ['key' => 'profit', 'label' => 'الربح', 'format' => 'signed'];
            $totals[] = ['key' => 'profit', 'label' => 'إجمالي الربح', 'value' => $this->sum($rows->pluck('profit')), 'format' => 'signed'];
        }

        return new ReportResult($columns, $rows, $totals);
    }
}
