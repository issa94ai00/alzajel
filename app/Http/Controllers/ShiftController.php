<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Currency;
use App\Models\Shift;
use App\Services\ReportService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly ReportService $reportService,
    ) {}

    public function index(): Response
    {
        $shifts = Shift::with(['openedBy', 'closedBy'])
            ->latest('opened_at')
            ->paginate(20);

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Shift::class);

        $currentShift = $this->shiftService->getCurrentOpenShift();

        if ($currentShift && ! $request->user()->can('close', $currentShift)) {
            return redirect()->route('dashboard');
        }

        $currencies = Currency::where('is_active', true)->get();

        return Inertia::render('Shifts/Open', [
            'currencies' => $currencies,
            'currentShiftOpen' => (bool) $currentShift,
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        $currentShift = $this->shiftService->getCurrentOpenShift();

        if ($currentShift) {
            $this->authorize('close', $currentShift);

            $this->shiftService->closeCurrentAndOpenNew($request->user(), $request->validated('opening_balances', []));

            return redirect()->route('dashboard')->with('success', 'تم إغلاق الوردية السابقة وفتح وردية جديدة بنجاح.');
        }

        $this->shiftService->openShift($request->user(), $request->validated('opening_balances', []));

        return redirect()->route('dashboard')->with('success', 'تم فتح الوردية بنجاح.');
    }

    public function show(Shift $shift): Response
    {
        $this->authorize('view', $shift);

        return Inertia::render('Shifts/Show', [
            'shift' => $shift->load(['openedBy', 'closedBy']),
            'balances' => $this->reportService->currentBalances($shift),
            'profitLoss' => $this->reportService->profitLossForShift($shift),
        ]);
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorize('close', $shift);

        $this->shiftService->closeShift($shift, $request->user());

        return redirect()->route('shifts.show', $shift)->with('success', 'تم إغلاق الوردية بنجاح.');
    }
}
