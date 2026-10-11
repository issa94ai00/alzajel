<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\ReportRunner;
use App\Services\ReportService;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ShiftService $shiftService,
        private readonly ReportRunner $runner,
    ) {}

    /**
     * أنواع التقارير المتاحة لهذا المستخدم حسب إعدادات النظام.
     */
    public function catalog(Request $request): array
    {
        return [
            'reports' => $this->runner->availableFor($request->user()),
            'export_formats' => $this->runner->exportFormats(),
        ];
    }

    /**
     * تشغيل أي تقرير معرّف في الإعدادات وإرجاع أعمدته وصفوفه.
     */
    public function run(Request $request, string $report): array
    {
        $definition = $this->runner->findByKey($report) ?? abort(404, 'هذا التقرير غير معرّف في النظام.');

        abort_unless($definition->isVisibleTo($request->user()), 403);

        return $this->runner->run($definition, $request->except(['format']), $request->user());
    }

    public function balances(): array
    {
        $shift = $this->shiftService->getCurrentOpenShift();

        return [
            'shift_id' => $shift?->id,
            'balances' => $shift ? $this->reportService->currentBalances($shift) : [],
        ];
    }

    public function daily(Request $request): array
    {
        $range = $request->only(['date_from', 'date_to']);

        return [
            'transactions' => $this->reportService->dailyOperationsReport($range),
            'range' => $range,
        ];
    }

    public function profitLoss(Request $request): array
    {
        $range = $request->only(['date_from', 'date_to']);

        return [
            'shifts' => $this->reportService->profitLossForRange($range),
            'range' => $range,
        ];
    }

    public function currencyMovement(Request $request): array
    {
        $currencies = Currency::where('is_active', true)->where('is_base', false)->get();

        $currency = $request->filled('currency_id')
            ? Currency::findOrFail($request->integer('currency_id'))
            : $currencies->first();

        $range = $request->only(['date_from', 'date_to']);

        return [
            'currencies' => $currencies,
            'selected_currency' => $currency,
            'transactions' => $currency ? $this->reportService->currencyMovementReport($currency, $range) : [],
            'range' => $range,
        ];
    }
}
