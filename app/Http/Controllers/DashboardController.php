<?php

namespace App\Http\Controllers;

use App\Models\ExchangeTransaction;
use App\Services\ReportService;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $shift = $this->shiftService->getCurrentOpenShift($request->user());

        $canViewProfits = $request->user()->can(Permissions::REPORTS_PROFIT_LOSS);

        $stats = null;
        if ($shift) {
            $stats = [
                'transactions_count' => (int) $shift->exchangeTransactions()->count(),
                'buy_count' => (int) $shift->exchangeTransactions()->where('type', 'buy')->count(),
                'sell_count' => (int) $shift->exchangeTransactions()->where('type', 'sell')->count(),
                'total_volume' => (string) ($shift->exchangeTransactions()->sum('total') ?? '0'),
                'total_profit' => $canViewProfits ? (string) ($shift->exchangeTransactions()->sum('profit') ?? '0') : null,
            ];
        }

        return Inertia::render('Dashboard', [
            'shift' => $shift,
            'stats' => $stats,
            'balances' => $shift ? $this->reportService->currentBalances($shift) : [],
            'recentTransactions' => $shift
                ? ExchangeTransaction::with(['currency', 'counterCurrency', 'user'])
                    ->where('shift_id', $shift->id)
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                : [],
        ]);
    }
}
