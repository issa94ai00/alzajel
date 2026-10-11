<?php

namespace App\Reports\Builders;

use App\Models\Shift;
use App\Models\User;
use App\Reports\ReportResult;
use App\Services\ShiftService;

class BalancesReportBuilder extends BaseReportBuilder
{
    public function __construct(private readonly ShiftService $shiftService) {}

    public function filters(): array
    {
        return [$this->shiftFilter()];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        $shift = ! empty($filters['shift_id'])
            ? Shift::find($filters['shift_id'])
            : $this->shiftService->getCurrentOpenShift();

        $rows = $shift
            ? $shift->shiftAccounts()->with('currency')->get()->map(fn ($account) => [
                'currency' => $account->currency->code,
                'currency_name' => $account->currency->name,
                'opening_balance' => $account->opening_balance,
                'current_balance' => $account->current_balance,
                'change' => bcsub((string) $account->current_balance, (string) $account->opening_balance, 4),
                'closing_balance' => $account->closing_balance,
            ])
            : collect();

        return new ReportResult(
            columns: [
                ['key' => 'currency', 'label' => 'العملة', 'format' => 'text'],
                ['key' => 'currency_name', 'label' => 'الاسم', 'format' => 'text'],
                ['key' => 'opening_balance', 'label' => 'الرصيد الافتتاحي', 'format' => 'number'],
                ['key' => 'current_balance', 'label' => 'الرصيد الحالي', 'format' => 'number'],
                ['key' => 'change', 'label' => 'صافي الحركة', 'format' => 'signed'],
                ['key' => 'closing_balance', 'label' => 'رصيد الإغلاق', 'format' => 'number'],
            ],
            rows: $rows,
            totals: [
                ['key' => 'accounts', 'label' => 'عدد العملات', 'value' => $rows->count(), 'format' => 'integer'],
            ],
            meta: [
                'shift' => $shift ? [
                    'id' => $shift->id,
                    'business_date' => $shift->business_date->toDateString(),
                    'status' => $shift->status,
                ] : null,
                'empty_message' => $shift ? 'لا توجد حسابات في هذه الوردية.' : 'لا توجد وردية مفتوحة حالياً.',
            ],
        );
    }
}
