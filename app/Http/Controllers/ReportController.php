<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\ReportService;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ShiftService $shiftService,
    ) {}

    public function balances(): Response
    {
        $shift = $this->shiftService->getCurrentOpenShift();

        return Inertia::render('Reports/Balances', [
            'shift' => $shift,
            'balances' => $shift ? $this->reportService->currentBalances($shift) : [],
        ]);
    }

    public function daily(Request $request): Response
    {
        $range = $request->only(['date_from', 'date_to']);

        return Inertia::render('Reports/Daily', [
            'transactions' => $this->reportService->dailyOperationsReport($range),
            'range' => $range,
        ]);
    }

    public function profitLoss(Request $request): Response
    {
        $range = $request->only(['date_from', 'date_to']);

        return Inertia::render('Reports/ProfitLoss', [
            'shifts' => $this->reportService->profitLossForRange($range),
            'range' => $range,
        ]);
    }

    public function currencyMovement(Request $request): Response
    {
        $currencies = Currency::where('is_active', true)->where('is_base', false)->get();

        $currency = $request->filled('currency_id')
            ? Currency::findOrFail($request->integer('currency_id'))
            : $currencies->first();

        $range = $request->only(['date_from', 'date_to']);

        return Inertia::render('Reports/CurrencyMovement', [
            'currencies' => $currencies,
            'selectedCurrency' => $currency,
            'transactions' => $currency ? $this->reportService->currencyMovementReport($currency, $range) : [],
            'range' => $range,
        ]);
    }
}
