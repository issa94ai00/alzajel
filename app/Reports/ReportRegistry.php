<?php

namespace App\Reports;

use App\Reports\Builders\BalancesReportBuilder;
use App\Reports\Builders\CurrencyMovementReportBuilder;
use App\Reports\Builders\CurrencyTotalsReportBuilder;
use App\Reports\Builders\CustomerActivityReportBuilder;
use App\Reports\Builders\ProfitLossReportBuilder;
use App\Reports\Builders\ShiftSummaryReportBuilder;
use App\Reports\Builders\TransactionsReportBuilder;
use App\Reports\Builders\UserPerformanceReportBuilder;
use InvalidArgumentException;

/**
 * سجلّ أنواع التقارير المدعومة. كل نوع هنا يمكن ربطه بتعريف تقرير واحد أو أكثر
 * في جدول report_definitions، والمدير يتحكم بالظهور والخيارات من الإعدادات.
 */
class ReportRegistry
{
    /** @var array<string, class-string<ReportBuilder>> */
    private const BUILDERS = [
        'balances' => BalancesReportBuilder::class,
        'transactions' => TransactionsReportBuilder::class,
        'currency_movement' => CurrencyMovementReportBuilder::class,
        'shift_summary' => ShiftSummaryReportBuilder::class,
        'currency_totals' => CurrencyTotalsReportBuilder::class,
        'user_performance' => UserPerformanceReportBuilder::class,
        'customer_activity' => CustomerActivityReportBuilder::class,
        'profit_loss' => ProfitLossReportBuilder::class,
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'balances' => 'أرصدة الوردية',
        'transactions' => 'سجل العمليات',
        'currency_movement' => 'حركة عملة',
        'shift_summary' => 'ملخص الورديات',
        'currency_totals' => 'إجماليات العملات',
        'user_performance' => 'أداء الموظفين',
        'customer_activity' => 'نشاط العملاء',
        'profit_loss' => 'الأرباح والخسائر',
    ];

    public function make(string $builder): ReportBuilder
    {
        if (! isset(self::BUILDERS[$builder])) {
            throw new InvalidArgumentException("نوع تقرير غير معروف: {$builder}");
        }

        return app(self::BUILDERS[$builder]);
    }

    public function has(string $builder): bool
    {
        return isset(self::BUILDERS[$builder]);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::BUILDERS);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return collect(self::BUILDERS)
            ->map(fn ($class, $key) => ['value' => $key, 'label' => self::LABELS[$key] ?? $key])
            ->values()
            ->all();
    }
}
