<?php

namespace App\Http\Controllers;

use App\Models\ExchangeTransaction;
use App\Services\ReportService;
use App\Services\ShiftService;
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

        return Inertia::render('Dashboard', [
            'shift' => $shift,
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
